<?php

namespace App\Controllers;

use App\Libraries\FinancialService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class Home extends BaseController
{
    private const PAGES = [
        'dashboard' => ['view' => 'pages/dashboard', 'title' => 'Dashboard', 'subtitle' => 'Dashboard operasional keuangan'],
        'periods' => ['view' => 'pages/academic/periods', 'title' => 'Tahun & Periode', 'subtitle' => 'Pengaturan tahun akademik dan semester'],
        'programs' => ['view' => 'pages/academic/programs', 'title' => 'Program Studi', 'subtitle' => 'Master program studi aktif'],
        'activities' => ['view' => 'pages/academic/activities', 'title' => 'Kegiatan Akademik', 'subtitle' => 'Jenis kegiatan yang dapat ditagihkan'],
        'students' => [
            'view' => 'pages/admin/module',
            'title' => 'Mahasiswa',
            'subtitle' => 'Master data mahasiswa',
            'description' => 'Admin mencatat identitas, program studi, status, dan akun mahasiswa untuk akses portal pada tahap berikutnya.',
            'fields' => ['NIM', 'Nama lengkap', 'Program studi', 'Angkatan', 'Email dan nomor telepon', 'Status mahasiswa'],
        ],
        'lecturers' => [
            'view' => 'pages/admin/module',
            'title' => 'Dosen',
            'subtitle' => 'Master data dosen',
            'description' => 'Data dosen digunakan untuk penugasan kegiatan akademik dan pembayaran honor.',
            'fields' => ['NIDN atau NIP', 'Nama lengkap', 'Kontak', 'Data rekening', 'NPWP', 'Status aktif'],
        ],
        'activity-rules' => [
            'view' => 'pages/admin/module',
            'title' => 'Aturan Kegiatan',
            'subtitle' => 'Aturan penugasan kegiatan akademik',
            'description' => 'Tentukan jumlah pembimbing dan penguji untuk setiap kombinasi periode, program studi, dan jenis kegiatan.',
            'fields' => ['Periode akademik', 'Program studi', 'Jenis kegiatan', 'Batas pembimbing', 'Batas penguji', 'Status aturan'],
        ],
        'payment-methods' => [
            'view' => 'pages/admin/module',
            'title' => 'Metode Pembayaran',
            'subtitle' => 'Master metode penerimaan pembayaran',
            'description' => 'Metode ini akan dipilih mahasiswa saat mengunggah pembayaran dan digunakan admin ketika melakukan verifikasi.',
            'fields' => ['Kode metode', 'Nama metode', 'Status aktif'],
        ],
        'student-activities' => [
            'view' => 'pages/admin/module',
            'title' => 'Kegiatan Mahasiswa',
            'subtitle' => 'Pencatatan kegiatan yang dapat ditagihkan',
            'description' => 'Admin membuat kegiatan mahasiswa sebelum tagihan diterbitkan. Kegiatan menjadi penghubung antara mahasiswa, tarif, penugasan dosen, dan tagihan.',
            'fields' => ['Mahasiswa', 'Periode akademik', 'Program studi', 'Jenis kegiatan', 'Percobaan ke-', 'Jadwal dan status'],
        ],
        'fees' => ['view' => 'pages/finance/fees', 'title' => 'Tarif & Komponen', 'subtitle' => 'Tarif kegiatan dan komponen tagihan'],
        'bills' => ['view' => 'pages/finance/bills', 'title' => 'Tagihan Mahasiswa', 'subtitle' => 'Pembuatan dan pemantauan tagihan'],
        'verification' => ['view' => 'pages/finance/verification', 'title' => 'Verifikasi Pembayaran', 'subtitle' => 'Pemeriksaan pembayaran mahasiswa'],
        'honor' => ['view' => 'pages/honor', 'title' => 'Honor Dosen', 'subtitle' => 'Hak honor dan batch pembayaran dosen'],
        'honor-rates' => [
            'view' => 'pages/admin/module',
            'title' => 'Tarif Honor',
            'subtitle' => 'Pengaturan tarif honor dosen',
            'description' => 'Admin menentukan nominal bruto dan tarif pajak berdasarkan periode, peran dosen, posisi, dan jenis kegiatan.',
            'fields' => ['Periode akademik', 'Program studi', 'Jenis kegiatan', 'Peran dan posisi', 'Nominal bruto', 'Tarif pajak'],
        ],
        'honor-entitlements' => [
            'view' => 'pages/admin/module',
            'title' => 'Hak Honor',
            'subtitle' => 'Validasi hak honor dari penugasan dosen',
            'description' => 'Hak honor terbentuk dari penugasan dosen yang aktif dan menyimpan snapshot tarif ketika disetujui.',
            'fields' => ['Penugasan dosen', 'Tarif honor', 'Nominal bruto', 'Pajak', 'Nominal bersih', 'Status pengajuan'],
        ],
        'honor-payments' => [
            'view' => 'pages/admin/module',
            'title' => 'Batch Pembayaran Honor',
            'subtitle' => 'Penyusunan dan pencairan honor dosen',
            'description' => 'Admin mengelompokkan hak honor yang telah disetujui ke dalam batch pembayaran per periode.',
            'fields' => ['Nomor batch', 'Periode akademik', 'Tanggal pembayaran', 'Referensi pembayaran', 'Bukti pembayaran', 'Status batch'],
        ],
        'admin-users' => [
            'view' => 'pages/admin/module',
            'title' => 'Pengguna Admin',
            'subtitle' => 'Akun admin dan akses operasional',
            'description' => 'Pada fase ini hanya akun berperan ADMIN yang dikelola dari menu ini. Akun MAHASISWA nanti ditautkan satu-ke-satu dari data mahasiswa.',
            'fields' => ['Username', 'Nama lengkap', 'Email', 'Kata sandi', 'Peran ADMIN', 'Status akun'],
        ],
        'audit' => ['view' => 'pages/audit', 'title' => 'Audit Log', 'subtitle' => 'Riwayat perubahan transaksi dan konfigurasi'],
    ];

    public function index(): string
    {
        $auth = session('auth');
        if (is_array($auth) && ($auth['role'] ?? null) === 'PRODI') {
            return $this->prodiDashboard();
        }

        return $this->renderPage('dashboard');
    }

    private function prodiDashboard(): string
    {
        $db = db_connect();
        $auth = session('auth');
        $userId = is_array($auth) ? (int) ($auth['id'] ?? 0) : 0;
        $programIds = $db->tableExists('user_study_programs')
            ? array_map('intval', array_column($db->table('user_study_programs')->select('study_program_id')->where('user_id', $userId)->get()->getResultArray(), 'study_program_id'))
            : [];
        $programIds = $programIds === [] ? [0] : $programIds;
        $period = $db->table('academic_periods ap')->select('ap.id,ap.semester_code,ay.code academic_year_code')->join('academic_years ay','ay.id=ap.academic_year_id')->where(['ap.is_active'=>1,'ay.is_active'=>1])->get()->getRowArray();
        $studentCount = $db->table('students')->whereIn('study_program_id',$programIds)->where('status !=','NONAKTIF')->countAllResults();
        $activityCount = $period ? $db->table('academic_activities')->whereIn('study_program_id',$programIds)->where('academic_period_id',$period['id'])->countAllResults() : 0;
        $ruleCount = $period ? $db->table('activity_rules')->whereIn('study_program_id',$programIds)->where(['academic_period_id'=>$period['id'],'is_active'=>1])->countAllResults() : 0;
        $programs = $db->table('study_programs')->whereIn('id',$programIds)->orderBy('code')->get()->getResultArray();
        return view('pages/dashboard_prodi', ['live'=>true,'activeMenu'=>'dashboard','pageTitle'=>'Dashboard Prodi','pageSubtitle'=>'Ringkasan akademik program studi','period'=>$period,'programs'=>$programs,'studentCount'=>$studentCount,'activityCount'=>$activityCount,'ruleCount'=>$ruleCount]);
    }

    public function page(string $page): string
    {
        if (! isset(self::PAGES[$page])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderPage($page);
    }

    private function renderPage(string $page): string
    {
        $data = [
            'live' => false,
            'academicYears' => [],
            'academicPeriods' => [],
            'programs' => [],
            'activities' => [],
            'feeSettings' => [],
            'stats' => [],
            'auditLogs' => [],
        ];

        try {
            $data = array_merge($data, (new FinancialService())->dashboardData(), ['live' => true]);
        } catch (Throwable) {
            // Tampilan tetap dapat digunakan sebagai pratinjau saat database belum disiapkan.
        }

        $pageDefinition = self::PAGES[$page];
        $data = array_merge($data, $pageDefinition, [
            'activeMenu' => $page,
            'pageTitle' => $pageDefinition['title'],
            'pageSubtitle' => $pageDefinition['subtitle'],
        ]);

        return view($pageDefinition['view'], $data);
    }
}
