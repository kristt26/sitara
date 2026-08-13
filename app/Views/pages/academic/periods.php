<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?>
<style>[ng-cloak]{display:none!important}</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= $this->include('pages/partials/page_heading') ?>

<section class="row g-3 align-items-start" ng-controller="tahunPeriodeController" ng-cloak>
    <div class="col-12">
        <div class="alert alert-danger" role="alert" ng-if="errorMessage" ng-bind="errorMessage"></div>
        <div class="alert alert-success" role="status" ng-if="successMessage" ng-bind="successMessage"></div>
    </div>

    <div class="col-12 col-xl-4">
        <article class="card sitara-card">
            <header class="card-header d-flex align-items-start justify-content-between gap-3">
                <div>
                    <h2 class="sitara-card-title" ng-bind="model.id ? 'Ubah tahun akademik' : 'Tambah tahun akademik'"></h2>
                    <p class="sitara-card-subtitle">Hanya satu tahun akademik yang dapat aktif pada satu waktu.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-gray" ng-if="model.id" ng-click="reset()">Batal</button>
            </header>
            <div class="card-body">
                <form name="yearForm" ng-submit="save(yearForm)" novalidate>
                    <div class="mb-3">
                        <label for="code" class="form-label">Kode tahun akademik</label>
                        <input id="code" name="code" type="text" class="form-control" maxlength="20" placeholder="2026/2027" ng-model="model.code" ng-pattern="/^\d{4}\/\d{4}$/" required>
                        <div class="sitara-form-hint">Gunakan format YYYY/YYYY.</div>
                        <div class="text-danger small mt-1" ng-if="yearForm.$submitted && yearForm.code.$invalid">Kode tahun akademik wajib memakai format YYYY/YYYY.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label for="start_year" class="form-label">Tahun awal</label>
                            <input id="start_year" name="start_year" type="number" class="form-control" min="2000" max="9998" placeholder="2026" ng-model="model.start_year" required>
                        </div>
                        <div class="col-6">
                            <label for="end_year" class="form-label">Tahun akhir</label>
                            <input id="end_year" name="end_year" type="number" class="form-control" min="2001" max="9999" placeholder="2027" ng-model="model.end_year" required>
                        </div>
                    </div>
                    <div class="text-danger small mt-1" ng-if="yearForm.$submitted && (yearForm.start_year.$invalid || yearForm.end_year.$invalid)">Tahun awal dan tahun akhir wajib diisi.</div>
                    <div class="form-check form-switch sitara-active-switch mt-4 mb-4">
                        <input id="is_active" name="is_active" class="form-check-input" type="checkbox" role="switch" ng-model="model.is_active">
                        <label class="form-check-label" for="is_active">Jadikan tahun akademik aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" ng-disabled="saving">
                        <span ng-bind="saving ? 'Menyimpan...' : (model.id ? 'Simpan perubahan' : 'Tambah tahun akademik')"></span>
                    </button>
                </form>
            </div>
        </article>
    </div>

    <div class="col-12 col-xl-8">
        <article class="card sitara-card">
            <header class="card-header">
                <h2 class="sitara-card-title">Daftar tahun akademik</h2>
                <p class="sitara-card-subtitle">Pilih tombol Periode untuk mengelola semester pada tahun akademik tersebut.</p>
            </header>
            <div class="table-responsive">
                <table class="table sitara-table mb-0">
                    <thead>
                    <tr><th>Tahun akademik</th><th>Rentang</th><th>Periode</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <tr ng-if="loading"><td colspan="5" class="sitara-empty">Memuat data tahun akademik...</td></tr>
                    <tr ng-repeat="item in datas track by item.id">
                        <td class="table-main" ng-bind="item.code"></td>
                        <td><span ng-bind="item.start_year"></span>–<span ng-bind="item.end_year"></span></td>
                        <td><span ng-bind="item.period_count || 0"></span> periode</td>
                        <td>
                            <span ng-class="item.is_active ? 'sitara-status' : 'sitara-status sitara-status-muted'" ng-bind="item.is_active ? 'Aktif' : 'Arsip'"></span>
                        </td>
                        <td>
                            <div class="sitara-row-actions justify-content-end">
                                <a ng-href="{{ periodBaseUrl + '/' + item.id }}" class="btn btn-sm btn-outline-info">Periode</a>
                                <button type="button" class="btn btn-sm btn-outline-primary" ng-click="edit(item)" ng-disabled="workingId === item.id">Ubah</button>
                                <button type="button" class="btn btn-sm btn-outline-success" ng-if="!item.is_active" ng-click="activate(item)" ng-disabled="workingId === item.id">Aktifkan</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" ng-click="hapus(item)" ng-disabled="workingId === item.id">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr ng-if="!loading && datas.length === 0"><td colspan="5" class="sitara-empty">Belum ada tahun akademik. Tambahkan tahun akademik pertama melalui formulir di samping.</td></tr>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>

<script>
window.SITARA_PERIOD_CONFIG = <?= json_encode([
    'page' => 'years',
    'baseUrl' => site_url('periode'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.SITARA_CSRF = <?= json_encode([
    'header' => $csrfHeader,
    'hash' => $csrfHash,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?= $this->endSection() ?>
