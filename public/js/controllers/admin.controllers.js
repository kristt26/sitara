angular
  .module("adminctrl", [])
  .controller("dashboardController", dashboardController)
  .controller("tahunPeriodeController", tahunPeriodeController)
  .controller("periodeController", periodeController)
  .controller("programStudiController", programStudiController)
  .controller("dosenController", dosenController)
  .controller("mahasiswaController", mahasiswaController)
  .controller("jenisKegiatanController", jenisKegiatanController)
  .controller("aturanKegiatanController", aturanKegiatanController)
  .controller("metodePembayaranController", metodePembayaranController)
  .controller("tarifController", tarifController)
  .controller("kegiatanMahasiswaController", kegiatanMahasiswaController)
  .controller("documentTemplateController", documentTemplateController)
  .controller("tagihanMahasiswaController", tagihanMahasiswaController)
  .controller("verifikasiPembayaranController", verifikasiPembayaranController)
  .controller("tarifHonorController", tarifHonorController).controller("hakHonorController", hakHonorController).controller("batchHonorController", batchHonorController).controller("adminUserController", adminUserController).controller("auditLogController", auditLogController)
  .directive("fileChange", fileChangeDirective);

function dashboardController($scope, dashboardServices) {
  $scope.data = {};
  $scope.dashboardPercent = function (value, total) {
    value = parseInt(value || 0, 10);
    total = parseInt(total || 0, 10);

    if (total <= 0) {
      return value > 0 ? 100 : 0;
    }

    return Math.min(100, Math.round((value / total) * 100));
  };

  dashboardServices.get().then(function (data) {
    $scope.data = data || {};
  });
}

