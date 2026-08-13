<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?>
<style>
[ng-cloak]{display:none!important}.student-toolbar{background:linear-gradient(135deg,#f8fbff,#eef5fd);border:1px solid #dce9f7;border-radius:.75rem;padding:1rem}.student-section{border:1px solid #e5eaf0;border-radius:.75rem;padding:1.15rem;height:100%}.student-section-soft{background:#f8fafc}.student-section-blue{background:linear-gradient(120deg,#f3f8ff,#fbfdff 70%);border-color:#dce9f7}.student-section-title{color:#4b6685;font-size:.75rem;font-weight:700;letter-spacing:.08em;margin-bottom:1rem;text-transform:uppercase}.student-modal-form{display:flex;flex:1 1 auto;flex-direction:column;max-height:100%;min-height:0}.student-modal-form>.modal-body{flex:1 1 auto;overflow-y:auto}.student-modal-form>.modal-footer{background:#fff;box-shadow:0 -.35rem 1rem rgba(31,58,95,.08);z-index:2}.student-file-zone{background:#f8fbff;border:2px dashed #b7cae0;border-radius:.75rem;padding:1.35rem;text-align:center}@media(max-width:575.98px){.student-modal-form>.modal-footer{display:grid;grid-template-columns:1fr 1fr}.student-modal-form>.modal-footer .btn{margin:0;width:100%}}
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= $this->include('pages/partials/page_heading') ?>

<section ng-controller="mahasiswaController" ng-cloak>
    <div class="alert alert-danger" ng-if="errorMessage" ng-bind="errorMessage"></div>
    <div class="alert alert-success" ng-if="successMessage" ng-bind="successMessage"></div>
    <article class="card sitara-card">
        <header class="card-header border-0 pb-0">
            <div class="student-toolbar d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div><h2 class="sitara-card-title">Daftar mahasiswa</h2><p class="sitara-card-subtitle mb-0"><span ng-bind="activeCount()"></span> mahasiswa aktif dari <span ng-bind="datas.length"></span> data.</p></div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <input type="search" class="form-control form-control-sm" style="min-width:240px" placeholder="Cari NIM, nama, atau program studi" ng-model="searchText">
                    <a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= site_url('master/mahasiswa/template') ?>">Format Excel</a>
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" ng-click="openImport()">Import Excel</button>
                    <button type="button" class="btn btn-sm btn-primary text-nowrap" ng-click="openCreate()">Tambah mahasiswa</button>
                </div>
            </div>
        </header>
        <div class="table-responsive mt-3">
            <table class="table sitara-table mb-0">
                <thead><tr><th>Mahasiswa</th><th>Program Studi</th><th>Angkatan</th><th>Kontak</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <tr ng-if="loading"><td colspan="6" class="sitara-empty">Memuat data mahasiswa...</td></tr>
                <tr ng-repeat="item in (filteredDatas = (datas | filter:searchText)) track by item.id">
                    <td><div class="table-main" ng-bind="item.full_name"></div><div class="small text-gray-600">NIM <span ng-bind="item.nim"></span></div></td>
                    <td><div class="table-main" ng-bind="item.program_code"></div><div class="small text-gray-600" ng-bind="item.degree_level + ' · ' + item.program_name"></div></td>
                    <td ng-bind="item.cohort_year || '—'"></td>
                    <td><div ng-bind="item.email || '—'"></div><div class="small text-gray-600" ng-bind="item.phone || ''"></div></td>
                    <td><span ng-class="item.status === 'AKTIF' ? 'sitara-status' : 'sitara-status sitara-status-muted'" ng-bind="item.status"></span></td>
                    <td><div class="sitara-row-actions justify-content-end"><button class="btn btn-sm btn-outline-primary" type="button" ng-click="edit(item)">Ubah</button><button class="btn btn-sm btn-outline-success" type="button" ng-if="item.status !== 'AKTIF'" ng-click="activate(item)">Aktifkan</button><button class="btn btn-sm btn-outline-danger" type="button" ng-click="hapus(item)">Hapus</button></div></td>
                </tr>
                <tr ng-if="!loading && datas.length === 0"><td colspan="6" class="sitara-empty">Belum ada data mahasiswa.</td></tr>
                <tr ng-if="!loading && datas.length && filteredDatas.length === 0"><td colspan="6" class="sitara-empty">Tidak ada mahasiswa yang sesuai pencarian.</td></tr>
                </tbody>
            </table>
        </div>
    </article>

    <div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form class="student-modal-form" name="studentForm" ng-submit="save(studentForm)" novalidate>
            <div class="modal-header"><div><h2 class="modal-title h5" ng-bind="model.id ? 'Ubah data mahasiswa' : 'Tambah mahasiswa'"></h2><p class="small text-gray-600 mb-0">Lengkapi identitas, akademik, dan kontak mahasiswa.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="alert alert-danger" ng-if="errorMessage" ng-bind="errorMessage"></div>
                <div class="student-section student-section-blue mb-4"><div class="student-section-title">Identitas akademik</div><div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="student_nim">NIM</label><input id="student_nim" name="nim" class="form-control" maxlength="30" ng-model="model.nim" required><div class="text-danger small mt-1" ng-if="studentForm.$submitted && studentForm.nim.$invalid">NIM wajib diisi.</div></div>
                    <div class="col-md-5"><label class="form-label" for="student_program">Program studi</label><select id="student_program" name="study_program_id" class="form-select" ng-model="model.study_program_id" required><option value="">Pilih program studi</option><option ng-repeat="program in programs | filter:{is_active:true}" ng-value="program.id" ng-bind="program.code + ' — ' + program.name"></option></select><div class="text-danger small mt-1" ng-if="studentForm.$submitted && studentForm.study_program_id.$invalid">Program studi wajib dipilih.</div></div>
                    <div class="col-md-3"><label class="form-label" for="student_cohort">Angkatan</label><input id="student_cohort" name="cohort_year" type="number" class="form-control" min="1900" max="<?= date('Y') + 1 ?>" ng-model="model.cohort_year" placeholder="<?= date('Y') ?>"></div>
                </div></div>
                <div class="row g-4"><div class="col-lg-7"><div class="student-section"><div class="student-section-title">Profil dan kontak</div>
                    <label class="form-label" for="student_name">Nama lengkap</label><input id="student_name" name="full_name" class="form-control" minlength="3" maxlength="200" ng-model="model.full_name" required><div class="text-danger small mt-1" ng-if="studentForm.$submitted && studentForm.full_name.$invalid">Nama lengkap wajib diisi.</div>
                    <div class="row g-3 mt-1"><div class="col-md-7"><label class="form-label" for="student_email">Email</label><input id="student_email" name="email" type="email" class="form-control" ng-model="model.email"><div class="text-danger small mt-1" ng-if="studentForm.$submitted && studentForm.email.$invalid">Email tidak valid.</div></div><div class="col-md-5"><label class="form-label" for="student_phone">Telepon</label><input id="student_phone" name="phone" class="form-control" maxlength="30" ng-model="model.phone"></div></div>
                </div></div><div class="col-lg-5"><div class="student-section student-section-soft"><div class="student-section-title">Status mahasiswa</div><label class="form-label" for="student_status">Status akademik</label><select id="student_status" name="status" class="form-select" ng-model="model.status" required><option ng-repeat="status in statuses" ng-value="status" ng-bind="status"></option></select><p class="small text-gray-600 mt-3 mb-0">Status aktif digunakan saat membuat kegiatan dan transaksi akademik mahasiswa.</p></div></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-gray" data-bs-dismiss="modal" ng-disabled="saving">Batal</button><button type="submit" class="btn btn-primary px-4" ng-disabled="saving" ng-bind="saving ? 'Menyimpan...' : (model.id ? 'Simpan perubahan' : 'Simpan data mahasiswa')"></button></div>
        </form>
    </div></div></div>

    <div class="modal fade" id="studentImportModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <form class="student-modal-form" name="studentImportForm" ng-submit="importExcel(studentImportForm)" novalidate>
            <div class="modal-header"><div><h2 class="modal-title h5">Import data mahasiswa</h2><p class="small text-gray-600 mb-0">Tambahkan atau perbarui mahasiswa melalui Excel.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4"><div class="alert alert-danger" ng-if="errorMessage" ng-bind="errorMessage"></div><div class="alert alert-info small">Gunakan <strong>kode program studi</strong> dari sheet Daftar Prodi. Daftar tersebut diambil dari master Program Studi saat template diunduh. NIM yang sudah ada akan diperbarui.</div><div class="student-file-zone"><label for="student_import_file" class="form-label fw-bold">Pilih berkas .xlsx</label><input id="student_import_file" type="file" class="form-control" accept=".xlsx" file-change="selectImportFile(files)"><div class="small text-gray-600 mt-2" ng-if="importFile">Siap diimpor: <strong ng-bind="importFile.name"></strong></div><div class="text-danger small mt-2" ng-if="studentImportForm.$submitted && !importFile">Pilih berkas terlebih dahulu.</div></div><div class="d-flex justify-content-between align-items-center mt-3"><span class="small text-gray-600">Maksimal 1.000 baris atau 2 MB.</span><a class="btn btn-sm btn-outline-primary" href="<?= site_url('master/mahasiswa/template') ?>">Unduh format Excel</a></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-gray" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary px-4" ng-disabled="importing || !importFile" ng-bind="importing ? 'Mengimpor...' : 'Import sekarang'"></button></div>
        </form>
    </div></div></div>
</section>
<script>
window.SITARA_STUDENT_CONFIG=<?= json_encode(['baseUrl' => site_url('master/mahasiswa'), 'statuses' => $statuses], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.SITARA_CSRF=<?= json_encode(['header' => $csrfHeader, 'hash' => $csrfHash], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?= $this->endSection() ?>
