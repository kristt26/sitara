<?php

namespace App\Libraries;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class XlsxTemplateWriter
{
    private ZipArchive $zip;
    private string $path;

    public function __construct(string $template, string $prefix)
    {
        if (! is_file($template)) throw new RuntimeException('Template Excel belum tersedia.');
        $this->path = tempnam(WRITEPATH . 'uploads', $prefix) ?: '';
        if ($this->path === '' || ! copy($template, $this->path)) throw new RuntimeException('Berkas Excel sementara belum dapat dibuat.');
        $this->zip = new ZipArchive();
        if ($this->zip->open($this->path) !== true) throw new RuntimeException('Template Excel tidak dapat dibuka.');
    }

    public function path(): string { return $this->path; }

    public function close(): void { $this->zip->close(); }

    public function setCellStyle(string $sheetName, string $reference, int $style): void
    {
        $path = $this->sheetPath($sheetName);
        $xml = $this->zip->getFromName($path);
        if ($xml === false) throw new RuntimeException('Sheet ' . $sheetName . ' tidak dapat diproses.');
        $pattern = '#<x:c\b([^>]*\br="' . preg_quote($reference, '#') . '"[^>]*)>#';
        $updated = preg_replace_callback($pattern, static function(array $match) use ($style): string {
            $attributes = preg_replace('/\s+s="\d+"/', '', $match[1]);
            return '<x:c' . $attributes . ' s="' . $style . '">';
        }, $xml, 1, $count);
        if ($updated === null || $count !== 1 || ! $this->zip->addFromString($path, $updated)) throw new RuntimeException('Format sel ' . $reference . ' belum dapat diterapkan.');
    }

    /** @param list<list<mixed>|array{values:list<mixed>,styles?:array<int,int>}> $rows @param array<int|string,int> $styles */
    public function replaceRowsAfter(string $sheetName, int $keepThroughRow, array $rows, int $startRow, array $styles = []): void
    {
        $path = $this->sheetPath($sheetName);
        $xml = $this->zip->getFromName($path);
        if ($xml === false || ! preg_match('#<x:sheetData>(.*?)</x:sheetData>#s', $xml, $match)) throw new RuntimeException('Sheet ' . $sheetName . ' tidak dapat diproses.');
        preg_match_all('#<x:row\b[^>]*\br="(\d+)"[^>]*>.*?</x:row>#s', $match[1], $existing, PREG_SET_ORDER);
        $content = '';
        foreach ($existing as $row) if ((int) $row[1] <= $keepThroughRow) $content .= $row[0];
        foreach ($rows as $offset => $row) {
            $values = isset($row['values']) && is_array($row['values']) ? $row['values'] : $row;
            $rowStyles = isset($row['styles']) && is_array($row['styles']) ? $row['styles'] : $styles;
            $content .= $this->rowXml($startRow + $offset, $values, $rowStyles);
        }
        $updated = preg_replace('#<x:sheetData>.*?</x:sheetData>#s', '<x:sheetData>' . $content . '</x:sheetData>', $xml, 1);
        if ($updated === null || ! $this->zip->addFromString($path, $updated)) throw new RuntimeException('Data sheet ' . $sheetName . ' belum dapat ditulis.');
    }

    private function rowXml(int $row, array $values, array $styles): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $ref = $this->column($index) . $row;
            $styleValue = $styles[$ref] ?? ($styles[$index] ?? null);
            $style = $styleValue !== null ? ' s="' . $styleValue . '"' : '';
            if ($value === null || $value === '') {
                if ($styleValue !== null) $cells .= '<x:c r="' . $ref . '"' . $style . '/>';
                continue;
            }
            if (is_int($value) || is_float($value)) $cells .= '<x:c r="' . $ref . '"' . $style . ' t="n"><x:v>' . $value . '</x:v></x:c>';
            else $cells .= '<x:c r="' . $ref . '"' . $style . ' t="inlineStr"><x:is><x:t xml:space="preserve">' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</x:t></x:is></x:c>';
        }
        return '<x:row r="' . $row . '">' . $cells . '</x:row>';
    }

    private function sheetPath(string $sheetName): string
    {
        $workbook = $this->xml('xl/workbook.xml');
        $rels = $this->xml('xl/_rels/workbook.xml.rels');
        $targets = [];
        foreach ($rels->xpath('//*[local-name()="Relationship"]') ?: [] as $rel) $targets[(string) $rel['Id']] = (string) $rel['Target'];
        foreach ($workbook->xpath('//*[local-name()="sheet"]') ?: [] as $sheet) {
            if (strcasecmp((string) $sheet['name'], $sheetName) !== 0) continue;
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = ltrim(str_replace('\\', '/', $targets[$id] ?? ''), '/');
            return str_starts_with($target, 'xl/') ? $target : 'xl/' . preg_replace('#^(\.\./)+#', '', $target);
        }
        throw new RuntimeException('Sheet ' . $sheetName . ' tidak ditemukan.');
    }

    private function xml(string $path): SimpleXMLElement
    {
        $content = $this->zip->getFromName($path);
        $xml = $content === false ? false : simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) throw new RuntimeException('Struktur Excel tidak dapat dibaca.');
        return $xml;
    }

    private function column(int $index): string
    {
        $name = '';
        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) $name = chr(($number - 1) % 26 + 65) . $name;
        return $name;
    }
}