function tahunPeriodeController($scope, tahunService) {
  var config = window.SITARA_PERIOD_CONFIG || {};
  $scope.periodBaseUrl = config.baseUrl || "periode";
  $scope.datas = [];
  $scope.loading = true;
  $scope.saving = false;
  $scope.workingId = null;
  $scope.errorMessage = "";
  $scope.successMessage = "";

  $scope.reset = function () {
    $scope.model = {
      id: null,
      code: "",
      start_year: null,
      end_year: null,
      is_active: false,
    };
    resetForm($scope.yearForm);
  };

  $scope.edit = function (item) {
    clearMessages();
    $scope.model = angular.copy(item);
    $scope.model.id = parseInt(item.id, 10);
    $scope.model.start_year = parseInt(item.start_year, 10);
    $scope.model.end_year = parseInt(item.end_year, 10);
    $scope.model.is_active = toBoolean(item.is_active);
    focusField("code");
  };

  $scope.save = function (form) {
    clearMessages();
    if (!form || form.$invalid) {
      if (form) form.$setSubmitted();
      return;
    }

    $scope.saving = true;
    var payload = angular.copy($scope.model);
    var request = payload.id ? tahunService.put(payload) : tahunService.post(payload);

    request.then(function (response) {
      $scope.successMessage = response.message || "Tahun akademik berhasil disimpan.";
      $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.saving = false;
    });
  };

  $scope.activate = function (item) {
    clearMessages();
    if (!window.confirm("Aktifkan tahun akademik " + item.code + "? Periode aktif dari tahun sebelumnya akan dinonaktifkan.")) {
      return;
    }

    $scope.workingId = item.id;
    tahunService.activate(item).then(function (response) {
      $scope.successMessage = response.message || "Tahun akademik berhasil diaktifkan.";
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  $scope.hapus = function (item) {
    clearMessages();
    if (!window.confirm("Hapus tahun akademik " + item.code + "? Tahun yang sudah memiliki periode tidak dapat dihapus.")) {
      return;
    }

    $scope.workingId = item.id;
    tahunService.deleted(item).then(function (response) {
      $scope.successMessage = response.message || "Tahun akademik berhasil dihapus.";
      if ($scope.model.id === item.id) $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  function loadData() {
    $scope.loading = true;
    return tahunService.get().then(function (response) {
      $scope.datas = (response.data || []).map(normalizeYear);
    }).catch(handleError).finally(function () {
      $scope.loading = false;
    });
  }

  function normalizeYear(item) {
    item.id = parseInt(item.id, 10);
    item.start_year = parseInt(item.start_year, 10);
    item.end_year = parseInt(item.end_year, 10);
    item.period_count = parseInt(item.period_count || 0, 10);
    item.is_active = toBoolean(item.is_active);
    return item;
  }

  function clearMessages() {
    $scope.errorMessage = "";
    $scope.successMessage = "";
  }

  function handleError(error) {
    $scope.errorMessage = errorMessage(error);
  }

  $scope.reset();
  loadData();
}

function periodeController($scope, periodeService) {
  $scope.year = null;
  $scope.datas = [];
  $scope.loading = true;
  $scope.saving = false;
  $scope.workingId = null;
  $scope.errorMessage = "";
  $scope.successMessage = "";

  $scope.reset = function () {
    $scope.model = {
      id: null,
      semester_code: "",
      start_date: null,
      end_date: null,
      is_active: false,
    };
    resetForm($scope.periodForm);
  };

  $scope.edit = function (item) {
    clearMessages();
    $scope.model = angular.copy(item);
    $scope.model.id = parseInt(item.id, 10);
    $scope.model.start_date = parseDate(item.start_date);
    $scope.model.end_date = parseDate(item.end_date);
    $scope.model.is_active = toBoolean(item.is_active) && Boolean($scope.year && $scope.year.is_active);
    focusField("semester_code");
  };

  $scope.save = function (form) {
    clearMessages();
    if (!form || form.$invalid) {
      if (form) form.$setSubmitted();
      return;
    }

    $scope.saving = true;
    var payload = {
      id: $scope.model.id,
      semester_code: $scope.model.semester_code,
      start_date: formatDate($scope.model.start_date),
      end_date: formatDate($scope.model.end_date),
      is_active: Boolean($scope.model.is_active),
    };
    var request = payload.id ? periodeService.put(payload) : periodeService.post(payload);

    request.then(function (response) {
      $scope.successMessage = response.message || "Periode akademik berhasil disimpan.";
      $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.saving = false;
    });
  };

  $scope.activate = function (item) {
    clearMessages();
    if (!$scope.year || !$scope.year.is_active) {
      $scope.errorMessage = "Aktifkan tahun akademik ini terlebih dahulu.";
      return;
    }
    if (!window.confirm("Aktifkan semester " + item.semester_code + "? Periode aktif lainnya akan dinonaktifkan.")) {
      return;
    }

    $scope.workingId = item.id;
    periodeService.activate(item).then(function (response) {
      $scope.successMessage = response.message || "Periode akademik berhasil diaktifkan.";
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  $scope.hapus = function (item) {
    clearMessages();
    if (!window.confirm("Hapus semester " + item.semester_code + " dari tahun akademik ini?")) {
      return;
    }

    $scope.workingId = item.id;
    periodeService.deleted(item).then(function (response) {
      $scope.successMessage = response.message || "Periode akademik berhasil dihapus.";
      if ($scope.model.id === item.id) $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  function loadData() {
    $scope.loading = true;
    return periodeService.get().then(function (response) {
      var result = response.data || {};
      $scope.year = normalizeYear(result.year || {});
      $scope.datas = (result.periods || []).map(normalizePeriod);
    }).catch(handleError).finally(function () {
      $scope.loading = false;
    });
  }

  function normalizeYear(item) {
    item.id = parseInt(item.id, 10);
    item.start_year = parseInt(item.start_year, 10);
    item.end_year = parseInt(item.end_year, 10);
    item.is_active = toBoolean(item.is_active);
    return item;
  }

  function normalizePeriod(item) {
    item.id = parseInt(item.id, 10);
    item.academic_year_id = parseInt(item.academic_year_id, 10);
    item.is_active = toBoolean(item.is_active);
    return item;
  }

  function clearMessages() {
    $scope.errorMessage = "";
    $scope.successMessage = "";
  }

  function handleError(error) {
    $scope.errorMessage = errorMessage(error);
  }

  $scope.reset();
  loadData();
}

function programStudiController($scope, programStudiService) {
  var config = window.SITARA_PROGRAM_CONFIG || {};
  $scope.degreeLevels = config.degreeLevels || ["D3", "D4", "S1", "S2", "S3", "PROFESI"];
  $scope.datas = [];
  $scope.loading = true;
  $scope.saving = false;
  $scope.workingId = null;
  $scope.errorMessage = "";
  $scope.successMessage = "";

  $scope.reset = function () {
    $scope.model = {
      id: null,
      code: "",
      name: "",
      degree_level: "S1",
      is_active: true,
    };
    resetForm($scope.programForm);
  };

  $scope.edit = function (item) {
    clearMessages();
    $scope.model = angular.copy(item);
    $scope.model.id = parseInt(item.id, 10);
    $scope.model.is_active = toBoolean(item.is_active);
    focusField("program_code");
  };

  $scope.save = function (form) {
    clearMessages();
    if (!form || form.$invalid) {
      if (form) form.$setSubmitted();
      return;
    }

    $scope.saving = true;
    var payload = angular.copy($scope.model);
    payload.code = String(payload.code || "").trim().toUpperCase();
    payload.name = String(payload.name || "").trim();
    var request = payload.id ? programStudiService.put(payload) : programStudiService.post(payload);

    request.then(function (response) {
      $scope.successMessage = response.message || "Program studi berhasil disimpan.";
      $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.saving = false;
    });
  };

  $scope.activate = function (item) {
    clearMessages();
    if (!window.confirm("Aktifkan program studi " + item.code + " - " + item.name + "?")) {
      return;
    }

    $scope.workingId = item.id;
    programStudiService.activate(item).then(function (response) {
      $scope.successMessage = response.message || "Program studi berhasil diaktifkan.";
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  $scope.hapus = function (item) {
    clearMessages();
    if (!window.confirm("Hapus program studi " + item.code + " - " + item.name + "? Data yang sudah digunakan tidak dapat dihapus.")) {
      return;
    }

    $scope.workingId = item.id;
    programStudiService.deleted(item).then(function (response) {
      $scope.successMessage = response.message || "Program studi berhasil dihapus.";
      if ($scope.model.id === item.id) $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  $scope.activeCount = function () {
    return $scope.datas.filter(function (item) {
      return item.is_active;
    }).length;
  };

  function loadData() {
    $scope.loading = true;
    return programStudiService.get().then(function (response) {
      $scope.datas = (response.data || []).map(normalizeProgram);
    }).catch(handleError).finally(function () {
      $scope.loading = false;
    });
  }

  function normalizeProgram(item) {
    item.id = parseInt(item.id, 10);
    item.is_active = toBoolean(item.is_active);
    return item;
  }

  function clearMessages() {
    $scope.errorMessage = "";
    $scope.successMessage = "";
  }

  function handleError(error) {
    $scope.errorMessage = errorMessage(error);
  }

  $scope.reset();
  loadData();
}

function dosenController($scope, dosenService) {
  $scope.datas = [];
  $scope.searchText = "";
  $scope.loading = true;
  $scope.saving = false;
  $scope.importing = false;
  $scope.importFile = null;
  $scope.workingId = null;
  $scope.errorMessage = "";
  $scope.successMessage = "";

  $scope.reset = function () {
    $scope.model = emptyLecturerModel();
    resetForm($scope.lecturerForm);
  };

  $scope.openCreate = function () {
    clearMessages();
    $scope.reset();
    lecturerModal().show();
    focusField("lecturer_nidn");
  };

  $scope.edit = function (item) {
    clearMessages();
    resetForm($scope.lecturerForm);
    $scope.model = angular.copy(item);
    $scope.model.id = parseInt(item.id, 10);
    $scope.model.is_active = toBoolean(item.is_active);
    lecturerModal().show();
    focusField("lecturer_nidn");
  };

  $scope.openImport = function () {
    clearMessages();
    $scope.importFile = null;
    resetForm($scope.importForm);
    var input = document.getElementById("lecturer_import_file");
    if (input) input.value = "";
    importModal().show();
  };

  $scope.selectImportFile = function (files) {
    $scope.importFile = files && files.length ? files[0] : null;
  };

  $scope.importExcel = function (form) {
    clearMessages();
    if (!$scope.importFile) {
      if (form) form.$setSubmitted();
      return;
    }

    $scope.importing = true;
    dosenService.importFile($scope.importFile).then(function (response) {
      $scope.successMessage = response.message || "Data dosen berhasil diimpor.";
      importModal().hide();
      $scope.importFile = null;
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.importing = false;
    });
  };

  $scope.save = function (form) {
    clearMessages();
    if (!form || form.$invalid || (!$scope.model.nidn && !$scope.model.nip)) {
      if (form) form.$setSubmitted();
      return;
    }

    $scope.saving = true;
    var payload = angular.copy($scope.model);
    var request = payload.id ? dosenService.put(payload) : dosenService.post(payload);

    request.then(function (response) {
      $scope.successMessage = response.message || "Data dosen berhasil disimpan.";
      lecturerModal().hide();
      $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.saving = false;
    });
  };

  $scope.activate = function (item) {
    clearMessages();
    if (!window.confirm("Aktifkan kembali dosen " + item.full_name + "?")) {
      return;
    }

    $scope.workingId = item.id;
    dosenService.activate(item).then(function (response) {
      $scope.successMessage = response.message || "Dosen berhasil diaktifkan.";
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  $scope.hapus = function (item) {
    clearMessages();
    if (!window.confirm("Hapus data dosen " + item.full_name + "? Dosen yang sudah memiliki penugasan atau honor tidak dapat dihapus.")) {
      return;
    }

    $scope.workingId = item.id;
    dosenService.deleted(item).then(function (response) {
      $scope.successMessage = response.message || "Data dosen berhasil dihapus.";
      if ($scope.model.id === item.id) $scope.reset();
      return loadData();
    }).catch(handleError).finally(function () {
      $scope.workingId = null;
    });
  };

  $scope.activeCount = function () {
    return $scope.datas.filter(function (item) {
      return item.is_active;
    }).length;
  };

  function loadData() {
    $scope.loading = true;
    return dosenService.get().then(function (response) {
      $scope.datas = (response.data || []).map(normalizeLecturer);
    }).catch(handleError).finally(function () {
      $scope.loading = false;
    });
  }

  function normalizeLecturer(item) {
    item.id = parseInt(item.id, 10);
    item.is_active = toBoolean(item.is_active);
    return item;
  }

  function emptyLecturerModel() {
    return {
      id: null,
      nidn: "",
      nip: "",
      full_name: "",
      email: "",
      phone: "",
      bank_name: "",
      bank_account_number: "",
      bank_account_name: "",
      tax_id: "",
      is_active: true,
    };
  }

  function lecturerModal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("lecturerModal"));
  }

  function importModal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("lecturerImportModal"));
  }

  function clearMessages() {
    $scope.errorMessage = "";
    $scope.successMessage = "";
  }

  function handleError(error) {
    $scope.errorMessage = errorMessage(error);
  }

  $scope.reset();
  loadData();
}

function fileChangeDirective() {
  return {
    restrict: "A",
    scope: { fileChange: "&" },
    link: function (scope, element) {
      var handler = function (event) {
        scope.$apply(function () {
          scope.fileChange({ files: event.target.files });
        });
      };
      element.on("change", handler);
      scope.$on("$destroy", function () {
        element.off("change", handler);
      });
    },
  };
}

function mahasiswaController($scope, mahasiswaService) {
  var config = window.SITARA_STUDENT_CONFIG || {};
  $scope.statuses = config.statuses || ["AKTIF", "CUTI", "LULUS", "NONAKTIF"];
  $scope.datas = [];
  $scope.programs = [];
  $scope.searchText = "";
  $scope.loading = true;
  $scope.saving = false;
  $scope.importing = false;
  $scope.importFile = null;
  $scope.workingId = null;
  $scope.errorMessage = "";
  $scope.successMessage = "";

  $scope.reset = function () {
    $scope.model = { id: null, nim: "", full_name: "", study_program_id: null, cohort_year: null, email: "", phone: "", status: "AKTIF" };
    resetForm($scope.studentForm);
  };

  $scope.openCreate = function () { clearMessages(); $scope.reset(); studentModal().show(); focusField("student_nim"); };
  $scope.edit = function (item) {
    clearMessages(); resetForm($scope.studentForm); $scope.model = angular.copy(item);
    $scope.model.id = parseInt(item.id, 10); $scope.model.study_program_id = parseInt(item.study_program_id, 10);
    $scope.model.cohort_year = item.cohort_year ? parseInt(item.cohort_year, 10) : null;
    studentModal().show(); focusField("student_nim");
  };
  $scope.save = function (form) {
    clearMessages();
    if (!form || form.$invalid) { if (form) form.$setSubmitted(); return; }
    $scope.saving = true;
    var payload = angular.copy($scope.model);
    (payload.id ? mahasiswaService.put(payload) : mahasiswaService.post(payload)).then(function (response) {
      $scope.successMessage = response.message || "Data mahasiswa berhasil disimpan.";
      studentModal().hide(); $scope.reset(); return loadData();
    }).catch(handleError).finally(function () { $scope.saving = false; });
  };
  $scope.activate = function (item) {
    clearMessages(); if (!window.confirm("Aktifkan kembali mahasiswa " + item.full_name + "?")) return;
    $scope.workingId = item.id;
    mahasiswaService.activate(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; });
  };
  $scope.issueActivation = function (item) {
    clearMessages(); if (!window.confirm("Kirim ulang kode aktivasi ke email " + (item.email || "mahasiswa") + "? Kode sebelumnya akan dinonaktifkan.")) return;
    $scope.workingId = item.id;
    mahasiswaService.activation(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; });
  };
  $scope.hapus = function (item) {
    clearMessages(); if (!window.confirm("Hapus data mahasiswa " + item.full_name + "? Data yang sudah digunakan pada transaksi tidak dapat dihapus.")) return;
    $scope.workingId = item.id;
    mahasiswaService.deleted(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; });
  };
  $scope.openImport = function () {
    clearMessages(); $scope.importFile = null; resetForm($scope.studentImportForm);
    var input = document.getElementById("student_import_file"); if (input) input.value = "";
    studentImportModal().show();
  };
  $scope.selectImportFile = function (files) { $scope.importFile = files && files.length ? files[0] : null; };
  $scope.importExcel = function (form) {
    clearMessages(); if (!$scope.importFile) { if (form) form.$setSubmitted(); return; }
    $scope.importing = true;
    mahasiswaService.importFile($scope.importFile).then(function (response) {
      $scope.successMessage = response.message || "Data mahasiswa berhasil diimpor."; studentImportModal().hide(); return loadData();
    }).catch(handleError).finally(function () { $scope.importing = false; });
  };
  $scope.activeCount = function () { return $scope.datas.filter(function (item) { return item.status === "AKTIF"; }).length; };

  function loadData() {
    $scope.loading = true;
    return mahasiswaService.get().then(function (response) {
      var data = response.data || {}; $scope.programs = (data.programs || []).map(normalizeProgram); $scope.datas = (data.students || []).map(normalizeStudent);
    }).catch(handleError).finally(function () { $scope.loading = false; });
  }
  function normalizeProgram(item) { item.id = parseInt(item.id, 10); item.is_active = toBoolean(item.is_active); return item; }
  function normalizeStudent(item) { item.id = parseInt(item.id, 10); item.study_program_id = parseInt(item.study_program_id, 10); return item; }
  function clearMessages() { $scope.errorMessage = ""; $scope.successMessage = ""; }
  function handleError(error) { $scope.errorMessage = errorMessage(error); }
  function studentModal() { return bootstrap.Modal.getOrCreateInstance(document.getElementById("studentModal")); }
  function studentImportModal() { return bootstrap.Modal.getOrCreateInstance(document.getElementById("studentImportModal")); }

  $scope.reset(); loadData();
}

function jenisKegiatanController($scope, jenisKegiatanService) {
  $scope.datas = []; $scope.loading = true; $scope.saving = false; $scope.workingId = null; $scope.searchText = ""; $scope.errorMessage = ""; $scope.successMessage = "";
  $scope.reset = function () { $scope.model = { id: null, code: "", name: "", examiner_supported: true, is_active: true }; resetForm($scope.activityTypeForm); };
  $scope.edit = function (item) { clearMessages(); $scope.model = angular.copy(item); $scope.model.id = parseInt(item.id, 10); $scope.model.examiner_supported = toBoolean(item.examiner_supported); $scope.model.is_active = toBoolean(item.is_active); focusField("activity_type_code"); };
  $scope.save = function (form) {
    clearMessages(); if (!form || form.$invalid) { if (form) form.$setSubmitted(); return; }
    $scope.saving = true; var payload = angular.copy($scope.model); payload.code = String(payload.code || "").trim().toUpperCase();
    (payload.id ? jenisKegiatanService.put(payload) : jenisKegiatanService.post(payload)).then(function (response) { $scope.successMessage = response.message; $scope.reset(); return loadData(); }).catch(handleError).finally(function () { $scope.saving = false; });
  };
  $scope.activate = function (item) { clearMessages(); if (!window.confirm("Aktifkan jenis kegiatan " + item.name + "?")) return; $scope.workingId = item.id; jenisKegiatanService.activate(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; }); };
  $scope.hapus = function (item) { clearMessages(); if (!window.confirm("Hapus jenis kegiatan " + item.name + "? Data yang sudah digunakan tidak dapat dihapus.")) return; $scope.workingId = item.id; jenisKegiatanService.deleted(item).then(function (response) { $scope.successMessage = response.message; if ($scope.model.id === item.id) $scope.reset(); return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; }); };
  $scope.activeCount = function () { return $scope.datas.filter(function (item) { return item.is_active; }).length; };
  function loadData() { $scope.loading = true; return jenisKegiatanService.get().then(function (response) { $scope.datas = (response.data || []).map(function (item) { item.id = parseInt(item.id, 10); item.examiner_supported = toBoolean(item.examiner_supported); item.is_active = toBoolean(item.is_active); return item; }); }).catch(handleError).finally(function () { $scope.loading = false; }); }
  function clearMessages() { $scope.errorMessage = ""; $scope.successMessage = ""; }
  function handleError(error) { $scope.errorMessage = errorMessage(error); }
  $scope.reset(); loadData();
}

function aturanKegiatanController($scope, aturanKegiatanService) {
  $scope.datas = []; $scope.periods = []; $scope.previousPeriods = []; $scope.activePeriod = null; $scope.programs = []; $scope.activityTypes = []; $scope.loading = true; $scope.saving = false; $scope.copying = false; $scope.workingId = null; $scope.searchText = ""; $scope.errorMessage = ""; $scope.successMessage = "";
  $scope.reset = function () { $scope.model = { id: null, academic_period_id: $scope.activePeriod ? $scope.activePeriod.id : null, study_program_id: null, activity_type_id: null, min_supervisors: 1, max_supervisors: 1, min_examiners: 0, max_examiners: 0, examiner_optional: false, is_active: true, notes: "" }; resetForm($scope.activityRuleForm); };
  $scope.openCreate = function () { clearMessages(); $scope.reset(); ruleModal().show(); };
  $scope.openCopy = function () { clearMessages(); $scope.copySourcePeriodId = $scope.previousPeriods.length ? $scope.previousPeriods[0].id : null; copyModal().show(); };
  $scope.copyPrevious = function (form) {
    clearMessages(); if (!form || form.$invalid || !$scope.copySourcePeriodId) { if (form) form.$setSubmitted(); return; }
    $scope.copying = true;
    aturanKegiatanService.copyPrevious($scope.copySourcePeriodId).then(function (response) { $scope.successMessage = response.message; copyModal().hide(); return loadData(); }).catch(handleError).finally(function () { $scope.copying = false; });
  };
  $scope.edit = function (item) {
    clearMessages(); resetForm($scope.activityRuleForm); $scope.model = angular.copy(item);
    ["id","academic_period_id","study_program_id","activity_type_id","min_supervisors","max_supervisors","min_examiners","max_examiners"].forEach(function (field) { $scope.model[field] = parseInt(item[field], 10); });
    $scope.model.examiner_optional = toBoolean(item.examiner_optional); $scope.model.is_active = toBoolean(item.is_active); ruleModal().show();
  };
  $scope.save = function (form) {
    clearMessages(); if (!form || form.$invalid || $scope.model.max_supervisors < $scope.model.min_supervisors || $scope.model.max_examiners < $scope.model.min_examiners) { if (form) form.$setSubmitted(); return; }
    $scope.saving = true; var payload = angular.copy($scope.model);
    (payload.id ? aturanKegiatanService.put(payload) : aturanKegiatanService.post(payload)).then(function (response) { $scope.successMessage = response.message; ruleModal().hide(); $scope.reset(); return loadData(); }).catch(handleError).finally(function () { $scope.saving = false; });
  };
  $scope.activate = function (item) { clearMessages(); if (!window.confirm("Aktifkan kembali aturan " + item.program_code + " — " + item.activity_name + "?")) return; $scope.workingId = item.id; aturanKegiatanService.activate(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; }); };
  $scope.hapus = function (item) { clearMessages(); if (!window.confirm("Hapus aturan " + item.program_code + " — " + item.activity_name + "?")) return; $scope.workingId = item.id; aturanKegiatanService.deleted(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; }); };
  $scope.onActivityTypeChange = function () { var selected = $scope.activityTypes.find(function (item) { return item.id === $scope.model.activity_type_id; }); if (selected && !selected.examiner_supported) { $scope.model.min_examiners = 0; $scope.model.max_examiners = 0; $scope.model.examiner_optional = false; } };
  $scope.examinerEnabled = function () { var selected = $scope.activityTypes.find(function (item) { return item.id === $scope.model.activity_type_id; }); return !selected || selected.examiner_supported; };
  $scope.activeCount = function () { return $scope.datas.filter(function (item) { return item.is_active; }).length; };
  function loadData() { $scope.loading = true; return aturanKegiatanService.get().then(function (response) { var data = response.data || {}; $scope.datas = (data.rules || []).map(normalizeRule); $scope.activePeriod = data.activePeriod || null; if ($scope.activePeriod) $scope.activePeriod.id = parseInt($scope.activePeriod.id, 10); $scope.periods = (data.periods || []).map(normalizePeriodOption); $scope.previousPeriods = (data.previousPeriods || []).map(function (item) { item.id = parseInt(item.id, 10); item.rule_count = parseInt(item.rule_count || 0, 10); return item; }); $scope.programs = (data.programs || []).map(normalizeActiveOption); $scope.activityTypes = (data.activityTypes || []).map(normalizeActivityOption); }).catch(handleError).finally(function () { $scope.loading = false; }); }
  function normalizeRule(item) { ["id","academic_period_id","study_program_id","activity_type_id","min_supervisors","max_supervisors","min_examiners","max_examiners"].forEach(function (field) { item[field] = parseInt(item[field], 10); }); item.examiner_optional = toBoolean(item.examiner_optional); item.is_active = toBoolean(item.is_active); return item; }
  function normalizePeriodOption(item) { item.id = parseInt(item.id, 10); item.is_active = toBoolean(item.is_active); item.academic_year_active = toBoolean(item.academic_year_active); item.available = item.is_active && item.academic_year_active; return item; }
  function normalizeActiveOption(item) { item.id = parseInt(item.id, 10); item.is_active = toBoolean(item.is_active); return item; }
  function normalizeActivityOption(item) { item = normalizeActiveOption(item); item.examiner_supported = toBoolean(item.examiner_supported); return item; }
  function clearMessages() { $scope.errorMessage = ""; $scope.successMessage = ""; }
  function handleError(error) { $scope.errorMessage = errorMessage(error); }
  function ruleModal() { return bootstrap.Modal.getOrCreateInstance(document.getElementById("activityRuleModal")); }
  function copyModal() { return bootstrap.Modal.getOrCreateInstance(document.getElementById("copyActivityRuleModal")); }
  $scope.reset(); loadData();
}

function metodePembayaranController($scope, metodePembayaranService) {
  $scope.datas = []; $scope.loading = true; $scope.saving = false; $scope.workingId = null; $scope.searchText = ""; $scope.errorMessage = ""; $scope.successMessage = "";
  $scope.reset = function () { $scope.model = { id: null, code: "", name: "", is_active: true }; resetForm($scope.paymentMethodForm); };
  $scope.edit = function (item) { clearMessages(); $scope.model = angular.copy(item); $scope.model.id = parseInt(item.id, 10); $scope.model.is_active = toBoolean(item.is_active); focusField("payment_method_code"); };
  $scope.save = function (form) {
    clearMessages(); if (!form || form.$invalid) { if (form) form.$setSubmitted(); return; }
    $scope.saving = true; var payload = angular.copy($scope.model); payload.code = String(payload.code || "").trim().toUpperCase(); payload.name = String(payload.name || "").trim();
    (payload.id ? metodePembayaranService.put(payload) : metodePembayaranService.post(payload)).then(function (response) { $scope.successMessage = response.message; $scope.reset(); return loadData(); }).catch(handleError).finally(function () { $scope.saving = false; });
  };
  $scope.activate = function (item) { clearMessages(); if (!window.confirm("Aktifkan metode pembayaran " + item.name + "?")) return; $scope.workingId = item.id; metodePembayaranService.activate(item).then(function (response) { $scope.successMessage = response.message; return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; }); };
  $scope.hapus = function (item) { clearMessages(); if (!window.confirm("Hapus metode pembayaran " + item.name + "? Metode yang sudah digunakan tidak dapat dihapus.")) return; $scope.workingId = item.id; metodePembayaranService.deleted(item).then(function (response) { $scope.successMessage = response.message; if ($scope.model.id === item.id) $scope.reset(); return loadData(); }).catch(handleError).finally(function () { $scope.workingId = null; }); };
  $scope.activeCount = function () { return $scope.datas.filter(function (item) { return item.is_active; }).length; };
  function loadData() { $scope.loading = true; return metodePembayaranService.get().then(function (response) { $scope.datas = (response.data || []).map(function (item) { item.id = parseInt(item.id, 10); item.is_active = toBoolean(item.is_active); return item; }); }).catch(handleError).finally(function () { $scope.loading = false; }); }
  function clearMessages() { $scope.errorMessage = ""; $scope.successMessage = ""; }
  function handleError(error) { $scope.errorMessage = errorMessage(error); }
  $scope.reset(); loadData();
}

function tarifController($scope,tarifService){
  $scope.datas=[];$scope.programs=[];$scope.activityTypes=[];$scope.examPaths=[];$scope.activePeriod=null;$scope.loading=true;$scope.saving=false;$scope.workingId=null;$scope.searchText="";$scope.errorMessage="";$scope.successMessage="";
  $scope.reset=function(){ $scope.model={id:null,study_program_id:null,activity_type_id:null,exam_path_id:null,effective_start_date:null,effective_end_date:null,notes:"",items:[emptyItem()]};resetForm($scope.feeForm);};
  $scope.openCreate=function(){clear();$scope.reset();feeModal().show();};
  $scope.revise=function(item){clear();$scope.saving=true;tarifService.detail(item.id).then(function(r){var d=r.data||{};$scope.model={id:d.id,study_program_id:parseInt(d.study_program_id,10),activity_type_id:parseInt(d.activity_type_id,10),exam_path_id:parseInt(d.exam_path_id,10),effective_start_date:parseDate(d.effective_start_date),effective_end_date:parseDate(d.effective_end_date),notes:d.notes||"",items:(d.items||[]).map(function(x){return{code:x.item_code,name:x.item_name,amount:parseFloat(x.amount),is_required:toBoolean(x.is_required)};})};feeModal().show();}).catch(error).finally(function(){$scope.saving=false;});};
  $scope.addItem=function(){$scope.model.items.push(emptyItem());};$scope.removeItem=function(i){if($scope.model.items.length>1)$scope.model.items.splice(i,1);};
  $scope.total=function(){return($scope.model.items||[]).reduce(function(t,x){return t+(parseFloat(x.amount)||0);},0);};$scope.rupiah=function(v){return"Rp "+new Intl.NumberFormat("id-ID",{maximumFractionDigits:0}).format(v||0);};
  $scope.save=function(form){clear();if(!form||form.$invalid||$scope.total()<=0){if(form)form.$setSubmitted();return;}$scope.saving=true;var p=angular.copy($scope.model);p.effective_start_date=formatDate(p.effective_start_date);p.effective_end_date=formatDate(p.effective_end_date);p.items=p.items.map(function(x,i){x.sort_order=i+1;return x;});tarifService.post(p).then(function(r){$scope.successMessage=r.message;feeModal().hide();return load();}).catch(error).finally(function(){$scope.saving=false;});};
  $scope.deactivate=function(x){clear();if(!confirm("Nonaktifkan tarif "+x.program_code+" — "+x.activity_name+"?"))return;$scope.workingId=x.id;tarifService.deactivate(x).then(function(r){$scope.successMessage=r.message;return load();}).catch(error).finally(function(){$scope.workingId=null;});};
  function load(){$scope.loading=true;return tarifService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod||null;$scope.datas=(d.settings||[]).map(function(x){x.id=parseInt(x.id,10);x.total_amount=parseFloat(x.total_amount);return x;});$scope.programs=(d.programs||[]).map(option);$scope.activityTypes=(d.activityTypes||[]).map(option);$scope.examPaths=(d.examPaths||[]).map(option);}).catch(error).finally(function(){$scope.loading=false;});}
  function option(x){x.id=parseInt(x.id,10);x.is_active=toBoolean(x.is_active);return x;}function emptyItem(){return{code:"",name:"",amount:null,is_required:true};}function clear(){$scope.errorMessage="";$scope.successMessage="";}function error(e){$scope.errorMessage=errorMessage(e);}function feeModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("feeModal"));}$scope.reset();load();
}

function documentTemplateController($scope,documentTemplateService){$scope.templates=[];$scope.fields=[];$scope.activityTypes=[];$scope.documentTypes=[];$scope.sources=[];$scope.selected=null;$scope.model={activity_type_id:null,document_type:'BERITA_ACARA_TUNGGAL'};$scope.file=null;$scope.sourceAttributes={};$scope.sourceAttributesByActivity={};$scope.sourceOptions=function(source){var map=$scope.sourceAttributesByActivity[String($scope.selected&&$scope.selected.activity_type_id)]||$scope.sourceAttributes;return map[source]||{};};$scope.loading=true;$scope.saving=false;$scope.errorMessage='';$scope.successMessage='';$scope.selectFile=function(files){$scope.file=files&&files.length?files[0]:null;};$scope.reupload=function(t){$scope.model.activity_type_id=parseInt(t.activity_type_id,10);$scope.model.document_type=t.document_type;$scope.file=null;var input=document.getElementById('document_template_file');if(input)input.value='';window.scrollTo({top:0,behavior:'smooth'});};$scope.upload=function(form){$scope.errorMessage='';if(!$scope.file||!$scope.model.activity_type_id||!$scope.model.document_type){if(form)form.$setSubmitted();return;}$scope.saving=true;documentTemplateService.upload($scope.model,$scope.file).then(function(r){$scope.successMessage=r.message;$scope.file=null;return $scope.loadWithFields();}).catch(function(e){$scope.errorMessage=errorMessage(e);}).finally(function(){$scope.saving=false;});};$scope.editFields=function(t){$scope.selected=t;$scope.fields=($scope.allFields||[]).filter(function(x){return parseInt(x.template_id,10)===parseInt(t.id,10);});bootstrap.Modal.getOrCreateInstance(document.getElementById('templateFieldsModal')).show();};$scope.addField=function(){$scope.fields.push({field_key:'',label:'',source_type:'MANUAL',source_key:'',input_type:'TEXT',is_required:false});};$scope.removeField=function(i){$scope.fields.splice(i,1);};$scope.saveFields=function(){if(!$scope.selected)return;$scope.saving=true;documentTemplateService.saveFields($scope.selected.id,$scope.fields).then(function(r){$scope.successMessage=r.message;bootstrap.Modal.getOrCreateInstance(document.getElementById('templateFieldsModal')).hide();}).catch(function(e){$scope.errorMessage=errorMessage(e);}).finally(function(){$scope.saving=false;});};$scope.loadWithFields=function(){return documentTemplateService.get().then(function(r){var d=r.data||{};$scope.templates=d.templates||[];$scope.allFields=d.fields||[];$scope.activityTypes=d.activityTypes||[];$scope.documentTypes=d.documentTypes||[];$scope.sources=d.sources||[];$scope.sourceAttributes=d.sourceAttributes||{};$scope.sourceAttributesByActivity=d.sourceAttributesByActivity||{};}).catch(function(e){$scope.errorMessage=errorMessage(e);}).finally(function(){$scope.loading=false;});};$scope.loadWithFields();}

function kegiatanMahasiswaController($scope,kegiatanMahasiswaService){
  var cfg=window.SITARA_STUDENT_ACTIVITY_CONFIG||{};$scope.statuses=cfg.statuses||["DRAFT","TERJADWAL","SELESAI","CANCELLED"];$scope.statusTab="ACTIVE";$scope.allDatas=[];$scope.activityHistory=[];$scope.datas=[];$scope.students=[];$scope.activityTypes=[];$scope.examPaths=[];$scope.lecturers=[];$scope.rules=[];$scope.activeRule=null;$scope.supervisorSlots=[];$scope.examinerSlots=[];$scope.activePeriod=null;$scope.loading=true;$scope.saving=false;$scope.importing=false;$scope.importFile=null;$scope.workingId=null;$scope.searchText="";$scope.errorMessage="";$scope.successMessage="";
  $scope.reset=function(){$scope.model={id:null,student_id:null,activity_type_id:null,exam_path_id:null,attempt_no:1,title:"",scheduled_at:null,status:"DRAFT",notes:"",locked:false};$scope.activeRule=null;$scope.supervisorSlots=[];$scope.examinerSlots=[];resetForm($scope.studentActivityForm);};
  $scope.openCreate=function(){clear();$scope.reset();activityModal().show();};
  $scope.syncRule=function(){var student=findById($scope.students,$scope.model.student_id);$scope.activeRule=(student&&$scope.activePeriod)?$scope.rules.find(function(r){return r.academic_period_id===parseInt($scope.activePeriod.id,10)&&r.study_program_id===parseInt(student.study_program_id,10)&&r.activity_type_id===parseInt($scope.model.activity_type_id,10);})||null:null;syncAttempt();buildSlots([]);};
  $scope.edit=function(x){clear();if(isTerminal(x)){$scope.errorMessage="Kegiatan yang sudah selesai atau dibatalkan tidak dapat diubah.";return;}$scope.saving=true;kegiatanMahasiswaService.detail(x.id).then(function(r){var d=r.data||{};$scope.model=angular.copy(d);["id","student_id","activity_type_id","exam_path_id","attempt_no"].forEach(function(f){$scope.model[f]=parseInt(d[f],10);});$scope.model.scheduled_at=parseDateTime(d.scheduled_at);$scope.model.locked=toBoolean(d.locked);$scope.activeRule=d.rule?parseRule(d.rule):null;buildSlots(d.assignments||[]);activityModal().show();}).catch(error).finally(function(){$scope.saving=false;});};
  $scope.save=function(form){clear();if(!form||form.$invalid){if(form)form.$setSubmitted();$scope.errorMessage="Mohon lengkapi semua isian wajib yang ditandai * sebelum menyimpan.";return;}if(!$scope.activeRule){$scope.errorMessage="Aturan kegiatan aktif belum tersedia untuk mahasiswa dan jenis kegiatan yang dipilih.";return;}$scope.saving=true;var p=angular.copy($scope.model);p.scheduled_at=formatDateTime(p.scheduled_at);p.assignments=$scope.supervisorSlots.concat($scope.examinerSlots).filter(function(x){return x.lecturer_id;}).map(function(x){return{lecturer_id:parseInt(x.lecturer_id,10),role_type:x.role_type,position_no:x.position_no};});(p.id?kegiatanMahasiswaService.put(p):kegiatanMahasiswaService.post(p)).then(function(r){$scope.successMessage=r.message;activityModal().hide();return load();}).catch(error).finally(function(){$scope.saving=false;});};
  $scope.hapus=function(x){clear();if(!confirm("Hapus kegiatan "+x.activity_no+"?"))return;$scope.workingId=x.id;kegiatanMahasiswaService.deleted(x).then(function(r){$scope.successMessage=r.message;return load();}).catch(error).finally(function(){$scope.workingId=null;});};
  $scope.openImport=function(){clear();$scope.importFile=null;var input=document.getElementById("activity_import_file");if(input)input.value="";bootstrap.Modal.getOrCreateInstance(document.getElementById("studentActivityImportModal")).show();};
  $scope.selectImportFile=function(files){$scope.importFile=files&&files.length?files[0]:null;};
  $scope.importExcel=function(form){clear();if(!$scope.importFile){if(form)form.$setSubmitted();return;}$scope.importing=true;kegiatanMahasiswaService.importFile($scope.importFile).then(function(r){$scope.successMessage=r.message;bootstrap.Modal.getOrCreateInstance(document.getElementById("studentActivityImportModal")).hide();return load();}).catch(error).finally(function(){$scope.importing=false;});};
  $scope.selectedIds=[];$scope.selectAllScheduled=function(){$scope.selectedIds=($scope.filteredDatas||[]).filter(function(x){return !!x.scheduled_at;}).map(function(x){return parseInt(x.id,10);});};$scope.isSelected=function(x){return $scope.selectedIds.indexOf(parseInt(x.id,10))>=0;};$scope.toggleSelected=function(x){var id=parseInt(x.id,10),i=$scope.selectedIds.indexOf(id);if(i>=0)$scope.selectedIds.splice(i,1);else $scope.selectedIds.push(id);};$scope.printDocument=function(x,kind){if(!x||!x.scheduled_at){$scope.errorMessage="Kegiatan belum memiliki jadwal ujian.";return;}window.open(kegiatanMahasiswaService.documentUrl(parseInt(x.id,10),kind==="nilai"?"nilai":"tunggal"),"_blank");};$scope.printTeam=function(){if($scope.selectedIds.length<2){$scope.errorMessage="Pilih minimal dua mahasiswa yang sudah memiliki jadwal untuk berita acara tim.";return;}kegiatanMahasiswaService.teamDocument($scope.selectedIds).then(function(r){var blob=r.data||r;var url=URL.createObjectURL(blob);var a=document.createElement("a");a.href=url;a.download="berita-acara-tim.docx";a.click();setTimeout(function(){URL.revokeObjectURL(url);},1000);}).catch(error);};$scope.printBeritaAcara=function(){if($scope.selectedIds.length===1){$scope.printDocument({id:$scope.selectedIds[0],scheduled_at:true},"tunggal");return;}$scope.printTeam();};
  $scope.statusLabel=function(s){return{DRAFT:"Draft",TERJADWAL:"Terjadwal",SELESAI:"Selesai",CANCELLED:"Dibatalkan"}[s]||s;};
  $scope.setStatusTab=function(tab){$scope.statusTab=tab;applyStatusTab();};$scope.statusCount=function(tab){return $scope.allDatas.filter(function(x){return tab==="ACTIVE"?(x.status==="DRAFT"||x.status==="TERJADWAL"):x.status===tab;}).length;};$scope.isTerminal=isTerminal;
  $scope.isLecturerUsed=function(lecturerId,current){if(!lecturerId)return false;return $scope.supervisorSlots.concat($scope.examinerSlots).some(function(x){return x!==current&&parseInt(x.lecturer_id,10)===parseInt(lecturerId,10);});};
  function buildSlots(assignments){assignments=(assignments||[]).map(function(x){return{lecturer_id:parseInt(x.lecturer_id,10),role_type:x.role_type,position_no:parseInt(x.position_no,10)};});$scope.supervisorSlots=slots("PEMBIMBING",$scope.activeRule?parseInt($scope.activeRule.max_supervisors,10):0,assignments);$scope.examinerSlots=slots("PENGUJI",$scope.activeRule?parseInt($scope.activeRule.max_examiners,10):0,assignments);}function slots(role,max,assignments){var out=[];for(var i=1;i<=max;i++){var found=assignments.find(function(x){return x.role_type===role&&x.position_no===i;});out.push({role_type:role,position_no:i,lecturer_id:found?found.lecturer_id:null});}return out;}function parseRule(x){["id","academic_period_id","study_program_id","activity_type_id","min_supervisors","max_supervisors","min_examiners","max_examiners"].forEach(function(k){x[k]=parseInt(x[k],10);});return x;}function findById(list,id){id=parseInt(id,10);return list.find(function(x){return x.id===id;});}
  function load(){$scope.loading=true;return kegiatanMahasiswaService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod||null;$scope.allDatas=(d.activities||[]).map(row);$scope.activityHistory=(d.activityHistory||[]).map(row);applyStatusTab();$scope.students=(d.students||[]).map(option);$scope.activityTypes=(d.activityTypes||[]).map(option);$scope.examPaths=(d.examPaths||[]).map(option);$scope.lecturers=(d.lecturers||[]).map(option);$scope.rules=(d.rules||[]).map(parseRule);}).catch(error).finally(function(){$scope.loading=false;});}function syncAttempt(){if($scope.model.id)return;var studentId=parseInt($scope.model.student_id,10),typeId=parseInt($scope.model.activity_type_id,10);var history=$scope.activityHistory.filter(function(x){return x.student_id===studentId&&x.activity_type_id===typeId;});$scope.model.attempt_no=history.length?Math.max.apply(null,history.map(function(x){return x.attempt_no;}))+1:1;}function applyStatusTab(){$scope.datas=$scope.allDatas.filter(function(x){return $scope.statusTab==="ACTIVE"?(x.status==="DRAFT"||x.status==="TERJADWAL"):x.status===$scope.statusTab;});}function isTerminal(x){return x&&((x.status==="SELESAI")||(x.status==="CANCELLED"));}function row(x){["id","student_id","activity_type_id","exam_path_id","attempt_no","supervisor_count","examiner_count"].forEach(function(f){x[f]=parseInt(x[f]||0,10);});return x;}function option(x){x.id=parseInt(x.id,10);if(x.study_program_id!==undefined)x.study_program_id=parseInt(x.study_program_id,10);return x;}function clear(){$scope.errorMessage="";$scope.successMessage="";}function error(e){$scope.errorMessage=errorMessage(e);}function activityModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("studentActivityModal"));}$scope.reset();load();
}

function tagihanMahasiswaController($scope,tagihanMahasiswaService){
  $scope.datas=[];$scope.activities=[];$scope.components=[];$scope.previewLoading=false;$scope.activePeriod=null;$scope.loading=true;$scope.saving=false;$scope.searchText="";$scope.errorMessage="";$scope.successMessage="";$scope.detailData=null;
  $scope.reset=function(){$scope.model={activity_id:null,due_date:null};$scope.components=[];resetForm($scope.billForm);};
  $scope.openCreate=function(){clear();$scope.reset();billModal().show();};
  $scope.activityChanged=function(){clear();$scope.components=[];var id=parseInt($scope.model.activity_id,10);var activity=$scope.activities.find(function(x){return x.id===id;});if(activity&&activity.scheduled_at){var scheduled=parseDateTime(activity.scheduled_at);if(scheduled){scheduled.setDate(scheduled.getDate()-1);$scope.model.due_date=scheduled;}}else{$scope.model.due_date=null;}if(!id)return;$scope.previewLoading=true;tagihanMahasiswaService.preview(id).then(function(r){$scope.components=(r.data.items||[]).map(function(x){x.id=parseInt(x.id,10);x.amount=parseFloat(x.amount);x.is_required=toBoolean(x.is_required);x.selected=x.is_required||toBoolean(x.selected);return x;});}).catch(error).finally(function(){$scope.previewLoading=false;});};
  $scope.toggleComponent=function(x){if(x.is_required){x.selected=true;return;}x.selected=!x.selected;};$scope.componentTotal=function(){return $scope.components.filter(function(x){return x.selected;}).reduce(function(t,x){return t+x.amount;},0);};
  $scope.save=function(form){clear();if(!form||form.$invalid||!$scope.components.length||$scope.componentTotal()<=0){if(form)form.$setSubmitted();return;}$scope.saving=true;var p=angular.copy($scope.model);p.due_date=formatDate(p.due_date);p.selected_item_ids=$scope.components.filter(function(x){return x.selected;}).map(function(x){return x.id;});tagihanMahasiswaService.post(p).then(function(r){$scope.successMessage=r.message;billModal().hide();return load();}).catch(error).finally(function(){$scope.saving=false;});};
  $scope.detail=function(x){clear();$scope.detailData=null;tagihanMahasiswaService.detail(x.id).then(function(r){$scope.detailData=r.data;billDetailModal().show();}).catch(error);};
  $scope.rupiah=function(v){return"Rp "+new Intl.NumberFormat("id-ID",{maximumFractionDigits:0}).format(v||0);};$scope.statusLabel=function(s){return{BELUM_DIBAYAR:"Belum dibayar",MENUNGGU:"Menunggu",SEBAGIAN:"Sebagian",LUNAS:"Lunas",DIBATALKAN:"Dibatalkan"}[s]||s;};
  function load(){$scope.loading=true;return tagihanMahasiswaService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod||null;$scope.datas=(d.bills||[]).map(row);$scope.activities=(d.activities||[]).map(row);}).catch(error).finally(function(){$scope.loading=false;});}function row(x){x.id=parseInt(x.id,10);if(x.fee_amount!==undefined)x.fee_amount=parseFloat(x.fee_amount);if(x.total_amount!==undefined)x.total_amount=parseFloat(x.total_amount);if(x.paid_amount!==undefined)x.paid_amount=parseFloat(x.paid_amount);return x;}function clear(){$scope.errorMessage="";$scope.successMessage="";}function error(e){$scope.errorMessage=errorMessage(e);}function billModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("billModal"));}function billDetailModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("billDetailModal"));}$scope.reset();load();
}

