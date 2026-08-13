<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?>
<style>[ng-cloak]{display:none!important}</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= $this->include('pages/partials/page_heading') ?>

<section class="row g-3 align-items-start" ng-controller="programStudiController" ng-cloak>
    <div class="col-12">
        <div class="alert alert-danger" role="alert" ng-if="errorMessage" ng-bind="errorMessage"></div>
        <div class="alert alert-success" role="status" ng-if="successMessage" ng-bind="successMessage"></div>
    </div>

    <div class="col-12 col-xl-4">
        <article class="card sitara-card">
            <header class="card-header d-flex align-items-start justify-content-between gap-3">
                <div>
                    <h2 class="sitara-card-title" ng-bind="model.id ? 'Ubah program studi' : 'Tambah program studi'"></h2>
                    <p class="sitara-card-subtitle">Program studi aktif dapat digunakan pada kegiatan dan transaksi akademik.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-gray" ng-if="model.id" ng-click="reset()">Batal</button>
            </header>
            <div class="card-body">
                <form name="programForm" ng-submit="save(programForm)" novalidate>
                    <div class="mb-3">
                        <label for="program_code" class="form-label">Kode program studi</label>
                        <input id="program_code" name="code" type="text" class="form-control text-uppercase" maxlength="20" placeholder="SI" ng-model="model.code" ng-pattern="/^[A-Za-z0-9._-]+$/" required>
                        <div class="sitara-form-hint">Contoh: SI, TI, D3-AK.</div>
                        <div class="text-danger small mt-1" ng-if="programForm.$submitted && programForm.code.$invalid">Kode wajib diisi dengan huruf, angka, titik, garis bawah, atau tanda hubung.</div>
                    </div>
                    <div class="mb-3">
                        <label for="program_name" class="form-label">Nama program studi</label>
                        <input id="program_name" name="name" type="text" class="form-control" minlength="3" maxlength="150" placeholder="Sistem Informasi" ng-model="model.name" required>
                        <div class="text-danger small mt-1" ng-if="programForm.$submitted && programForm.name.$invalid">Nama program studi wajib terdiri dari minimal 3 karakter.</div>
                    </div>
                    <div class="mb-3">
                        <label for="degree_level" class="form-label">Jenjang</label>
                        <select id="degree_level" name="degree_level" class="form-select" ng-model="model.degree_level" required>
                            <option value="">Pilih jenjang</option>
                            <option ng-repeat="level in degreeLevels track by level" ng-value="level" ng-bind="level"></option>
                        </select>
                        <div class="text-danger small mt-1" ng-if="programForm.$submitted && programForm.degree_level.$invalid">Jenjang program studi wajib dipilih.</div>
                    </div>
                    <div class="form-check form-switch sitara-active-switch mt-4 mb-4">
                        <input id="program_is_active" name="is_active" class="form-check-input" type="checkbox" role="switch" ng-model="model.is_active">
                        <label class="form-check-label" for="program_is_active">Jadikan program studi aktif</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" ng-disabled="saving">
                        <span ng-bind="saving ? 'Menyimpan...' : (model.id ? 'Simpan perubahan' : 'Tambah program studi')"></span>
                    </button>
                </form>
            </div>
        </article>
    </div>

    <div class="col-12 col-xl-8">
        <article class="card sitara-card">
            <header class="card-header d-flex align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="sitara-card-title">Daftar program studi</h2>
                    <p class="sitara-card-subtitle">Program studi yang sudah dipakai dapat dinonaktifkan dan tetap tersimpan sebagai arsip.</p>
                </div>
                <span class="badge bg-primary"><span ng-bind="activeCount()"></span> aktif</span>
            </header>
            <div class="table-responsive">
                <table class="table sitara-table mb-0">
                    <thead><tr><th>Kode</th><th>Program studi</th><th>Jenjang</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <tr ng-if="loading"><td colspan="5" class="sitara-empty">Memuat data program studi...</td></tr>
                    <tr ng-repeat="item in datas track by item.id">
                        <td class="table-main" ng-bind="item.code"></td>
                        <td ng-bind="item.name"></td>
                        <td ng-bind="item.degree_level"></td>
                        <td><span ng-class="item.is_active ? 'sitara-status' : 'sitara-status sitara-status-muted'" ng-bind="item.is_active ? 'Aktif' : 'Arsip'"></span></td>
                        <td>
                            <div class="sitara-row-actions justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" ng-click="edit(item)" ng-disabled="workingId === item.id">Ubah</button>
                                <button type="button" class="btn btn-sm btn-outline-success" ng-if="!item.is_active" ng-click="activate(item)" ng-disabled="workingId === item.id">Aktifkan</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" ng-click="hapus(item)" ng-disabled="workingId === item.id">Hapus</button>
                            </div>
                        </td>
                    </tr>
                    <tr ng-if="!loading && datas.length === 0"><td colspan="5" class="sitara-empty">Belum ada program studi. Tambahkan program studi pertama melalui formulir di samping.</td></tr>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>

<script>
window.SITARA_PROGRAM_CONFIG = <?= json_encode([
    'baseUrl' => site_url('program-studi'),
    'degreeLevels' => array_values($degreeLevels),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.SITARA_CSRF = <?= json_encode([
    'header' => $csrfHeader,
    'hash' => $csrfHash,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?= $this->endSection() ?>
