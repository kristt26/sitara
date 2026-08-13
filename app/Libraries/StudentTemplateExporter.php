<?php

namespace App\Libraries;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class StudentTemplateExporter
{
    /** @param list<array{code:string,name:string,degree_level:string,is_active:int|string}> $programs */
    public function export(array $programs): string
    {
        if (count($programs) > 100) {
            throw new RuntimeException('Template mendukung maksimal 100 program studi.');
        }

        $source = FCPATH . 'templates/template-import-mahasiswa.xlsx';
        if (! is_file($source)) {
            throw new RuntimeException('Template impor mahasiswa belum tersedia.');
        }
        $temporary = tempnam(WRITEPATH . 'uploads', 'student-template-');
        if ($temporary === false || ! copy($source, $temporary)) {
            throw new RuntimeException('Template impor mahasiswa belum dapat dibuat.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporary) !== true) {
            @unlink($temporary);
            throw new RuntimeException('Template impor mahasiswa tidak dapat dibuka.');
        }

        try {
            $sheetPath = $this->sheetPath($zip, 'Daftar Prodi');
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) {
                throw new RuntimeException('Sheet Daftar Prodi tidak ditemukan pada template.');
            }

            foreach ($programs as $index => $program) {
                $row = $index + 2;
                $values = [
                    'A' => $program['code'],
                    'B' => $program['name'],
                    'C' => $program['degree_level'],
                    'D' => ((int) $program['is_active'] === 1 ? 'AKTIF' : 'NONAKTIF'),
                ];
                foreach ($values as $column => $value) {
                    $reference = $column . $row;
                    $pattern = '#<(\w+:)?c\b([^>]*\br="' . preg_quote($reference, '#') . '"[^>]*)\s*/>#';
                    $replacement = '<${1}c$2 t="inlineStr"><${1}is><${1}t>' . $this->escape((string) $value) . '</${1}t></${1}is></${1}c>';
                    $updated = preg_replace($pattern, $replacement, $xml, 1, $count);
                    if ($updated === null || $count !== 1) {
                        throw new RuntimeException('Sel ' . $reference . ' pada sheet Daftar Prodi tidak dapat diperbarui.');
                    }
                    $xml = $updated;
                }
            }

            if (! $zip->addFromString($sheetPath, $xml)) {
                throw new RuntimeException('Daftar program studi belum dapat ditulis ke template.');
            }
        } finally {
            $zip->close();
        }

        return $temporary;
    }

    private function sheetPath(ZipArchive $zip, string $sheetName): string
    {
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $targets = [];
        foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            $attributes = $relationship->attributes();
            $targets[(string) $attributes['Id']] = (string) $attributes['Target'];
        }
        foreach ($workbook->xpath('//*[local-name()="sheet"]') ?: [] as $sheet) {
            if (strcasecmp((string) $sheet['name'], $sheetName) !== 0) {
                continue;
            }
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = ltrim(str_replace('\\', '/', $targets[$id] ?? ''), '/');
            return str_starts_with($target, 'xl/') ? $target : 'xl/' . preg_replace('#^(\.\./)+#', '', $target);
        }
        throw new RuntimeException('Sheet ' . $sheetName . ' tidak ditemukan pada template.');
    }

    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $contents = $zip->getFromName($path);
        $xml = $contents === false ? false : simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            throw new RuntimeException('Struktur template Excel tidak dapat dibaca.');
        }
        return $xml;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
