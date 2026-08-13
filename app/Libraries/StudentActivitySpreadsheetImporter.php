<?php

namespace App\Libraries;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class StudentActivitySpreadsheetImporter
{
    /** @return list<array<string,mixed>> */
    public function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Berkas Excel tidak valid atau rusak.');
        try {
            $strings = $this->sharedStrings($zip);
            $activities = $this->table($zip, 'Data Kegiatan', $strings, [
                'kode baris'=>'row_code','nim'=>'nim','kode jenis kegiatan'=>'activity_code','kode jalur ujian'=>'exam_path_code','judul'=>'title','jadwal (yyyy-mm-dd hh:mm)'=>'scheduled_at','status'=>'status','catatan'=>'notes',
            ], ['row_code','nim','activity_code','exam_path_code']);
            $assignments = $this->table($zip, 'Penugasan Dosen', $strings, [
                'kode baris'=>'row_code','nidn/nip dosen'=>'lecturer_identifier','peran'=>'role_type','posisi ke-'=>'position_no',
            ], ['row_code','lecturer_identifier','role_type','position_no'], true);
        } finally { $zip->close(); }

        $grouped = [];
        foreach ($assignments as $assignment) $grouped[strtoupper(trim((string)$assignment['row_code']))][] = $assignment;
        $seen = [];
        foreach ($activities as &$activity) {
            $code = strtoupper(trim((string)$activity['row_code']));
            if ($code === '') throw new RuntimeException('Baris ' . $activity['_row'] . ': Kode Baris wajib diisi.');
            if (isset($seen[$code])) throw new RuntimeException('Kode Baris ' . $code . ' digunakan lebih dari sekali.');
            $seen[$code] = true;
            $activity['row_code'] = $code;
            $activity['assignments'] = $grouped[$code] ?? [];
            unset($grouped[$code]);
        }
        unset($activity);
        if ($grouped !== []) throw new RuntimeException('Sheet Penugasan Dosen memiliki Kode Baris yang tidak ditemukan pada Data Kegiatan: ' . array_key_first($grouped) . '.');
        return $activities;
    }

    private function table(ZipArchive $zip, string $sheet, array $strings, array $map, array $required, bool $allowEmpty = false): array
    {
        $rows = $this->rows($zip, $this->sheetPath($zip, $sheet), $strings);
        $headerAt = null; $columns = [];
        foreach (array_slice($rows, 0, 25, true) as $index => $row) {
            $candidate = [];
            foreach ($row['cells'] as $column => $value) { $key = $this->normalize($value); if (isset($map[$key])) $candidate[$column] = $map[$key]; }
            if (count(array_intersect($required, $candidate)) === count($required)) { $headerAt = $index; $columns = $candidate; break; }
        }
        if ($headerAt === null) throw new RuntimeException('Judul kolom sheet ' . $sheet . ' tidak sesuai template.');
        $result = [];
        foreach (array_slice($rows, $headerAt + 1) as $row) {
            $data = ['_row'=>$row['number']]; $has = false;
            foreach ($map as $field) $data[$field] = '';
            foreach ($columns as $column => $field) { $data[$field] = trim($row['cells'][$column] ?? ''); if ($data[$field] !== '') $has = true; }
            if ($has) $result[] = $data;
        }
        if (!$allowEmpty && $result === []) throw new RuntimeException('Sheet ' . $sheet . ' belum berisi data.');
        if (count($result) > 1000) throw new RuntimeException('Maksimal 1.000 baris per sheet dapat diimpor.');
        return $result;
    }

    private function rows(ZipArchive $zip, string $path, array $strings): array
    {
        $xml = $this->xml($zip, $path); $rows = [];
        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $cells = [];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) $cells[$this->columnIndex((string)$cell['r'])] = $this->cellValue($cell, $strings);
            $rows[] = ['number'=>(int)$row['r'],'cells'=>$cells];
        }
        return $rows;
    }

    private function sheetPath(ZipArchive $zip, string $name): string
    {
        $wb=$this->xml($zip,'xl/workbook.xml');$rels=$this->xml($zip,'xl/_rels/workbook.xml.rels');$targets=[];
        foreach($rels->xpath('//*[local-name()="Relationship"]')?:[] as$r)$targets[(string)$r['Id']]=(string)$r['Target'];
        foreach($wb->xpath('//*[local-name()="sheet"]')?:[] as$s)if(strcasecmp((string)$s['name'],$name)===0){$id=(string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];$target=ltrim(str_replace('\\','/',$targets[$id]??''),'/');return str_starts_with($target,'xl/')?$target:'xl/'.preg_replace('#^(\.\./)+#','',$target);}
        throw new RuntimeException('Sheet '.$name.' tidak ditemukan.');
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        if($zip->locateName('xl/sharedStrings.xml')===false)return[];$xml=$this->xml($zip,'xl/sharedStrings.xml');$out=[];
        foreach($xml->xpath('//*[local-name()="si"]')?:[] as$item){$parts=[];foreach($item->xpath('.//*[local-name()="t"]')?:[] as$t)$parts[]=(string)$t;$out[]=implode('',$parts);}return$out;
    }

    private function cellValue(SimpleXMLElement $cell,array $strings):string
    {
        $type=(string)$cell['t'];if($type==='inlineStr'){$p=[];foreach($cell->xpath('.//*[local-name()="t"]')?:[] as$t)$p[]=(string)$t;return implode('',$p);} $v=$cell->xpath('./*[local-name()="v"]')?:[];$value=isset($v[0])?(string)$v[0]:'';return$type==='s'?($strings[(int)$value]??''):$value;
    }

    private function xml(ZipArchive $zip,string $path):SimpleXMLElement{$content=$zip->getFromName($path);$xml=$content===false?false:simplexml_load_string($content,SimpleXMLElement::class,LIBXML_NONET);if($xml===false)throw new RuntimeException('Struktur Excel tidak lengkap.');return$xml;}
    private function normalize(string $value):string{$value=strtolower(trim(str_replace('*','',$value)));return preg_replace('/\s+/',' ',$value)??$value;}
    private function columnIndex(string $ref):int{preg_match('/^([A-Z]+)/i',$ref,$m);$n=0;foreach(str_split(strtoupper($m[1]??''))as$c)$n=$n*26+ord($c)-64;return$n-1;}
}
