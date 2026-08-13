<?php

namespace App\Libraries;

final class HonorBatchExporter
{
    public function export(array $batch, array $details): string
    {
        $writer = new XlsxTemplateWriter(FCPATH . 'templates/template-export-batch-honor.xlsx', 'honor-batch-');
        try {
            $summary = [
                ['Nomor Batch', $batch['batch_no']], ['Periode', $batch['academic_year_code'] . ' - ' . $batch['semester_code']],
                ['Status', $batch['status']], ['Tanggal Pembayaran', $batch['payment_date'] ?: '-'], ['Nomor Referensi', $batch['reference_no'] ?: '-'],
                ['Jumlah Dosen', (int)$batch['lecturer_count']], ['Total Bersih', (float)$batch['net_total']],
            ];
            $writer->replaceRowsAfter('Ringkasan Batch', 3, $summary, 4, ['B10' => 9]);
            $rows = [];
            foreach ($details as $index => $x) $rows[] = [$index + 1,$x['full_name'],$x['nidn'],$x['nip'],$x['bank_name_snapshot'],$x['bank_account_number_snapshot'],$x['bank_account_name_snapshot'],$x['activity_no'],$x['activity_name'],$x['role_type'],(int)$x['position_no'],(float)$x['gross_amount'],(float)$x['tax_amount'],(float)$x['net_amount'],$x['payment_status']];
            $writer->replaceRowsAfter('Detail Honor', 3, $rows, 4, [10 => 10, 11 => 9, 12 => 9, 13 => 9]);

            $grouped = [];
            foreach ($details as $detail) $grouped[(int)$detail['lecturer_id']][] = $detail;
            $recapRows = [];
            foreach ($grouped as $lecturerRows) {
                if ($recapRows !== []) $recapRows[] = [];
                $recapRows[] = ['values' => ['Nama Dosen: ' . $lecturerRows[0]['full_name'], '', '', ''], 'styles' => [16,17,17,18]];
                $recapRows[] = ['values' => ['Nomor','Nama Mahasiswa','Peran','Nominal Honor'], 'styles' => [21,21,21,21]];
                foreach ($lecturerRows as $number => $detail) {
                    $role = ucfirst(strtolower($detail['role_type']));
                    if ((int)$detail['role_count'] > 1) $role .= ' ' . (int)$detail['position_no'];
                    $recapRows[] = ['values' => [$number + 1,$detail['student_name'],$role,(float)$detail['net_amount']], 'styles' => [null,null,null,9]];
                }
            }
            $writer->replaceRowsAfter('Rekap per Dosen', 1, $recapRows, 3);
        } finally { $writer->close(); }
        return $writer->path();
    }
}
