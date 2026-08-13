<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?>
<style>
[ng-cloak]{display:none!important}
.lecturer-toolbar{background:linear-gradient(135deg,#f8fbff 0%,#eef5fd 100%);border:1px solid #dce9f7;border-radius:.75rem;padding:1rem}
.lecturer-section{border:1px solid #e5eaf0;border-radius:.75rem;padding:1.15rem;height:100%}
.lecturer-section-soft{background:#f8fafc}
.lecturer-section-bank{background:linear-gradient(120deg,#f3f8ff 0%,#fbfdff 70%);border-color:#dce9f7}
.lecturer-section-title{font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#4b6685;font-weight:700;margin-bottom:1rem}
.lecturer-optional{font-size:.68rem;font-weight:600;color:#64748b;background:#eef2f7;border-radius:999px;padding:.15rem .45rem;vertical-align:middle}
.lecturer-file-zone{border:2px dashed #b7cae0;border-radius:.75rem;background:#f8fbff;padding:1.35rem;text-align:center}
.lecturer-file-name{word-break:break-word}
.lecturer-modal-form{display:flex;flex:1 1 auto;flex-direction:column;max-height:100%;min-height:0}
.lecturer-modal-form>.modal-body{flex:1 1 auto;overflow-y:auto}
.lecturer-modal-form>.modal-footer{background:#fff;box-shadow:0 -.35rem 1rem rgba(31,58,95,.08);position:relative;z-index:2}
@media(max-width:575.98px){.lecturer-modal-form>.modal-footer{display:grid;grid-template-columns:1fr 1fr}.lecturer-modal-form>.modal-footer .btn{margin:0;width:100%}}
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?= $this->include('pages/partials/page_heading') ?>

<section ng-controller="dosenController" ng-cloak>
    <div class="alert alert-danger" role="alert" ng-if="errorMessage" ng-bind="errorMessage"></div>
    <div class="alert alert-success" role="status" ng-if="successMessage" ng-bind="successMessage"></div>

    <article class="card sitara-card">
        <header class="card-header border-0 pb-0">
            <div class="lecturer-toolbar d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div>
                    <h2 class="sitara-card-title">Daftar dosen</h2>
                    <p class="sitara-card-subtitle mb-0"><span ng-bind="activeCount()"></span> dosen aktif dari <span ng-bind="datas.length"></span> data.</p>
                </div>
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
                    <input type="search" class="form-control form-control-sm" style="min-width:240px" placeholder="Cari nama, NIDN, atau NIP" ng-model="searchText">
                    <a class="btn btn-sm btn-outline-primary text-nowrap" href="<?= base_url('templates/template-import-dosen.xlsx') ?>" download>
                        Format Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" ng-click="openImport()">Import Excel</button>
                    <button type="button" class="btn btn-sm btn-primary text-nowrap" ng-click="openCreate()">Tambah dosen</button>
                </div>
            </div>
        </header>

        <div class="table-responsive mt-3">
            <table class="table sitara-table mb-0">
                <thead>
                <tr>
                    <th>Identitas</th>
                    <th>Dosen</th>
                    <th>Kontak</th>
                    <th>Rekening</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <tr ng-if="loading"><td colspan="6" class="sitara-empty">Memuat data dosen...</td></tr>
                <tr ng-repeat="item in (filteredDatas = (datas | filter:searchText)) track by item.id">
                    <td>
                        <div class="table-main" ng-if="item.nidn">NIDN <span ng-bind="item.nidn"></span></div>
                        <div ng-if="item.nip">NIP <span ng-bind="item.nip"></span></div>
                    </td>
                    <td>
                        <div class="table-main" ng-bind="item.full_name"></div>
                        <div class="small text-gray-600" ng-bind="item.tax_id ? 'NPWP ' + item.tax_id : 'NPWP belum tersedia'"></div>
                    </td>
                    <td>
                        <div ng-bind="item.email || '—'"></div>
                        <div class="small text-gray-600" ng-bind="item.phone || ''"></div>
                    </td>
                    <td>
                        <div ng-bind="item.bank_name || '—'"></div>
                        <div class="small text-gray-600" ng-bind="item.bank_account_number || ''"></div>
                    </td>
                    <td><span ng-class="item.is_active ? 'sitara-status' : 'sitara-status sitara-status-muted'" ng-bind="item.is_active ? 'Aktif' : 'Arsip'"></span></td>
                    <td>
                        <div class="sitara-row-actions justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" ng-click="edit(item)" ng-disabled="workingId === item.id">Ubah</button>
                            <button type="button" class="btn btn-sm btn-outline-success" ng-if="!item.is_active" ng-click="activate(item)" ng-disabled="workingId === item.id">Aktifkan</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" ng-click="hapus(item)" ng-disabled="workingId === item.id">Hapus</button>
                        </div>
                    </td>
                </tr>
                <tr ng-if="!loading && datas.length === 0"><td colspan="6" class="sitara-empty">Belum ada data dosen.</td></tr>
                <tr ng-if="!loading && datas.length > 0 && filteredDatas.length === 0"><td colspan="6" class="sitara-empty">Tidak ada dosen yang sesuai dengan pencarian.</td></tr>
                </tbody>
            </table>
        </div>
    </article>

    <div class="modal fade" id="lecturerModal" tabindex="-1" aria-labelledby="lecturerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form class="lecturer-modal-form" name="lecturerForm" ng-submit="save(lecturerForm)" novalidate>
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 mb-1" id="lecturerModalLabel" ng-bind="model.id ? 'Ubah data dosen' : 'Tambah dosen'"></h2>
                            <p class="small text-gray-600 mb-0">Lengkapi identitas, kontak, dan informasi pembayaran dalam satu formulir.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="alert alert-danger" role="alert" ng-if="errorMessage" ng-bind="errorMessage"></div>
                        <div class="lecturer-section lecturer-section-bank mb-4">
                            <div class="lecturer-section-title">Identitas utama</div>
                            <div class="row g-3 align-items-start">
                                <div class="col-md-5">
                                    <label for="lecturer_nidn" class="form-label">NIDN</label>
                                    <input id="lecturer_nidn" name="nidn" type="text" class="form-control" maxlength="30" placeholder="0123456789" ng-model="model.nidn">
                                </div>
                                <div class="col-md-4">
                                    <label for="lecturer_nip" class="form-label">NIP <span class="lecturer-optional">Opsional</span></label>
                                    <input id="lecturer_nip" name="nip" type="text" class="form-control" maxlength="50" placeholder="Boleh dikosongkan" ng-model="model.nip">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label d-block">Status data</label>
                                    <div class="form-check form-switch sitara-active-switch pt-2">
                                        <input id="lecturer_is_active" name="is_active" class="form-check-input" type="checkbox" role="switch" ng-model="model.is_active">
                                        <label class="form-check-label" for="lecturer_is_active" ng-bind="model.is_active ? 'Dosen aktif' : 'Dosen nonaktif'"></label>
                                    </div>
                                </div>
                            </div>
                            <div class="text-danger small mt-2" ng-if="lecturerForm.$submitted && !model.nidn && !model.nip">NIDN wajib diisi apabila NIP dikosongkan.</div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-lg-7">
                                <div class="lecturer-section">
                                    <div class="lecturer-section-title">Profil dan kontak</div>
                                    <div>
                                        <label for="lecturer_name" class="form-label">Nama lengkap</label>
                                        <input id="lecturer_name" name="full_name" type="text" class="form-control" minlength="3" maxlength="200" placeholder="Nama lengkap beserta gelar" ng-model="model.full_name" required>
                                        <div class="text-danger small mt-1" ng-if="lecturerForm.$submitted && lecturerForm.full_name.$invalid">Nama lengkap dosen wajib diisi.</div>
                                    </div>
                                    <div class="row g-3 mt-1">
                                        <div class="col-md-7">
                                            <label for="lecturer_email" class="form-label">Email</label>
                                            <input id="lecturer_email" name="email" type="email" class="form-control" maxlength="200" placeholder="dosen@kampus.ac.id" ng-model="model.email">
                                            <div class="text-danger small mt-1" ng-if="lecturerForm.$submitted && lecturerForm.email.$invalid">Alamat email tidak valid.</div>
                                        </div>
                                        <div class="col-md-5">
                                            <label for="lecturer_phone" class="form-label">Telepon</label>
                                            <input id="lecturer_phone" name="phone" type="text" class="form-control" maxlength="30" placeholder="08..." ng-model="model.phone">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="lecturer-section lecturer-section-soft">
                                    <div class="lecturer-section-title">Administrasi pajak</div>
                                    <label for="tax_id" class="form-label">NPWP <span class="lecturer-optional">Opsional</span></label>
                                    <input id="tax_id" name="tax_id" type="text" class="form-control" maxlength="50" placeholder="00.000.000.0-000.000" ng-model="model.tax_id">
                                    <p class="small text-gray-600 mt-3 mb-0">Data pajak dapat dilengkapi kemudian dan tidak menghalangi penyimpanan data dosen.</p>
                                </div>
                            </div>
                        </div>

                        <div class="lecturer-section lecturer-section-bank">
                            <div class="lecturer-section-title">Informasi rekening</div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="bank_name" class="form-label">Nama bank</label>
                                    <input id="bank_name" name="bank_name" type="text" class="form-control" maxlength="100" placeholder="Bank Papua" ng-model="model.bank_name">
                                </div>
                                <div class="col-md-4">
                                    <label for="bank_account_number" class="form-label">Nomor rekening</label>
                                    <input id="bank_account_number" name="bank_account_number" type="text" class="form-control" maxlength="100" placeholder="Nomor rekening" ng-model="model.bank_account_number">
                                </div>
                                <div class="col-md-5">
                                    <label for="bank_account_name" class="form-label">Nama pemilik rekening</label>
                                    <input id="bank_account_name" name="bank_account_name" type="text" class="form-control" maxlength="200" placeholder="Sesuai buku rekening" ng-model="model.bank_account_name">
                                </div>
                            </div>
                            <p class="small text-gray-600 mt-2 mb-0">Jika data rekening digunakan, nama bank, nomor rekening, dan nama pemilik harus diisi bersama.</p>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-gray" data-bs-dismiss="modal" ng-disabled="saving">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" ng-disabled="saving">
                            <span ng-bind="saving ? 'Menyimpan...' : (model.id ? 'Simpan perubahan' : 'Simpan data dosen')"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lecturerImportModal" tabindex="-1" aria-labelledby="lecturerImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form class="lecturer-modal-form" name="importForm" ng-submit="importExcel(importForm)" novalidate>
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 mb-1" id="lecturerImportModalLabel">Import data dosen</h2>
                            <p class="small text-gray-600 mb-0">Tambahkan atau perbarui banyak dosen melalui satu berkas Excel.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-danger" role="alert" ng-if="errorMessage" ng-bind="errorMessage"></div>
                        <div class="alert alert-info small">
                            Gunakan sheet <strong>Data Dosen</strong>. NIP boleh kosong selama NIDN diisi. Data dengan NIDN atau NIP yang sudah ada akan diperbarui.
                        </div>
                        <div class="lecturer-file-zone">
                            <label for="lecturer_import_file" class="form-label fw-bold">Pilih berkas .xlsx</label>
                            <input id="lecturer_import_file" name="file" type="file" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" file-change="selectImportFile(files)">
                            <div class="small text-gray-600 mt-2 lecturer-file-name" ng-if="importFile">Siap diimpor: <strong ng-bind="importFile.name"></strong></div>
                            <div class="text-danger small mt-2" ng-if="importForm.$submitted && !importFile">Pilih berkas Excel terlebih dahulu.</div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3">
                            <span class="small text-gray-600">Maksimal 1.000 baris atau 2 MB per impor.</span>
                            <a href="<?= base_url('templates/template-import-dosen.xlsx') ?>" class="btn btn-sm btn-outline-primary" download>Unduh format Excel</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-gray" data-bs-dismiss="modal" ng-disabled="importing">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" ng-disabled="importing || !importFile" ng-bind="importing ? 'Mengimpor...' : 'Import sekarang'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
window.SITARA_LECTURER_CONFIG = <?= json_encode([
    'baseUrl' => site_url('master/dosen'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.SITARA_CSRF = <?= json_encode([
    'header' => $csrfHeader,
    'hash' => $csrfHash,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<?= $this->endSection() ?>
