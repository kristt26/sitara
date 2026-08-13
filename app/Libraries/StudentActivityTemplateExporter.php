<?php

namespace App\Libraries;

final class StudentActivityTemplateExporter
{
    public function export(array $students, array $types, array $paths, array $lecturers, array $rules): string
    {
        $writer = new XlsxTemplateWriter(FCPATH . 'templates/template-import-kegiatan-mahasiswa.xlsx', 'activity-template-');
        try {
            $writer->replaceRowsAfter('Daftar Mahasiswa', 2, array_map(fn($x) => [$x['nim'], $x['full_name'], $x['program_code'], $x['program_name']], $students), 3);
            $writer->replaceRowsAfter('Jenis Kegiatan', 2, array_map(fn($x) => [$x['code'], $x['name']], $types), 3);
            $writer->replaceRowsAfter('Jalur Ujian', 2, array_map(fn($x) => [$x['code'], $x['name']], $paths), 3);
            $writer->replaceRowsAfter('Daftar Dosen', 2, array_map(fn($x) => [$x['identifier'], $x['nidn'], $x['nip'], $x['full_name']], $lecturers), 3);
            $writer->replaceRowsAfter('Aturan Kegiatan', 2, array_map(fn($x) => [$x['program_code'], $x['activity_code'], (int)$x['min_supervisors'], (int)$x['max_supervisors'], (int)$x['min_examiners'], (int)$x['max_examiners']], $rules), 3);
        } finally { $writer->close(); }
        return $writer->path();
    }
}
