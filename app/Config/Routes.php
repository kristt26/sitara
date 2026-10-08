<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt', ['filter' => 'csrf']);
$routes->get('aktivasi-mahasiswa', 'StudentActivation::index');
$routes->post('aktivasi-mahasiswa', 'StudentActivation::activate', ['filter' => 'csrf']);
$routes->post('logout', 'Auth::logout', ['filter' => ['auth', 'csrf']]);

$routes->group('portal-mahasiswa', ['filter' => 'studentauth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'StudentPortal::index');
    $routes->post('pembayaran', 'StudentPortal::submitPayment', ['filter' => 'csrf']);
});

$routes->group('', ['filter' => 'adminauth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'Home::index');
    $routes->group('periode', static function (RouteCollection $routes): void {
        $routes->get('/', 'AcademicYear::index');
        $routes->get('read', 'AcademicYear::read');
        $routes->post('post', 'AcademicYear::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'AcademicYear::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'AcademicYear::activate/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'AcademicYear::delete/$1', ['filter' => 'csrf']);

        $routes->get('(:num)', 'AcademicPeriod::index/$1');
        $routes->get('(:num)/read', 'AcademicPeriod::read/$1');
        $routes->post('(:num)/post', 'AcademicPeriod::create/$1', ['filter' => 'csrf']);
        $routes->put('(:num)/put/(:num)', 'AcademicPeriod::update/$1/$2', ['filter' => 'csrf']);
        $routes->post('(:num)/aktif/(:num)', 'AcademicPeriod::activate/$1/$2', ['filter' => 'csrf']);
        $routes->delete('(:num)/delete/(:num)', 'AcademicPeriod::delete/$1/$2', ['filter' => 'csrf']);
    });

    $routes->group('program-studi', static function (RouteCollection $routes): void {
        $routes->get('/', 'StudyProgram::index');
        $routes->get('read', 'StudyProgram::read');
        $routes->post('post', 'StudyProgram::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'StudyProgram::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'StudyProgram::activate/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'StudyProgram::delete/$1', ['filter' => 'csrf']);
    });

    $routes->group('kegiatan', static function (RouteCollection $routes): void {
        $routes->get('/', 'ActivityType::index');
        $routes->get('read', 'ActivityType::read');
        $routes->post('post', 'ActivityType::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'ActivityType::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'ActivityType::activate/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'ActivityType::delete/$1', ['filter' => 'csrf']);
    });

    $routes->group('aturan-kegiatan', static function (RouteCollection $routes): void {
        $routes->get('/', 'ActivityRule::index');
        $routes->get('read', 'ActivityRule::read');
        $routes->post('copy-previous', 'ActivityRule::copyPrevious', ['filter' => 'csrf']);
        $routes->post('post', 'ActivityRule::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'ActivityRule::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'ActivityRule::activate/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'ActivityRule::delete/$1', ['filter' => 'csrf']);
    });

    $routes->group('keuangan/metode-pembayaran', static function (RouteCollection $routes): void {
        $routes->get('/', 'PaymentMethod::index');
        $routes->get('read', 'PaymentMethod::read');
        $routes->post('post', 'PaymentMethod::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'PaymentMethod::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'PaymentMethod::activate/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'PaymentMethod::delete/$1', ['filter' => 'csrf']);
    });

    $routes->group('keuangan/tarif', static function (RouteCollection $routes): void {
        $routes->get('/', 'FeeSetting::index'); $routes->get('read', 'FeeSetting::read'); $routes->get('detail/(:num)', 'FeeSetting::detail/$1');
        $routes->post('post', 'FeeSetting::store', ['filter'=>'csrf']); $routes->post('nonaktif/(:num)', 'FeeSetting::deactivate/$1', ['filter'=>'csrf']);
    });
    $routes->group('kegiatan-mahasiswa', static function (RouteCollection $routes): void {
        $routes->get('/', 'StudentActivity::index'); $routes->get('read', 'StudentActivity::read'); $routes->get('detail/(:num)', 'StudentActivity::detail/$1'); $routes->get('template', 'StudentActivity::template'); $routes->post('import', 'StudentActivity::import', ['filter'=>'csrf']); $routes->post('post', 'StudentActivity::create', ['filter'=>'csrf']);
        $routes->put('put/(:num)', 'StudentActivity::update/$1', ['filter'=>'csrf']); $routes->delete('delete/(:num)', 'StudentActivity::delete/$1', ['filter'=>'csrf']);
        $routes->get('dokumen/(:num)/(:segment)', 'StudentActivity::document/$1/$2');
        $routes->post('dokumen/tim', 'StudentActivity::documentTeam', ['filter'=>'csrf']);
    });
    $routes->group('template-dokumen', static function (RouteCollection $routes): void {
        $routes->get('/', 'DocumentTemplate::index'); $routes->get('read', 'DocumentTemplate::read'); $routes->get('download/(:num)', 'DocumentTemplate::download/$1');
        $routes->post('upload', 'DocumentTemplate::upload', ['filter'=>'csrf']); $routes->put('fields/(:num)', 'DocumentTemplate::saveFields/$1', ['filter'=>'csrf']);
    });
    $routes->group('format-nomor-surat', static function (RouteCollection $routes): void {
        $routes->get('/', 'LetterNumberFormat::index'); $routes->get('read', 'LetterNumberFormat::read'); $routes->post('save', 'LetterNumberFormat::save', ['filter'=>'csrf']);
    });
    $routes->group('keuangan/tagihan', static function (RouteCollection $routes): void {
        $routes->get('/', 'StudentBill::index'); $routes->get('read', 'StudentBill::read'); $routes->get('preview/(:num)', 'StudentBill::preview/$1'); $routes->get('detail/(:num)', 'StudentBill::detail/$1');
        $routes->post('post', 'StudentBill::create', ['filter'=>'csrf']);
    });
    $routes->group('keuangan/verifikasi', static function (RouteCollection $routes): void {
        $routes->get('/', 'PaymentVerification::index'); $routes->get('read', 'PaymentVerification::read'); $routes->get('bukti/(:num)', 'PaymentVerification::proof/$1'); $routes->post('post', 'PaymentVerification::create', ['filter'=>'csrf']);
        $routes->post('terima/(:num)', 'PaymentVerification::verify/$1', ['filter'=>'csrf']); $routes->post('tolak/(:num)', 'PaymentVerification::reject/$1', ['filter'=>'csrf']);
    });
    $routes->group('honor/tarif', static function (RouteCollection $routes): void {
        $routes->get('/', 'HonorRate::index'); $routes->get('read', 'HonorRate::read'); $routes->post('post', 'HonorRate::store', ['filter'=>'csrf']); $routes->post('nonaktif/(:num)', 'HonorRate::deactivate/$1', ['filter'=>'csrf']);
    });
    $routes->group('honor/hak', static function (RouteCollection $routes): void {
        $routes->get('/', 'HonorEntitlement::index'); $routes->get('read', 'HonorEntitlement::read'); $routes->post('setujui/(:num)', 'HonorEntitlement::approve/$1', ['filter'=>'csrf']);
    });
    $routes->group('honor/pembayaran', static function (RouteCollection $routes): void {
        $routes->get('/', 'HonorPaymentBatch::index'); $routes->get('read', 'HonorPaymentBatch::read'); $routes->get('detail/(:num)', 'HonorPaymentBatch::detail/$1'); $routes->get('export/(:num)', 'HonorPaymentBatch::export/$1'); $routes->post('post', 'HonorPaymentBatch::create', ['filter'=>'csrf']); $routes->post('bayar/(:num)', 'HonorPaymentBatch::pay/$1', ['filter'=>'csrf']);
    });
    $routes->group('pengaturan/admin', static function (RouteCollection $routes): void {
        $routes->get('/', 'AdminUser::index'); $routes->get('read', 'AdminUser::read'); $routes->post('post', 'AdminUser::create', ['filter'=>'csrf']); $routes->put('put/(:num)', 'AdminUser::update/$1', ['filter'=>'csrf']); $routes->post('password/(:num)', 'AdminUser::resetPassword/$1', ['filter'=>'csrf']);
    });
    $routes->group('audit-log', static function (RouteCollection $routes): void {
        $routes->get('/', 'AuditLog::index'); $routes->get('read', 'AuditLog::read'); $routes->get('detail/(:num)', 'AuditLog::detail/$1');
    });

    $routes->group('master/dosen', static function (RouteCollection $routes): void {
        $routes->get('/', 'Lecturer::index');
        $routes->get('read', 'Lecturer::read');
        $routes->post('import', 'Lecturer::import', ['filter' => 'csrf']);
        $routes->post('post', 'Lecturer::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'Lecturer::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'Lecturer::activate/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'Lecturer::delete/$1', ['filter' => 'csrf']);
    });

    $routes->group('master/mahasiswa', static function (RouteCollection $routes): void {
        $routes->get('/', 'Student::index');
        $routes->get('read', 'Student::read');
        $routes->get('template', 'Student::template');
        $routes->post('import', 'Student::import', ['filter' => 'csrf']);
        $routes->post('post', 'Student::create', ['filter' => 'csrf']);
        $routes->put('put/(:num)', 'Student::update/$1', ['filter' => 'csrf']);
        $routes->post('aktif/(:num)', 'Student::activate/$1', ['filter' => 'csrf']);
        $routes->post('aktivasi/(:num)', 'Student::activation/$1', ['filter' => 'csrf']);
        $routes->delete('delete/(:num)', 'Student::delete/$1', ['filter' => 'csrf']);
    });
});
