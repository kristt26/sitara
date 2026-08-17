<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

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
            ->select('pay.payment_no,pay.payment_date,pay.amount,pay.reference_no,pay.status,pm.name payment_method_name')
            ->join('payment_methods pm', 'pm.id=pay.payment_method_id')
            ->where('pay.student_id', $studentId)->orderBy('pay.payment_date', 'DESC')->get()->getResultArray();

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
            'summary' => [
                'activity_count' => count($activities),
                'unpaid_count' => $unpaidCount,
                'unpaid_total' => $unpaidTotal,
                'pending_payment_count' => count(array_filter($payments, static fn (array $row): bool => $row['status'] === 'MENUNGGU')),
            ],
        ]);
    }
}