function verifikasiPembayaranController($scope,verifikasiPembayaranService){
  $scope.datas=[];$scope.bills=[];$scope.methods=[];$scope.activePeriod=null;$scope.loading=true;$scope.saving=false;$scope.workingId=null;$scope.searchText="";$scope.statusFilter="MENUNGGU";$scope.errorMessage="";$scope.successMessage="";$scope.rejectModel={};
  $scope.reset=function(){$scope.model={student_bill_id:null,payment_method_id:null,payment_date:new Date(),amount:null,reference_no:"",notes:""};resetForm($scope.paymentForm);};
  $scope.openCreate=function(){clear();$scope.reset();paymentModal().show();};$scope.selectedBill=function(){var id=parseInt($scope.model.student_bill_id,10);return $scope.bills.find(function(x){return x.id===id;})||null;};$scope.useOutstanding=function(){var x=$scope.selectedBill();if(x)$scope.model.amount=x.outstanding_amount;};
  $scope.save=function(form){clear();if(!form||form.$invalid){if(form)form.$setSubmitted();return;}$scope.saving=true;var p=angular.copy($scope.model);p.payment_date=formatDateTime(p.payment_date);verifikasiPembayaranService.post(p).then(function(r){$scope.successMessage=r.message;paymentModal().hide();return load();}).catch(error).finally(function(){$scope.saving=false;});};
    $scope.proofUrl=function(x){return verifikasiPembayaranService.proofUrl(x.id);};
    $scope.accept=function(x){clear();if(!confirm("Terima pembayaran "+x.payment_no+"? Status tagihan akan diperbarui."))return;$scope.workingId=x.id;verifikasiPembayaranService.accept(x.id).then(function(r){$scope.successMessage=r.message;return load();}).catch(error).finally(function(){$scope.workingId=null;});};
  $scope.openReject=function(x){clear();$scope.rejectModel={id:x.id,payment_no:x.payment_no,notes:""};rejectModal().show();};$scope.reject=function(form){if(!form||form.$invalid){if(form)form.$setSubmitted();return;}$scope.saving=true;verifikasiPembayaranService.reject($scope.rejectModel.id,$scope.rejectModel.notes).then(function(r){$scope.successMessage=r.message;rejectModal().hide();return load();}).catch(error).finally(function(){$scope.saving=false;});};
  $scope.rupiah=function(v){return"Rp "+new Intl.NumberFormat("id-ID",{maximumFractionDigits:0}).format(v||0);};$scope.statusLabel=function(s){return{MENUNGGU:"Menunggu",DITERIMA:"Diterima",DITOLAK:"Ditolak"}[s]||s;};
  function load(){$scope.loading=true;return verifikasiPembayaranService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod||null;$scope.datas=(d.payments||[]).map(row);$scope.bills=(d.bills||[]).map(function(x){row(x);x.outstanding_amount=x.total_amount-x.paid_amount;return x;});$scope.methods=(d.methods||[]).map(row);}).catch(error).finally(function(){$scope.loading=false;});}function row(x){x.id=parseInt(x.id,10);["amount","allocated_amount","total_amount","paid_amount"].forEach(function(k){if(x[k]!==undefined)x[k]=parseFloat(x[k]);});return x;}function clear(){$scope.errorMessage="";$scope.successMessage="";}function error(e){$scope.errorMessage=errorMessage(e);}function paymentModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("paymentModal"));}function rejectModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("rejectPaymentModal"));}$scope.reset();load();
}

