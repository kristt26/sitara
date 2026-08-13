<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?>
<style>[ng-cloak]{display:none!important}</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>

<section ng-controller="periodeController" ng-cloak>
    <header class="sitara-page-heading">
        <div>
            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <h1 class="mb-0">Periode akademik</h1>
                <span ng-if="year" ng-class="year.is_active ? 'sitara-status' : 'sitara-status sitara-status-muted'" ng-bind="year.is_active ? 'Tahun aktif' : 'Tahun arsip'"></span>
            </div>
            <p ng-if="year">Tahun akademik <strong ng-bind="year.code"></strong> · <span ng-bind="year.start_year"></span>–<span ng-bind="year.end_year"></span></p>
            <p ng-if="!year">Memuat tahun akademik...</p>
        </div>
        <a href="<?= site_url('periode') ?>" class="btn btn-outline-gray btn-sm">Kembali ke daftar tahun</a>
    </header>

    <div class="alert alert-warning" role="alert" ng-if="year && !year.is_active">
        Tahun akademik ini berstatus arsip. Periode dapat disimpan, tetapi tidak dapat diaktifkan sebelum tahunnya diaktifkan.
    </div>
    <div class="alert alert-danger" role="alert" ng-if="errorMessage" ng-bind="errorMessage"></div>
    <div class="alert alert-success" role="status" ng-if="successMessage" ng-bind="successMessage"></div>

    <div class="row g-3 align-items-start">
        <div class="col-12 col-xl-4">
            <article class="card sitara-card">
                <header class="card-header d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <h2 class="sitara-card-title" ng-bind="model.id ? 'Ubah periode akademik' : 'Tambah periode akademik'"></h2>
                        <p class="sitara-card-subtitle">Semester tersimpan khusus pada tahun akademik ini.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-gray" ng-if="model.id" ng-click="reset()">Batal</button>
                </header>
                <div class="card-body">
                    <form name="periodForm" ng-submit="save(periodForm)" novalidate>
                        <div class="mb-3">
                            <label for="semester_code" class="form-label">Semester</label>
                            <select id="semester_code" name="semester_code" class="form-select" ng-model="model.semester_code" required>
                                <option value="">Pilih semester</option>
                                <option value="GANJIL">GANJIL</option>
                                <option value="GENAP">GENAP</option>
                            </select>
                            <div class="text-danger small mt-1" ng-if="periodForm.$submitted && periodForm.semester_code.$invalid">Semester wajib dipilih.</div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="start_date" class="form-label">Tanggal mulai</label>
                                <input id="start_date" name="start_date" type="date" class="form-control" ng-model="model.start_date">
                            </div>
                            <div class="col-6">
                                <label for="end_date" class="form-label">Tanggal akhir</label>
                                <input id="end_date" name="end_date" type="date" class="form-control" ng-model="model.end_date">
                            </div>
                        </div>
                        <div class="form-check form-switch sitara-active-switch mt-4 mb-4">
                            <input id="period_is_active" name="period_is_active" class="form-check-input" type="checkbox" role="switch" ng-model="model.is_active" ng-disabled="year && !year.is_active">
                            <label class="form-check-label" for="period_is_active">Jadikan periode akademik aktif</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" ng-disabled="saving || loading">
                            <span ng-bind="saving ? 'Menyimpan...' : (model.id ? 'Simpan perubahan' : 'Tambah periode akademik')"></span>
                        </button>
                    </form>
                </div>
            </article>
        </div>

        <div class="col-12 col-xl-8">
            <article class="card sitara-card">
                <header class="card-header">
                    <h2 class="sitara-card-title">Daftar periode</h2>
                    <p class="sitara-card-subtitle">Hanya periode milik tahun akademik yang dipilih yang ditampilkan di sini.</p>
                </header>
                <div class="table-responsive">
                    <table class="table sitara-table mb-0">
                        <thead><tr><th>Semester</th><th>Rentang tanggal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                        <tr ng-if="loading"><td colspan="4" class="sitara-empty">Memuat data periode akademik...</td></tr>
                        <tr ng-repeat="item in datas track by item.id">
                            <td class="table-main" ng-bind="item.semester_code"></td>
                            <td><span ng-bind="item.start_date || '—'"></span> – <span ng-bind="item.end_date || '—'"></span></td>
                            <td><span ng-class="item.is_active ? 'sitara-status' : 'sitara-status sitara-status-muted'" ng-bind="item.is_active ? 'Aktif' : 'Arsip'"></span></td>
                            <td>
                                <div class="sitara-row-actions justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" ng-click="edit(item)" ng-disabled="workingId === item.id">Ubah</button>
                                    <button type="button" class="btn btn-sm btn-outline-success" ng-if="!item.is_active" ng-click="activate(item)" ng-disabled="workingId === item.id || !year.is_active">Aktifkan</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" ng-click="hapus(item)" ng-disabled="workingId === item.id">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <tr ng-if="!loading && datas.length === 0"><td colspan="4" class="sitara-empty">Belum ada periode akademik untuk tahun ini.</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </div>
</section>

<script>
window.SITARA_PERIOD_CONFIG = <?= json_encode([
    'page' => 'periods',
    'baseUrl' => site_url('periode'),
    'yearId' => (int) $yearId,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.SITARA_CSRF = <?= json_encode([
    'header' => $csrfHeader,
    'hash' => $csrfHash,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?= $this->endSection() ?>
