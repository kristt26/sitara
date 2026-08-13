<?php

namespace App\Libraries;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class StudentSpreadsheetImporter
{
    private const MAX_ROWS = 1000;

    /** @return list<array<string, string|int>> */
    public function read(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZIP pada server belum tersedia untuk membaca berkas Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas Excel tidak dapat dibuka. Gunakan format .xlsx yang disediakan.');
        }

        try {
            $rows = $this->worksheetRows($zip, $this->dataSheetPath($zip), $this->sharedStrings($zip));
        } finally {
            $zip->close();
        }

        return $this->mapRows($rows);
    }

    private function dataSheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xmlFromZip($zip, 'xl/workbook.xml');
        $relationships = $this->xmlFromZip($zip, 'xl/_rels/workbook.xml.rels');
        $targets = [];
        foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            $attributes = $relationship->attributes();
            $targets[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $selectedId = null;
        $firstId = null;
        foreach ($workbook->xpath('//*[local-name()="sheet"]') ?: [] as $sheet) {
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $firstId ??= $id;
            if (strcasecmp(trim((string) $sheet['name']), 'Data Mahasiswa') === 0) {
                $selectedId = $id;
                break;
            }
        }

        $target = $targets[$selectedId ?? $firstId ?? ''] ?? null;
        if ($target === null || $target === '') {
            throw new RuntimeException('Sheet Data Mahasiswa tidak ditemukan pada berkas Excel.');
        }
        $target = ltrim(str_replace('\\', '/', $target), '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/' . preg_replace('#^(\.\./)+#', '', $target);
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $xml = $this->xmlFromZip($zip, 'xl/sharedStrings.xml');
        $strings = [];
        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $parts = [];
            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $parts[] = (string) $text;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings @return list<array{number:int,cells:array<int,string>}> */
    private function worksheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $this->xmlFromZip($zip, $sheetPath);
        $rows = [];
        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $cells = [];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                if ($column >= 0) {
                    $cells[$column] = $this->cellValue($cell, $sharedStrings);
                }
            }
            $rows[] = ['number' => (int) $row['r'], 'cells' => $cells];
        }

        return $rows;
    }

    /** @param list<array{number:int,cells:array<int,string>}> $rows @return list<array<string,string|int>> */
    private function mapRows(array $rows): array
    {
        $headerIndex = null;
        $columns = [];
        foreach (array_slice($rows, 0, 25, true) as $index => $row) {
            $candidate = [];
            foreach ($row['cells'] as $column => $value) {
                if (($field = $this->headerField($value)) !== null) {
                    $candidate[$column] = $field;
                }
            }
            if (in_array('nim', $candidate, true) && in_array('full_name', $candidate, true) && in_array('program_code', $candidate, true)) {
                $headerIndex = $index;
                $columns = $candidate;
                break;
            }
        }
        if ($headerIndex === null) {
            throw new RuntimeException('Judul kolom Excel tidak sesuai format. Unduh dan gunakan template impor mahasiswa.');
        }

        $result = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $data = [
                '_row' => $row['number'], 'nim' => '', 'full_name' => '', 'program_code' => '',
                'cohort_year' => '', 'email' => '', 'phone' => '', 'status' => '',
            ];
            foreach ($columns as $column => $field) {
                $data[$field] = trim($row['cells'][$column] ?? '');
            }
            if (implode('', array_map('strval', array_diff_key($data, ['_row' => true]))) !== '') {
                $result[] = $data;
            }
            if (count($result) > self::MAX_ROWS) {
                throw new RuntimeException('Maksimal 1.000 baris data mahasiswa dapat diimpor sekaligus.');
            }
        }
        if ($result === []) {
            throw new RuntimeException('Berkas Excel belum berisi data mahasiswa.');
        }

        return $result;
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $parts[] = (string) $text;
            }
            return implode('', $parts);
        }
        $values = $cell->xpath('./*[local-name()="v"]') ?: [];
        $value = isset($values[0]) ? (string) $values[0] : '';

        return $type === 's' ? ($sharedStrings[(int) $value] ?? '') : $value;
    }

    private function headerField(string $header): ?string
    {
        $header = strtolower(trim(str_replace('*', '', $header)));
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return [
            'nim' => 'nim', 'nama lengkap' => 'full_name', 'nama mahasiswa' => 'full_name',
            'kode program studi' => 'program_code', 'kode prodi' => 'program_code', 'program studi' => 'program_code',
            'angkatan' => 'cohort_year', 'tahun angkatan' => 'cohort_year', 'email' => 'email',
            'telepon' => 'phone', 'nomor telepon' => 'phone', 'no telepon' => 'phone', 'status' => 'status',
        ][$header] ?? null;
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return -1;
        }
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function xmlFromZip(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $stat = $zip->statName($path);
        if (is_array($stat) && (int) ($stat['size'] ?? 0) > 20 * 1024 * 1024) {
            throw new RuntimeException('Isi berkas Excel terlalu besar untuk diproses.');
        }
        $contents = $zip->getFromName($path);
        if ($contents === false) {
            throw new RuntimeException('Struktur berkas Excel tidak lengkap atau rusak.');
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new RuntimeException('Struktur XML pada berkas Excel tidak dapat dibaca.');
        }

        return $xml;
    }
}