function tarifHonorController($scope,tarifHonorService){$scope.datas=[];$scope.programs=[];$scope.activityTypes=[];$scope.roles=["PEMBIMBING","PENGUJI"];$scope.loading=true;$scope.saving=false;$scope.errorMessage="";$scope.successMessage="";$scope.reset=function(){$scope.model={study_program_id:null,activity_type_id:null,role_type:"PEMBIMBING",position_no:1,gross_amount:null,tax_rate_percent:0,notes:""};resetForm($scope.rateForm);};$scope.open=function(x){clear();$scope.reset();if(x){$scope.model={study_program_id:x.study_program_id?parseInt(x.study_program_id,10):null,activity_type_id:parseInt(x.activity_type_id,10),role_type:x.role_type,position_no:parseInt(x.position_no,10),gross_amount:parseFloat(x.gross_amount),tax_rate_percent:parseFloat(x.tax_rate_percent),notes:x.notes||""};}modal().show();};$scope.save=function(f){if(!f||f.$invalid){if(f)f.$setSubmitted();return;}$scope.saving=true;tarifHonorService.post($scope.model).then(function(r){$scope.successMessage=r.message;modal().hide();return load();}).catch(err).finally(function(){$scope.saving=false;});};$scope.deactivate=function(x){if(!confirm("Nonaktifkan tarif honor ini?"))return;tarifHonorService.deactivate(x.id).then(function(r){$scope.successMessage=r.message;return load();}).catch(err);};$scope.rupiah=rupiah;function load(){return tarifHonorService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod;$scope.datas=d.rates||[];$scope.programs=(d.programs||[]).map(opt);$scope.activityTypes=(d.activityTypes||[]).map(opt);}).catch(err).finally(function(){$scope.loading=false;});}function opt(x){x.id=parseInt(x.id,10);return x;}function clear(){$scope.errorMessage="";$scope.successMessage="";}function err(e){$scope.errorMessage=errorMessage(e);}function modal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("honorRateModal"));}$scope.reset();load();}
function hakHonorController($scope,hakHonorService){$scope.datas=[];$scope.loading=true;$scope.errorMessage="";$scope.successMessage="";$scope.approve=function(x){if(!confirm("Setujui hak honor "+x.full_name+"?"))return;hakHonorService.approve(x.id).then(function(r){$scope.successMessage=r.message;return load();}).catch(err);};$scope.rupiah=rupiah;function load(){return hakHonorService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod;$scope.datas=d.entitlements||[];}).catch(err).finally(function(){$scope.loading=false;});}function err(e){$scope.errorMessage=errorMessage(e);}load();}
function batchHonorController($scope,batchHonorService){$scope.datas=[];$scope.entitlements=[];$scope.methods=[];$scope.selected={};$scope.working=false;$scope.errorMessage="";$scope.successMessage="";$scope.rupiah=rupiah;$scope.toggle=function(id){$scope.selected[id]=!$scope.selected[id];};$scope.selectedIds=function(){return Object.keys($scope.selected).filter(function(k){return $scope.selected[k];}).map(Number);};$scope.selectedTotal=function(){return $scope.entitlements.filter(function(x){return $scope.selected[x.id];}).reduce(function(total,x){return total+parseFloat(x.net_amount||0);},0);};$scope.allSelected=function(){return $scope.entitlements.length>0&&$scope.selectedIds().length===$scope.entitlements.length;};$scope.toggleAll=function(){var checked=!$scope.allSelected();$scope.entitlements.forEach(function(x){$scope.selected[x.id]=checked;});};$scope.exportUrl=function(x){return x&&x.id?batchHonorService.exportUrl(x.id):"";};$scope.create=function(){var ids=$scope.selectedIds();if(!ids.length)return;clear();$scope.working=true;batchHonorService.post({entitlement_ids:ids}).then(function(r){$scope.successMessage=r.message;$scope.selected={};return load();}).catch(err).finally(function(){$scope.working=false;});};$scope.openPay=function(x){clear();$scope.payModel={id:x.id,payment_date:new Date(),payment_method_id:null,reference_no:""};payModal().show();};$scope.pay=function(f){if(!f||f.$invalid){if(f)f.$setSubmitted();return;}clear();$scope.working=true;var p=angular.copy($scope.payModel);p.payment_date=formatDate(p.payment_date);batchHonorService.pay(p.id,p).then(function(r){$scope.successMessage=r.message;payModal().hide();return load();}).catch(err).finally(function(){$scope.working=false;});};$scope.detail=function(x){clear();batchHonorService.detail(x.id).then(function(r){$scope.detailData=r.data;detailModal().show();}).catch(err);};function load(){return batchHonorService.get().then(function(r){var d=r.data||{};$scope.activePeriod=d.activePeriod;$scope.datas=(d.batches||[]).map(num);$scope.entitlements=(d.entitlements||[]).map(num);$scope.methods=(d.methods||[]).map(function(x){x.id=parseInt(x.id,10);return x;});}).catch(err);}function num(x){x.id=parseInt(x.id,10);["gross_total","tax_total","net_total","gross_amount_snapshot","tax_amount","net_amount"].forEach(function(k){if(x[k]!==undefined)x[k]=parseFloat(x[k]||0);});return x;}function clear(){$scope.errorMessage="";$scope.successMessage="";}function err(e){$scope.errorMessage=errorMessage(e);}function payModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("payHonorModal"));}function detailModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("batchDetailModal"));}load();}
function adminUserController($scope,adminUserService){$scope.datas=[];$scope.roles=["ADMIN","PRODI","KEUANGAN"];$scope.programs=window.SITARA_ADMIN_PROGRAMS||[];$scope.errorMessage="";$scope.successMessage="";$scope.reset=function(){$scope.model={id:null,username:"",email:"",full_name:"",password:"",role:"ADMIN",study_program_ids:[],is_active:true};};$scope.programNames=function(x){return(x.programs||[]).map(function(p){return p.code;}).join(", ")||"-";};$scope.open=function(x){$scope.reset();if(x){$scope.model=angular.copy(x);$scope.model.id=parseInt(x.id,10);$scope.model.is_active=toBoolean(x.is_active);$scope.model.study_program_ids=(x.programs||[]).map(function(p){return parseInt(p.id,10);});}$scope.errorMessage="";userModal().show();};$scope.save=function(f){if(!f||f.$invalid)return;($scope.model.id?adminUserService.put($scope.model):adminUserService.post($scope.model)).then(function(r){$scope.successMessage=r.message;userModal().hide();return load();}).catch(err);};$scope.openPassword=function(x){$scope.passwordModel={id:parseInt(x.id,10),name:x.full_name,password:""};passwordModal().show();};$scope.savePassword=function(f){if(!f||f.$invalid)return;adminUserService.password($scope.passwordModel.id,$scope.passwordModel.password).then(function(r){$scope.successMessage=r.message;passwordModal().hide();}).catch(err);};function load(){return adminUserService.get().then(function(r){$scope.datas=(r.data||[]).map(function(x){x.id=parseInt(x.id,10);x.is_active=toBoolean(x.is_active);return x;});}).catch(err);}function err(e){$scope.errorMessage=errorMessage(e);}function userModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("adminUserModal"));}function passwordModal(){return bootstrap.Modal.getOrCreateInstance(document.getElementById("adminPasswordModal"));}$scope.reset();load();}
function auditLogController($scope,auditLogService){$scope.datas=[];$scope.entities=[];$scope.filters={entity:"",action:""};$scope.load=function(){auditLogService.get($scope.filters).then(function(r){var d=r.data||{};$scope.datas=d.logs||[];$scope.entities=d.entities||[];}).catch(function(e){$scope.errorMessage=errorMessage(e);});};$scope.detail=function(x){auditLogService.detail(x.id).then(function(r){$scope.detailData=r.data;["old_values","new_values"].forEach(function(k){try{$scope.detailData[k]=JSON.stringify(JSON.parse($scope.detailData[k]),null,2);}catch(e){}});bootstrap.Modal.getOrCreateInstance(document.getElementById("auditDetailModal")).show();});};$scope.load();}
function rupiah(v){return"Rp "+new Intl.NumberFormat("id-ID",{maximumFractionDigits:0}).format(v||0);}

