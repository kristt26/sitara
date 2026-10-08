<?php
$rupiah = static fn (float|int|string $amount): string => 'Rp ' . number_format((float) $amount, 0, ',', '.');
$date = static fn (?string $value, bool $time = false): string => $value ? date($time ? 'd M Y, H:i' : 'd M Y', strtotime($value)) : '—';
$statusTone = static fn (string $status): string => in_array($status, ['LUNAS', 'DITERIMA', 'SELESAI'], true) ? 'sitara-status' : 'sitara-status sitara-status-muted';
?>
<?= $this->extend('layouts/student') ?>
<?= $this->section('styles') ?>
<style>
.student-portal-body{background:#f4f7fb;display:flex;flex-direction:column;min-height:100vh}.student-portal-container{max-width:1440px}.student-portal-header{background:linear-gradient(120deg,#0b4f8a,#1769aa);box-shadow:0 .25rem 1.25rem rgba(11,79,138,.18);padding:.9rem 1.25rem}.student-portal-header .sitara-brand{color:#fff}.student-portal-role{color:#c9e3f8}.student-portal-main{flex:1;padding:1.5rem 1.25rem 2.5rem}.student-portal-footer{background:#fff;border-top:1px solid #e5eaf0;color:#66788a;font-size:.78rem;padding:1rem 1.25rem}.student-welcome{background:linear-gradient(135deg,#fff,#eef6fd);border:1px solid #dce9f7;border-radius:1rem;padding:1.5rem}.student-profile-grid{display:grid;gap:.8rem;grid-template-columns:repeat(2,minmax(0,1fr))}.student-profile-item{background:#f8fafc;border-radius:.65rem;padding:.8rem}.student-profile-item small{color:#718096;display:block;margin-bottom:.2rem}.student-profile-item strong{color:#243b53}.student-summary{border:0;border-radius:.85rem;box-shadow:0 .3rem 1rem rgba(31,58,95,.07);height:100%}.student-summary-label{color:#627d98;font-size:.75rem;font-weight:700;text-transform:uppercase}.student-summary-value{color:#102a43;font-size:1.45rem;font-weight:800;margin-top:.35rem}.student-section-card{border:0;border-radius:.9rem;box-shadow:0 .3rem 1rem rgba(31,58,95,.07)}@media(max-width:575.98px){.student-portal-header,.student-portal-main,.student-portal-footer{padding-left:1rem;padding-right:1rem}.student-profile-grid{grid-template-columns:1fr}.student-welcome{padding:1.1rem}}
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<section class="student-welcome mb-4">
    <div class="row g-4 align-items-center">
        <div class="col-12 col-lg-7"><span class="badge bg-primary mb-2">Portal mahasiswa</span><h1 class="h3 mb-2">Halo, <?= esc($student['full_name']) ?>.</h1><p class="text-gray-600 mb-0">Pantau kegiatan akademik, tagihan, dan status pembayaran Anda dari satu halaman.</p></div>
        <div class="col-12 col-lg-5"><div class="student-profile-grid"><div class="student-profile-item"><small>NIM</small><strong><?= esc($student['nim']) ?></strong></div><div class="student-profile-item"><small>Program studi</small><strong><?= esc($student['degree_level'] . ' ' . $student['program_name']) ?></strong></div><div class="student-profile-item"><small>Angkatan</small><strong><?= esc((string) ($student['cohort_year'] ?: '—')) ?></strong></div><div class="student-profile-item"><small>Status</small><strong><?= esc($student['status']) ?></strong></div></div></div>
    </div>
</section>

<section class="row g-3 mb-4" aria-label="Ringkasan mahasiswa">
    <div class="col-6 col-xl-3"><article class="card student-summary"><div class="card-body"><div class="student-summary-label">Kegiatan</div><div class="student-summary-value"><?= esc((string) $summary['activity_count']) ?></div><small class="text-gray-600">Seluruh percobaan</small></div></article></div>
    <div class="col-6 col-xl-3"><article class="card student-summary"><div class="card-body"><div class="student-summary-label">Tagihan aktif</div><div class="student-summary-value"><?= esc((string) $summary['unpaid_count']) ?></div><small class="text-gray-600">Belum lunas</small></div></article></div>
    <div class="col-12 col-sm-6 col-xl-3"><article class="card student-summary"><div class="card-body"><div class="student-summary-label">Sisa tagihan</div><div class="student-summary-value"><?= esc($rupiah($summary['unpaid_total'])) ?></div><small class="text-gray-600">Setelah pembayaran diterima</small></div></article></div>
    <div class="col-12 col-sm-6 col-xl-3"><article class="card student-summary"><div class="card-body"><div class="student-summary-label">Menunggu verifikasi</div><div class="student-summary-value"><?= esc((string) $summary['pending_payment_count']) ?></div><small class="text-gray-600">Catatan pembayaran</small></div></article></div>
</section>

<section class="card student-section-card mb-4" id="upload-pembayaran">
    <header class="card-header"><h2 class="sitara-card-title">Kirim bukti pembayaran</h2><p class="sitara-card-subtitle">Pilih tagihan, isi nominal pembayaran, lalu unggah bukti untuk diverifikasi admin.</p></header>
    <form action="<?= site_url('portal-mahasiswa/pembayaran') ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="card-body"><div class="row g-3">
            <div class="col-12 col-lg-6"><label class="form-label" for="student_payment_bill">Tagihan <span class="text-danger">*</span></label><select class="form-select" id="student_payment_bill" name="student_bill_id" required><option value="">Pilih tagihan</option><?php foreach ($bills as $bill): ?><?php if ($bill['status'] !== 'LUNAS'): ?><option value="<?= esc((string) $bill['id']) ?>" <?= old('student_bill_id') === (string) $bill['id'] ? 'selected' : '' ?>><?= esc($bill['bill_no'] . ' — ' . $bill['activity_name'] . ' (sisa ' . $rupiah(max(0, (float) $bill['total_amount'] - (float) $bill['paid_amount'])) . ')') ?></option><?php endif; ?><?php endforeach; ?></select></div>
            <div class="col-12 col-lg-6"><label class="form-label" for="student_payment_method">Metode pembayaran <span class="text-danger">*</span></label><select class="form-select" id="student_payment_method" name="payment_method_id" required><option value="">Pilih metode</option><?php foreach ($paymentMethods as $method): ?><option value="<?= esc((string) $method['id']) ?>" <?= old('payment_method_id') === (string) $method['id'] ? 'selected' : '' ?>><?= esc($method['code'] . ' — ' . $method['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-6"><label class="form-label" for="student_payment_amount">Nominal dibayar <span class="text-danger">*</span></label><input class="form-control" id="student_payment_amount" name="amount" type="number" min="1" step="0.01" value="<?= esc(old('amount')) ?>" required></div>
            <div class="col-12 col-md-6"><label class="form-label" for="student_payment_reference">Nomor referensi</label><input class="form-control" id="student_payment_reference" name="reference_no" maxlength="100" value="<?= esc(old('reference_no')) ?>"><small class="text-gray-600">Kosongkan jika pembayaran tunai.</small></div>
            <div class="col-12"><label class="form-label" for="student_payment_proof">Bukti pembayaran <span class="text-danger">*</span></label><input class="form-control" id="student_payment_proof" name="proof_file" type="file" accept=".pdf,.jpg,.jpeg,.png" required><small class="text-gray-600">Format PDF, JPG, atau PNG. Maksimal 5 MB.</small></div>
            <div class="col-12"><label class="form-label" for="student_payment_notes">Catatan</label><textarea class="form-control" id="student_payment_notes" name="notes" rows="2" maxlength="1000"><?= esc(old('notes')) ?></textarea></div>
        </div></div>
        <div class="card-footer bg-white text-end"><button class="btn btn-primary" type="submit">Kirim untuk verifikasi</button></div>
    </form>
</section>

<section class="card student-section-card mb-4">
    <header class="card-header"><h2 class="sitara-card-title">Kegiatan akademik</h2><p class="sitara-card-subtitle">Jadwal dan status kegiatan yang terdaftar atas nama Anda.</p></header>
    <div class="table-responsive"><table class="table sitara-table mb-0"><thead><tr><th>Kegiatan</th><th>Periode</th><th>Jalur</th><th>Jadwal</th><th>Status</th></tr></thead><tbody>
    <?php if ($activities === []): ?><tr><td colspan="5" class="sitara-empty">Belum ada kegiatan akademik.</td></tr><?php else: foreach ($activities as $row): ?><tr><td><strong><?= esc($row['activity_name']) ?></strong><small class="d-block text-gray-600"><?= esc($row['activity_no']) ?> · Percobaan <?= esc((string) $row['attempt_no']) ?></small></td><td><?= esc($row['academic_year_code'] . ' / ' . $row['semester_code']) ?></td><td><?= esc($row['exam_path_name']) ?></td><td><?= esc($date($row['scheduled_at'], true)) ?></td><td><span class="<?= esc($statusTone($row['status'])) ?>"><?= esc($row['status']) ?></span></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<section class="card student-section-card mb-4">
    <header class="card-header"><h2 class="sitara-card-title">Tagihan</h2><p class="sitara-card-subtitle">Nominal tagihan dan pembayaran yang telah diterima.</p></header>
    <div class="table-responsive"><table class="table sitara-table mb-0"><thead><tr><th>Nomor</th><th>Kegiatan</th><th>Jatuh tempo</th><th class="text-end">Total</th><th class="text-end">Terbayar</th><th>Status</th></tr></thead><tbody>
    <?php if ($bills === []): ?><tr><td colspan="6" class="sitara-empty">Belum ada tagihan.</td></tr><?php else: foreach ($bills as $row): ?><tr><td><strong><?= esc($row['bill_no']) ?></strong><small class="d-block text-gray-600"><?= esc($row['academic_year_code'] . ' / ' . $row['semester_code']) ?></small></td><td><?= esc($row['activity_name']) ?><small class="d-block text-gray-600"><?= esc($row['exam_path_name']) ?></small></td><td><?= esc($date($row['due_date'])) ?></td><td class="text-end fw-bold"><?= esc($rupiah($row['total_amount'])) ?></td><td class="text-end"><?= esc($rupiah($row['paid_amount'])) ?></td><td><span class="<?= esc($statusTone($row['status'])) ?>"><?= esc($row['status']) ?></span></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<section class="card student-section-card">
    <header class="card-header"><h2 class="sitara-card-title">Riwayat pembayaran</h2><p class="sitara-card-subtitle">Status pencatatan dan verifikasi pembayaran Anda.</p></header>
    <div class="table-responsive"><table class="table sitara-table mb-0"><thead><tr><th>Nomor pembayaran</th><th>Tanggal</th><th>Metode</th><th>Referensi</th><th class="text-end">Nominal</th><th>Status</th></tr></thead><tbody>
    <?php if ($payments === []): ?><tr><td colspan="6" class="sitara-empty">Belum ada riwayat pembayaran.</td></tr><?php else: foreach ($payments as $row): ?><tr><td><strong><?= esc($row['payment_no']) ?></strong></td><td><?= esc($date($row['payment_date'], true)) ?></td><td><?= esc($row['payment_method_name']) ?></td><td><?= esc($row['reference_no'] ?: '—') ?></td><td class="text-end fw-bold"><?= esc($rupiah($row['amount'])) ?></td><td><span class="<?= esc($statusTone($row['status'])) ?>"><?= esc($row['status']) ?></span></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>
<?= $this->endSection() ?>
