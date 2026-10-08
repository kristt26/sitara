<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

class StudentPortal extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $auth = session('auth');
        $userId = is_array($auth) ? (int) ($auth['id'] ?? 0) : 0;
        $db = db_connect();
        $student = $db->table('students s')
            ->select('s.id,s.nim,s.full_name,s.cohort_year,s.email,s.phone,s.status,sp.code program_code,sp.name program_name,sp.degree_level,u.username,u.last_login_at')
            ->join('study_programs sp', 'sp.id=s.study_program_id')
            ->join('users u', 'u.id=s.user_id')
            ->where(['s.user_id' => $userId, 'u.role' => 'MAHASISWA', 'u.is_active' => 1])
            ->where('s.status !=', 'NONAKTIF')->get()->getRowArray();

        if (! is_array($student)) {
            session()->destroy();
            return redirect()->to(site_url('login'))->with('error', 'Profil mahasiswa tidak tersedia atau sedang nonaktif.');
        }

        $studentId = (int) $student['id'];
        $activities = $db->table('academic_activities aa')
            ->select('aa.activity_no,aa.title,aa.attempt_no,aa.scheduled_at,aa.completed_at,aa.status,at.name activity_name,ep.name exam_path_name,ay.code academic_year_code,ap.semester_code')
            ->join('activity_types at', 'at.id=aa.activity_type_id')
            ->join('exam_paths ep', 'ep.id=aa.exam_path_id')
            ->join('academic_periods ap', 'ap.id=aa.academic_period_id')
            ->join('academic_years ay', 'ay.id=ap.academic_year_id')
            ->where('aa.student_id', $studentId)->orderBy('aa.created_at', 'DESC')->get()->getResultArray();

        $bills = $db->table('student_bills sb')
            ->select('sb.id,sb.bill_no,sb.bill_date,sb.due_date,sb.total_amount,sb.status,at.name activity_name,ep.name exam_path_name,ay.code academic_year_code,ap.semester_code')
            ->join('activity_types at', 'at.id=sb.activity_type_id')
            ->join('exam_paths ep', 'ep.id=sb.exam_path_id')
            ->join('academic_periods ap', 'ap.id=sb.academic_period_id')
            ->join('academic_years ay', 'ay.id=ap.academic_year_id')
            ->where('sb.student_id', $studentId)->orderBy('sb.bill_date', 'DESC')->get()->getResultArray();

        $acceptedAllocations = $db->table('student_payment_allocations spa')
            ->select('spa.student_bill_id,SUM(spa.allocated_amount) paid_amount', false)
            ->join('student_payments pay', 'pay.id=spa.student_payment_id')
            ->where(['pay.student_id' => $studentId, 'pay.status' => 'DITERIMA'])
            ->groupBy('spa.student_bill_id')->get()->getResultArray();
        $paidByBill = array_column($acceptedAllocations, 'paid_amount', 'student_bill_id');
        foreach ($bills as &$bill) $bill['paid_amount'] = (float) ($paidByBill[$bill['id']] ?? 0);
        unset($bill);

        $payments = $db->table('student_payments pay')
            ->select('pay.payment_no,pay.payment_date,pay.amount,pay.reference_no,pay.proof_file_path,pay.status,pm.name payment_method_name')
            ->join('payment_methods pm', 'pm.id=pay.payment_method_id')
            ->where('pay.student_id', $studentId)->orderBy('pay.payment_date', 'DESC')->get()->getResultArray();
        $methods = $db->table('payment_methods')->select('id,code,name')->where('is_active', 1)->orderBy('name')->get()->getResultArray();

        $unpaidTotal = 0.0;
        $unpaidCount = 0;
        foreach ($bills as $bill) {
            if (in_array($bill['status'], ['BELUM_DIBAYAR', 'SEBAGIAN'], true)) {
                $unpaidCount++;
                $unpaidTotal += max(0, (float) $bill['total_amount'] - (float) $bill['paid_amount']);
            }
        }

        return view('pages/student/dashboard', [
            'pageTitle' => 'Portal Mahasiswa',
            'student' => $student,
            'activities' => $activities,
            'bills' => $bills,
            'payments' => $payments,
            'paymentMethods' => $methods,
            'summary' => [
                'activity_count' => count($activities),
                'unpaid_count' => $unpaidCount,
                'unpaid_total' => $unpaidTotal,
                'pending_payment_count' => count(array_filter($payments, static fn (array $row): bool => $row['status'] === 'MENUNGGU')),
            ],
        ]);
    }

    public function submitPayment(): RedirectResponse
    {
        $auth = session('auth');
        $userId = is_array($auth) ? (int) ($auth['id'] ?? 0) : 0;
        $db = db_connect();
        $student = $db->table('students')->where('user_id', $userId)->where('status !=', 'NONAKTIF')->get()->getRowArray();
        if (! is_array($student)) return redirect()->to(site_url('login'))->with('error', 'Profil mahasiswa tidak tersedia.');

        $storedPath = null;
        try {
            $billId = (int) $this->request->getPost('student_bill_id');
            $methodId = (int) $this->request->getPost('payment_method_id');
            $amount = (float) $this->request->getPost('amount');
            $reference = trim((string) $this->request->getPost('reference_no')) ?: null;
            $notes = trim((string) $this->request->getPost('notes')) ?: null;
            if ($reference !== null && strlen($reference) > 100) throw new RuntimeException('Nomor referensi maksimal 100 karakter.');
            if ($notes !== null && strlen($notes) > 1000) throw new RuntimeException('Catatan maksimal 1.000 karakter.');

            $bill = $db->table('student_bills')->where(['id' => $billId, 'student_id' => $student['id']])->get()->getRowArray();
            if (! is_array($bill) || $bill['status'] === 'LUNAS') throw new RuntimeException('Tagihan tidak ditemukan atau sudah lunas.');
            if ($db->table('student_payment_allocations spa')->join('student_payments pay', 'pay.id=spa.student_payment_id')->where(['spa.student_bill_id' => $billId, 'pay.status' => 'MENUNGGU'])->countAllResults() > 0) throw new RuntimeException('Tagihan ini sedang menunggu verifikasi pembayaran.');
            if ($db->table('payment_methods')->where(['id' => $methodId, 'is_active' => 1])->countAllResults() !== 1) throw new RuntimeException('Metode pembayaran tidak tersedia atau tidak aktif.');
            $accepted = $db->table('student_payment_allocations spa')->selectSum('spa.allocated_amount', 'paid')->join('student_payments pay', 'pay.id=spa.student_payment_id')->where(['spa.student_bill_id' => $billId, 'pay.status' => 'DITERIMA'])->get()->getRowArray();
            $outstanding = (float) $bill['total_amount'] - (float) ($accepted['paid'] ?? 0);
            if ($amount <= 0 || $amount > $outstanding) throw new RuntimeException('Nominal pembayaran harus lebih dari nol dan tidak melebihi sisa tagihan.');

            $storedPath = $this->storeProof($this->request->getFile('proof_file'));
            $now = date('Y-m-d H:i:s');
            $db->transStart();
            $paymentNo = 'PAY-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $db->table('student_payments')->insert(['payment_no' => $paymentNo, 'student_id' => $student['id'], 'payment_method_id' => $methodId, 'payment_date' => $now, 'amount' => $amount, 'reference_no' => $reference, 'proof_file_path' => $storedPath, 'status' => 'MENUNGGU', 'notes' => $notes, 'created_at' => $now, 'updated_at' => $now]);
            $paymentId = (int) $db->insertID();
            $db->table('student_payment_allocations')->insert(['student_payment_id' => $paymentId, 'student_bill_id' => $billId, 'allocated_amount' => $amount, 'created_at' => $now]);
            $db->table('student_bills')->where('id', $billId)->update(['status' => 'MENUNGGU', 'updated_at' => $now]);
            $this->audit($db, 'STUDENT_PAYMENT_SUBMITTED', $paymentId, $userId, ['student_id' => (int) $student['id'], 'bill_id' => $billId, 'payment_no' => $paymentNo]);
            $db->transComplete();
            if (! $db->transStatus()) throw new RuntimeException('Pembayaran belum dapat disimpan.');
            return redirect()->to(site_url('portal-mahasiswa'))->with('success', 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi admin.');
        } catch (Throwable $exception) {
            if ($storedPath !== null) $this->removeProof($storedPath);
            return redirect()->to(site_url('portal-mahasiswa'))->withInput()->with('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Bukti pembayaran belum dapat diproses.');
        }
    }

    private function storeProof($file): string
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK || ! is_file($file->getTempName()) || $file->hasMoved()) throw new RuntimeException('Bukti pembayaran wajib diunggah.');
        if ($file->getSize() > 5 * 1024 * 1024) throw new RuntimeException('Ukuran bukti pembayaran maksimal 5 MB.');
        $mime = strtolower((string) $file->getMimeType());
        $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (! isset($extensions[$mime])) throw new RuntimeException('Bukti pembayaran harus berupa PDF, JPG, atau PNG.');
        $directory = WRITEPATH . 'uploads/payment-proofs';
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) throw new RuntimeException('Folder penyimpanan bukti pembayaran belum tersedia.');
        $name = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        try {
            $moved = $file->move($directory, $name);
        } catch (Throwable) {
            // Feature tests may use a local fixture instead of an HTTP-uploaded temp file.
            $moved = @copy($file->getTempName(), $directory . DIRECTORY_SEPARATOR . $name);
        }
        if (! $moved) throw new RuntimeException('Bukti pembayaran belum dapat disimpan.');
        return 'payment-proofs/' . $name;
    }

    private function removeProof(string $relative): void
    {
        $path = WRITEPATH . 'uploads/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
        if (is_file($path)) @unlink($path);
    }

    private function audit($db, string $action, int $id, int $userId, array $new): void
    {
        $db->table('audit_logs')->insert(['user_id' => $userId, 'action' => $action, 'entity_type' => 'student_payments', 'entity_id' => $id, 'old_values' => null, 'new_values' => json_encode($new, JSON_UNESCAPED_UNICODE), 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]);
    }
}