function parseDateTime(value){if(!value)return null;var d=new Date(String(value).replace(" ","T"));return isNaN(d.getTime())?null:d;}
function formatDateTime(value){if(!angular.isDate(value)||isNaN(value.getTime()))return null;return value.getFullYear()+"-"+pad(value.getMonth()+1)+"-"+pad(value.getDate())+"T"+pad(value.getHours())+":"+pad(value.getMinutes());}

function toBoolean(value) {
  return value === true || value === 1 || value === "1";
}

function parseDate(value) {
  if (!value) return null;
  var parts = String(value).split("-");
  if (parts.length !== 3) return null;
  return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
}

function formatDate(value) {
  if (!angular.isDate(value) || isNaN(value.getTime())) return null;
  return value.getFullYear() + "-" + pad(value.getMonth() + 1) + "-" + pad(value.getDate());
}

function pad(value) {
  return String(value).padStart(2, "0");
}

function errorMessage(error) {
  if (error && error.data && error.data.message) return error.data.message;
  return "Permintaan belum dapat diproses. Silakan coba lagi.";
}

function resetForm(form) {
  if (!form) return;
  form.$setPristine();
  form.$setUntouched();
}

function focusField(id) {
  window.setTimeout(function () {
    var field = document.getElementById(id);
    if (field) field.focus();
  }, 0);
}
