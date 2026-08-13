<?php

namespace App\Controllers;

use App\Libraries\FinancialService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Finance extends BaseController
{
    public function storeFeeSetting(): ResponseInterface
    {
        try {
            $id = (new FinancialService())->storeFeeSetting($this->payload());
            return $this->response->setStatusCode(201)->setJSON(['ok' => true, 'id' => $id, 'message' => 'Pengaturan tarif berhasil disimpan.']);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function deactivateFeeSetting(int $id): ResponseInterface
    {
        try {
            (new FinancialService())->deactivateFeeSetting($id);
            return $this->response->setJSON(['ok' => true, 'message' => 'Pengaturan tarif dinonaktifkan dan tetap tersimpan dalam riwayat.']);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function copyFeeSetting(int $id): ResponseInterface
    {
        try {
            $newId = (new FinancialService())->copyFeeSetting($id, $this->payload());
            return $this->response->setStatusCode(201)->setJSON(['ok' => true, 'id' => $newId, 'message' => 'Pengaturan tarif berhasil disalin sebagai versi baru.']);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function createBill(): ResponseInterface
    {
        try {
            $payload = $this->payload();
            $activityId = (int) ($payload['activity_id'] ?? 0);
            if ($activityId < 1) {
                throw new \RuntimeException('Kegiatan akademik wajib dipilih.');
            }
            $bill = (new FinancialService())->createBill($activityId, $payload['due_date'] ?? null, $payload);
            return $this->response->setStatusCode(201)->setJSON(['ok' => true, 'data' => $bill, 'message' => 'Tagihan berhasil dibuat dan nominal dikunci.']);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function exportFeeSettings(): ResponseInterface
    {
        try {
            $settings = (new FinancialService())->exportFeeSettings();
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Tahun Akademik', 'Semester', 'Program Studi', 'Jenis Kegiatan', 'Nominal', 'Status'], ';');
            foreach ($settings as $setting) {
                fputcsv($stream, [$setting['year'], $setting['semester'], $setting['program'], $setting['activity'], $setting['amount'], 'Aktif'], ';');
            }
            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="daftar-tarif-sitara.csv"')
                ->setBody($csv);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function payload(): array
    {
        $json = $this->request->getJSON(true);
        return is_array($json) ? $json : $this->request->getPost();
    }

    private function errorResponse(Throwable $exception): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'ok' => false,
            'message' => $exception->getMessage(),
        ]);
    }
}
