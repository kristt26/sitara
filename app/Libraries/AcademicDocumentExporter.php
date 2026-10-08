<?php

namespace App\Libraries;

use RuntimeException;
use ZipArchive;

/** Generates the three academic documents from the supplied DOCX layouts. */
class AcademicDocumentExporter
{
    public function export(string $template, array $activities, string $kind, array $configuredFields = []): string
    {
        if (!is_file($template)) {
            throw new RuntimeException('Template dokumen belum tersedia.');
        }
        $zip = new ZipArchive();
        if ($zip->open($template) !== true) {
            throw new RuntimeException('Template dokumen tidak dapat dibuka.');
        }
        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new RuntimeException('Struktur DOCX tidak valid.');
        }
        $data = $this->data($activities);
        $this->applyConfiguredFields($data, $activities, $configuredFields);
        $xml = $this->replaceParagraphs($xml, $data, $kind);
        $output = tempnam(sys_get_temp_dir(), 'sitara-doc-') . '.docx';
        $zip->close();

        // Re-open and copy every entry to a new archive so the original template is untouched.
        $source = new ZipArchive();
        $source->open($template);
        $target = new ZipArchive();
        $target->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        for ($i = 0; $i < $source->numFiles; $i++) {
            $name = $source->getNameIndex($i);
            $target->addFromString($name, $name === 'word/document.xml' ? $xml : $source->getFromIndex($i));
        }
        $source->close();
        $target->close();
        return $output;
    }

    private function data(array $activities): array
    {
        $first = $activities[0] ?? [];
        $students = array_map(static fn(array $a): string => trim((string)($a['full_name'] ?? '')), $activities);
        $nims = array_map(static fn(array $a): string => trim((string)($a['nim'] ?? '')), $activities);
        $assignments = $first['assignments'] ?? [];
        $supervisors = array_values(array_filter($assignments, static fn(array $a): bool => ($a['role_type'] ?? '') === 'PEMBIMBING'));
        $examiners = array_values(array_filter($assignments, static fn(array $a): bool => ($a['role_type'] ?? '') === 'PENGUJI'));
        $scheduled = $first['scheduled_at'] ?? null;
        $date = $scheduled ? date('d F Y', strtotime((string)$scheduled)) : '';
        $time = $scheduled ? date('H.i', strtotime((string)$scheduled)) . ' WIT' : '';
        $names = implode(', ', array_filter($students));
        $tokens = [
            'nama_mahasiswa_1' => $names, 'nama_mahasiswa_2' => count($students) > 1 ? implode(', ', array_slice($students, 1)) : '',
            'nama_mahasiswa' => $names, 'npm' => implode(', ', array_filter($nims)), 'jenjang' => 'S1 (Sarjana)',
            'program_studi' => (string)($first['program_name'] ?? ''), 'pembimbing_utama' => (string)($supervisors[0]['full_name'] ?? ''),
            // Common aliases used by templates uploaded by Prodi.
            'nama_pembimbing' => (string)($supervisors[0]['full_name'] ?? ''),
            'nama_pembimbing_utama' => (string)($supervisors[0]['full_name'] ?? ''),
            'nama_pembimbing_pendamping' => (string)($supervisors[1]['full_name'] ?? ''),
            'nama_penguji' => (string)($examiners[0]['full_name'] ?? ''),
            'pembimbing_pendamping' => (string)($supervisors[1]['full_name'] ?? ''), 'judul_proposal' => (string)($first['title'] ?? ''),
            'judul' => (string)($first['title'] ?? ''), 'nim' => implode(', ', array_filter($nims)),
            'hari_tanggal' => $date, 'hari' => $scheduled ? date('l', strtotime((string)$scheduled)) : '', 'tanggal' => $date,
            'durasi_waktu' => '60 Menit', 'waktu_pelaksanaan' => $time, 'waktu' => $time, 'lokasi' => 'Ruang Ujian',
            'nomor_surat' => (string)($first['nomor_surat'] ?? '${nomor_surat}'), 'lampiran' => '1 (Satu) Draf Proposal Tugas Akhir',
            'hasil_ujian' => '${hasil_ujian}', 'nilai_akhir' => '${nilai_akhir}', 'nilai_huruf' => '${nilai_huruf}',
            'nilai_isi_proposal' => '${nilai_isi_proposal}', 'hasil_isi_proposal' => '${hasil_isi_proposal}',
            'nilai_penguasaan_materi' => '${nilai_penguasaan_materi}', 'hasil_penguasaan_materi' => '${hasil_penguasaan_materi}',
            'nilai_etika' => '${nilai_etika}', 'hasil_etika' => '${hasil_etika}', 'jumlah_nilai' => '${jumlah_nilai}',
            'catatan_revisi' => '${catatan_revisi}', 'nuptk' => (string)($supervisors[0]['nidn'] ?? $supervisors[0]['nip'] ?? ''),
            'pembimbing_utama_nuptk' => (string)($supervisors[0]['nidn'] ?? $supervisors[0]['nip'] ?? ''),
            'nuptk_pembimbing' => (string)($supervisors[0]['nidn'] ?? $supervisors[0]['nip'] ?? ''),
            'dosen_revisi' => (string)($supervisors[0]['full_name'] ?? ''),
            'dosen_revisi_nuptk' => (string)($supervisors[0]['nidn'] ?? $supervisors[0]['nip'] ?? ''),
        ];
        foreach ($supervisors as $i => $supervisor) {
            $tokens['pembimbing_' . ($i + 1)] = (string)($supervisor['full_name'] ?? '');
            $tokens['pembimbing_' . ($i + 1) . '_name'] = (string)($supervisor['full_name'] ?? '');
            $tokens['pembimbing_' . ($i + 1) . '_nidn'] = (string)($supervisor['nidn'] ?? '');
            $tokens['pembimbing_' . ($i + 1) . '_nip'] = (string)($supervisor['nip'] ?? '');
            $tokens['penilai_' . ($i + 1) . '_peran'] = 'Pembimbing ' . ($i + 1);
            $tokens['penilai_' . ($i + 1) . '_nuptk'] = (string)($supervisor['nidn'] ?? $supervisor['nip'] ?? '');
        }
        for ($i = count($supervisors) + 1; $i <= 10; $i++) {
            $tokens['pembimbing_' . $i] = '';
            $tokens['pembimbing_' . $i . '_name'] = '';
            $tokens['pembimbing_' . $i . '_nidn'] = '';
            $tokens['pembimbing_' . $i . '_nip'] = '';
        }
        $offset = count($supervisors);
        for ($i = 1; $i <= 10; $i++) $tokens['nama_penguji' . $i] = '';
        foreach ($examiners as $i => $examiner) {
            $tokens['penguji_' . ($i + 1)] = (string)($examiner['full_name'] ?? '');
            $tokens['penguji_' . ($i + 1) . '_name'] = (string)($examiner['full_name'] ?? '');
            $tokens['nama_penguji' . ($i + 1)] = (string)($examiner['full_name'] ?? '');
            $tokens['penguji_' . ($i + 1) . '_nidn'] = (string)($examiner['nidn'] ?? '');
            $tokens['penguji_' . ($i + 1) . '_nip'] = (string)($examiner['nip'] ?? '');
            $tokens['penilai_' . ($offset + $i + 1) . '_peran'] = 'Penguji ' . ($i + 1);
            $tokens['penilai_' . ($offset + $i + 1) . '_nuptk'] = (string)($examiner['nidn'] ?? $examiner['nip'] ?? '');
        }
        return [
            'Nama Mahasiswa 1' => $names,
            'Nama Mahasiswa 2' => count($students) > 1 ? implode(', ', array_slice($students, 1)) : '',
            'Nama Mahasiswa' => $names,
            'Nomor Pokok Mahasiswa' => implode(', ', array_filter($nims)),
            'Jenjang Pendidikan' => 'S1 (Sarjana)',
            'Program Studi' => (string)($first['program_name'] ?? ''),
            'Pembimbing Utama' => (string)($supervisors[0]['full_name'] ?? ''),
            'Pembimbing Pendamping' => (string)($supervisors[1]['full_name'] ?? ''),
            'Judul Proposal' => (string)($first['title'] ?? ''),
            'Hari / Tanggal' => $date,
            'Durasi Waktu' => '60 Menit',
            'pukul' => $time,
            'waktu_pelaksanaan' => $time,
            'Lokasi' => 'Ruang Ujian',
            'NUPTK' => (string)($supervisors[0]['nidn'] ?? $supervisors[0]['nip'] ?? ''),
            '__supervisors' => array_map(static fn(array $a): string => (string)($a['full_name'] ?? ''), $supervisors),
            '__examiners' => array_map(static fn(array $a): string => (string)($a['full_name'] ?? ''), $examiners),
            '__evaluator_names' => array_map(static fn(array $a): string => (string)($a['full_name'] ?? ''), array_merge($supervisors, $examiners)),
            '__evaluator_nuptk' => array_map(static fn(array $a): string => (string)($a['nidn'] ?? $a['nip'] ?? ''), array_merge($supervisors, $examiners)),
            '__evaluator_roles' => array_merge(array_map(static fn(int $i): string => 'Pembimbing ' . ($i + 1), array_keys($supervisors)), array_map(static fn(int $i): string => 'Penguji ' . ($i + 1), array_keys($examiners))),
            ...$tokens,
        ];
    }

    private function applyConfiguredFields(array &$data, array $activities, array $fields): void
    {
        $first = $activities[0] ?? [];
        $assignments = $first['assignments'] ?? [];
        $supervisors = array_values(array_filter($assignments, static fn(array $a): bool => ($a['role_type'] ?? '') === 'PEMBIMBING'));
        $examiners = array_values(array_filter($assignments, static fn(array $a): bool => ($a['role_type'] ?? '') === 'PENGUJI'));
        foreach ($fields as $field) {
            $key = trim((string)($field['field_key'] ?? ''));
            if ($key === '') continue;
            $source = strtoupper(trim((string)($field['source_type'] ?? 'MANUAL')));
            $sourceKey = trim((string)($field['source_key'] ?? ''));
            if ($source === 'MANUAL' || $source === 'TEKS_TETAP') continue;
            $value = '';
            if ($source === 'MAHASISWA') $value = (string)($first[$sourceKey] ?? '');
            elseif ($source === 'KEGIATAN') $value = (string)($first[$sourceKey] ?? '');
            elseif ($source === 'JADWAL') {
                $scheduled = (string)($first['scheduled_at'] ?? '');
                $value = $sourceKey === 'scheduled_date' ? ($scheduled ? date('d F Y', strtotime($scheduled)) : '')
                    : ($sourceKey === 'scheduled_time' ? ($scheduled ? date('H.i', strtotime($scheduled)) . ' WIT' : '')
                    : (string)($first[$sourceKey] ?? ($sourceKey === 'location' ? 'Ruang Ujian' : '')));
            }
            elseif ($source === 'DOSEN') {
                $person = array_values(array_filter($assignments, static fn(array $a): bool => ((string)($a[$sourceKey] ?? '') !== '')))[0] ?? [];
                $value = (string)($person[$sourceKey] ?? '');
            } elseif (in_array($source, ['NILAI', 'KEUANGAN'], true)) {
                $value = (string)($first[$sourceKey] ?? '');
            } elseif (preg_match('/^(pembimbing|penguji)_(\d+)_(name|nidn|nip)$/', $sourceKey, $m)) {
                $list = $m[1] === 'pembimbing' ? $supervisors : $examiners;
                $person = $list[((int)$m[2]) - 1] ?? [];
                $personKey = $m[3] === 'name' ? 'full_name' : $m[3];
                $value = (string)($person[$personKey] ?? '');
            } elseif ($source === 'PEMBIMBING' || $source === 'PENGUJI') {
                $list = $source === 'PEMBIMBING' ? $supervisors : $examiners;
                $value = (string)($list[0][$sourceKey] ?? '');
            }
            $data[$key] = $value;
        }
    }

    private function replaceParagraphs(string $xml, array $values, string $kind): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (!@$dom->loadXML($xml)) {
            throw new RuntimeException('XML dokumen tidak dapat diproses.');
        }
        $xpath = new \DOMXPath($dom);
        // Word may split a placeholder across several formatted runs. Resolve
        // placeholders at paragraph level first so tokens such as
        // ${nama_pembimbing} are still recognized when split as ${nama_ + pembimbing}.
        $this->replaceSplitPlaceholders($xpath, $values);
        foreach ($xpath->query('//*[local-name()="t"]') as $node) {
            $node->nodeValue = preg_replace_callback('/\$\{([a-z0-9_]+)\}/i', static function (array $m) use ($values): string {
                $key = strtolower($m[1]);
                return array_key_exists($key, $values) ? (string)$values[$key] : (array_key_exists($m[1], $values) ? (string)$values[$m[1]] : $m[0]);
            }, $node->nodeValue);
        }
        // Berita acara templates are controlled exclusively by explicit
        // ${field_key} placeholders. Keep all other template text, tables,
        // sample names, tabs, and signatures unchanged.
        if (in_array($kind, ['tunggal', 'tim'], true)) return $dom->saveXML();
        $paragraphs = $xpath->query('//*[local-name()="p"]');
        $previous = '';
        $titleLabel = false;
        $penilaiNo = 0;
        $roleNo = 0;
        $inRekap = true;
        $inEvaluasi = false;
        foreach ($paragraphs as $paragraph) {
            $nodes = $xpath->query('.//*[local-name()="t"]', $paragraph);
            $text = '';
            foreach ($nodes as $node) $text .= $node->textContent;
            $normalized = preg_replace('/\s+/u', ' ', trim($text));
            if ($titleLabel && $normalized !== '') {
                $this->setParagraph($xpath, $paragraph, (string)($values['Judul Proposal'] ?? ''));
                $titleLabel = false;
                $previous = $normalized;
                continue;
            }
            if ($kind !== 'nilai' && stripos($normalized, 'Dengan Judul Proposal') !== false) $titleLabel = true;
            if ($normalized === 'BERITA ACARA NILAI') { $inRekap = false; $inEvaluasi = true; }
            if ($normalized === 'LEMBAR REVISI') $inEvaluasi = false;
            if ($normalized === 'Penilai') { $penilaiNo++; $previous = $normalized; continue; }
            if ($kind !== 'nilai' && in_array($normalized, ['( )', 'Nama Pembimbing Utama'], true)) {
                $this->setParagraph($xpath, $paragraph, (string)($values['Pembimbing Utama'] ?? ''));
                $previous = $normalized;
                continue;
            }
            if ($kind !== 'nilai' && stripos($normalized, 'NUPTK') !== false) {
                $this->setParagraph($xpath, $paragraph, 'NUPTK. ' . ($values['NUPTK'] ?? ''));
                $previous = $normalized;
                continue;
            }
            if (preg_match('/^\((Pembimbing|Penguji)\s+\d+\)$/i', $normalized)) {
                $this->setParagraph($xpath, $paragraph, '(' . (($values['__evaluator_roles'][$roleNo] ?? $normalized)) . ')');
                $roleNo++;
                $previous = $normalized;
                continue;
            }
            if ($previous === 'Penilai') {
                $this->setParagraph($xpath, $paragraph, '(' . (($values['__evaluator_roles'][$penilaiNo - 1] ?? '')) . ')');
                $previous = $normalized;
                continue;
            }
            if (stripos($normalized, 'Jayapura,') === 0) {
                $this->setParagraph($xpath, $paragraph, 'Jayapura, ' . ($values['Hari / Tanggal'] ?? ''));
                $previous = $normalized;
                continue;
            }
            if ($inRekap && $normalized === 'Pembimbing Utama') {
                $this->setParagraph($xpath, $paragraph, (string)($values['Pembimbing Utama'] ?? ''));
                $previous = $normalized;
                continue;
            }
            if ($inRekap && stripos($normalized, 'NUPTK') !== false) {
                $this->setParagraph($xpath, $paragraph, 'NUPTK. ' . ($values['pembimbing_utama_nuptk'] ?? ''));
                $previous = $normalized;
                continue;
            }
            if (!$inRekap && $inEvaluasi && stripos($normalized, 'NUPTK') !== false) {
                $this->setParagraph($xpath, $paragraph, 'NUPTK. ' . ($values['__evaluator_nuptk'][$roleNo - 1] ?? ''));
                $previous = $normalized;
                continue;
            }
            if (!$inRekap && !$inEvaluasi && stripos($normalized, 'NUPTK') !== false) {
                $this->setParagraph($xpath, $paragraph, 'NUPTK. ' . ($values['dosen_revisi_nuptk'] ?? ''));
                $previous = $normalized;
                continue;
            }
            foreach ($values as $label => $value) {
                if (str_starts_with((string)$label, '__')) continue;
                if (stripos($normalized, $label) === false) continue;
                $colon = strpos($normalized, ':');
                if ($colon === false) continue;
                $prefix = trim(substr($normalized, 0, $colon + 1));
                if (stripos($prefix, $label) === false) continue;
                $this->replaceParagraphValue($xpath, $paragraph, $colon, trim((string)$value));
                break;
            }
            $previous = $normalized;
        }
        $this->replaceRoleTables($xpath, $values);
        return $dom->saveXML();
    }

    private function replaceSplitPlaceholders(\DOMXPath $xpath, array $values): void
    {
        foreach ($xpath->query('//*[local-name()="p"]') as $paragraph) {
            $nodes = $xpath->query('.//*[local-name()="t"]', $paragraph);
            if ($nodes->length < 2) continue;
            // Replace one cross-run token at a time. Text stays in its original
            // runs; only the characters belonging to the token are changed.
            while (true) {
                $texts = [];
                $combined = '';
                foreach ($nodes as $node) { $text = $node->textContent; $texts[] = $text; $combined .= $text; }
                if (!preg_match('/\$\{([a-z0-9_]+)\}/i', $combined, $match, PREG_OFFSET_CAPTURE)) break;
                $tokenStart = (int)$match[0][1];
                $tokenLength = strlen($match[0][0]);
                $tokenEnd = $tokenStart + $tokenLength;
                $key = strtolower($match[1][0]);
                $replacement = array_key_exists($key, $values) ? (string)$values[$key] : (array_key_exists($match[1][0], $values) ? (string)$values[$match[1][0]] : $match[0][0]);
                $offset = 0; $startNode = $endNode = null; $startLocal = $endLocal = 0;
                foreach ($texts as $i => $text) {
                    $next = $offset + strlen($text);
                    if ($startNode === null && $tokenStart < $next) { $startNode = $i; $startLocal = $tokenStart - $offset; }
                    if ($tokenEnd <= $next) { $endNode = $i; $endLocal = $tokenEnd - $offset; break; }
                    $offset = $next;
                }
                if ($startNode === null || $endNode === null) break;
                if ($startNode === $endNode) {
                    $texts[$startNode] = substr($texts[$startNode], 0, $startLocal) . $replacement . substr($texts[$startNode], $endLocal);
                } else {
                    $texts[$startNode] = substr($texts[$startNode], 0, $startLocal) . $replacement;
                    for ($i = $startNode + 1; $i < $endNode; $i++) $texts[$i] = '';
                    $texts[$endNode] = substr($texts[$endNode], $endLocal);
                }
                foreach ($texts as $i => $text) $nodes->item($i)->nodeValue = $text;
            }
        }
    }

    private function setParagraph(\DOMXPath $xpath, \DOMNode $paragraph, string $value): void
    {
        $nodes = $xpath->query('.//*[local-name()="t"]', $paragraph);
        if ($nodes->length === 0) return;
        $nodes->item(0)->nodeValue = $value;
        for ($i = 1; $i < $nodes->length; $i++) $nodes->item($i)->nodeValue = '';
    }

    /** Replace only the value after the first colon; preserve Word tabs and run formatting. */
    private function replaceParagraphValue(\DOMXPath $xpath, \DOMNode $paragraph, int $colon, string $value): void
    {
        $nodes = $xpath->query('.//*[local-name()="t"]', $paragraph);
        $position = 0;
        $target = null;
        for ($i = 0; $i < $nodes->length; $i++) {
            $nodeText = $nodes->item($i)->textContent;
            $next = $position + strlen($nodeText);
            if ($target === null && $colon < $next) {
                $target = $i;
                break;
            }
            $position = $next;
        }
        if ($target === null) return;
        $valueNode = null;
        for ($i = $target + 1; $i < $nodes->length; $i++) {
            if (trim($nodes->item($i)->textContent) !== '') { $valueNode = $nodes->item($i); break; }
        }
        if ($valueNode !== null) {
            $valueNode->nodeValue = $value;
            $clear = false;
            for ($i = $target + 1; $i < $nodes->length; $i++) {
                if ($nodes->item($i) === $valueNode) { $clear = true; continue; }
                if ($clear) $nodes->item($i)->nodeValue = '';
            }
            return;
        }
        $runs = $xpath->query('.//*[local-name()="r"]', $paragraph);
        if ($runs->length === 0) return;
        $text = $paragraph->ownerDocument->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $text->appendChild($paragraph->ownerDocument->createTextNode($value));
        $runs->item($runs->length - 1)->appendChild($text);
    }

    private function replaceRoleTables(\DOMXPath $xpath, array $values): void
    {
        $people = array_merge($values['__supervisors'] ?? [], $values['__examiners'] ?? []);
        foreach ($xpath->query('//*[local-name()="tbl"]') as $table) {
            $index = 0;
            foreach ($xpath->query('./*[local-name()="tr"]', $table) as $row) {
                $cells = $xpath->query('./*[local-name()="tc"]', $row);
                if ($cells->length < 3) continue;
                $number = preg_replace('/\s+/u', '', trim($cells->item(0)->textContent));
                if (!preg_match('/^\d+\.?$/', $number)) continue;
                // Always replace the name cell. Uploaded templates often contain
                // sample names; leaving non-empty cells untouched causes the same
                // sample lecturer to appear repeatedly in the generated BA.
                $this->setCellText($xpath, $cells->item(1), (string)($people[$index] ?? ''));
                $index++;
            }
            foreach ($xpath->query('./*[local-name()="tr"]', $table) as $row) {
                $label = preg_replace('/\s+/u', ' ', trim($row->textContent));
                $cells = $xpath->query('./*[local-name()="tc"]', $row);
                if ($cells->length < 2) continue;
                $value = null;
                if (stripos($label, 'Hari / Tanggal') !== false) $value = (string)($values['Hari / Tanggal'] ?? '');
                elseif (stripos($label, 'Waktu Pelaksanaan') !== false) $value = (string)($values['waktu_pelaksanaan'] ?? '');
                elseif (stripos($label, 'Lokasi') !== false) $value = (string)($values['Lokasi'] ?? '');
                if ($value !== null) $this->setCellText($xpath, $cells->item($cells->length - 1), $value);
            }
        }
        $rows = $xpath->query('//*[local-name()="tr"]');
        $examinerMember = 1;
        foreach ($rows as $row) {
            $text = preg_replace('/\s+/u', ' ', trim($row->textContent));
            $role = null;
            $position = 0;
            if (stripos($text, 'Pembimbing Utama') !== false) { $role = 'Pembimbing'; $position = 0; }
            elseif (stripos($text, 'Pembimbing Pendamping') !== false) { $role = 'Pembimbing'; $position = 1; }
            elseif (stripos($text, 'Penguji Ketua') !== false) { $role = 'Penguji'; $position = 0; }
            elseif (stripos($text, 'Penguji Anggota') !== false) { $role = 'Penguji'; $position = $examinerMember++; }
            elseif (stripos($text, 'Pembimbing') !== false) $role = 'Pembimbing';
            elseif (stripos($text, 'Penguji') !== false) $role = 'Penguji';
            if ($role === null) continue;
            $list = $role === 'Pembimbing' ? ($values['__supervisors'] ?? []) : ($values['__examiners'] ?? []);
            if ($list === []) continue;
            if ($role === 'Pembimbing' && !isset($list[$position])) $position = 0;
            if ($role === 'Penguji' && !isset($list[$position])) $position = 0;
            $cells = $xpath->query('./*[local-name()="tc"]', $row);
            if ($cells->length < 2) continue;
            $this->setCellText($xpath, $cells->item(1), (string)($list[$position] ?? $list[0]));
        }
    }

    private function setCellText(\DOMXPath $xpath, \DOMNode $cell, string $value): void
    {
        $nodes = $xpath->query('.//*[local-name()="t"]', $cell);
        if ($nodes->length) {
            $nodes->item(0)->nodeValue = $value;
            for ($i = 1; $i < $nodes->length; $i++) $nodes->item($i)->nodeValue = '';
            return;
        }
        $paragraph = $xpath->query('.//*[local-name()="p"]', $cell)->item(0);
        if (!$paragraph) return;
        $doc = $paragraph->ownerDocument;
        $run = $doc->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        $rPr = $doc->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:rPr');
        $fonts = $doc->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:rFonts');
        $fonts->setAttribute('w:ascii', 'Times New Roman');
        $fonts->setAttribute('w:hAnsi', 'Times New Roman');
        $fonts->setAttribute('w:eastAsia', 'Times New Roman');
        $rPr->appendChild($fonts);
        $run->appendChild($rPr);
        $text = $doc->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $text->appendChild($doc->createTextNode($value));
        $run->appendChild($text);
        $paragraph->appendChild($run);
    }
}
