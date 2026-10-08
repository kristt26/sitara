angular
  .module("admin.service", [])
  .factory("dashboardServices", dashboardServices)
  .factory("tahunService", tahunService)
  .factory("periodeService", periodeService)
  .factory("programStudiService", programStudiService)
  .factory("dosenService", dosenService)
  .factory("mahasiswaService", mahasiswaService)
  .factory("jenisKegiatanService", jenisKegiatanService)
  .factory("aturanKegiatanService", aturanKegiatanService)
  .factory("metodePembayaranService", metodePembayaranService)
  .factory("tarifService", tarifService)
  .factory("kegiatanMahasiswaService", kegiatanMahasiswaService)
  .factory("documentTemplateService", documentTemplateService)
  .factory("tagihanMahasiswaService", tagihanMahasiswaService)
  .factory("verifikasiPembayaranService", verifikasiPembayaranService)
  .factory("tarifHonorService", tarifHonorService).factory("hakHonorService", hakHonorService).factory("batchHonorService", batchHonorService).factory("adminUserService", adminUserService).factory("auditLogService", auditLogService);

function dashboardServices($http, helperServices) {
  return {
    get: function () {
      return $http.get(joinUrl(helperServices.url, "dashboard/read")).then(function (response) {
        return response.data && response.data.data ? response.data.data : response.data;
      });
    },
  };
}

function tahunService($http, helperServices) {
  var configuredUrl = window.SITARA_PERIOD_CONFIG && window.SITARA_PERIOD_CONFIG.baseUrl;
  var controller = trimTrailingSlash(configuredUrl || joinUrl(helperServices.url, "periode"));

  return {
    get: function () {
      return apiRequest($http, { method: "GET", url: controller + "/read" });
    },
    post: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/post",
        data: param,
        headers: mutationHeaders(),
      });
    },
    put: function (param) {
      return apiRequest($http, {
        method: "PUT",
        url: controller + "/put/" + param.id,
        data: param,
        headers: mutationHeaders(),
      });
    },
    activate: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/aktif/" + param.id,
        data: {},
        headers: mutationHeaders(),
      });
    },
    deleted: function (param) {
      return apiRequest($http, {
        method: "DELETE",
        url: controller + "/delete/" + param.id,
        headers: mutationHeaders(),
      });
    },
  };
}

function periodeService($http, helperServices) {
  var config = window.SITARA_PERIOD_CONFIG || {};
  var baseUrl = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "periode"));
  var yearId = parseInt(config.yearId || 0, 10);
  var controller = baseUrl + "/" + yearId;

  return {
    get: function () {
      return apiRequest($http, { method: "GET", url: controller + "/read" });
    },
    post: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/post",
        data: param,
        headers: mutationHeaders(),
      });
    },
    put: function (param) {
      return apiRequest($http, {
        method: "PUT",
        url: controller + "/put/" + param.id,
        data: param,
        headers: mutationHeaders(),
      });
    },
    activate: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/aktif/" + param.id,
        data: {},
        headers: mutationHeaders(),
      });
    },
    deleted: function (param) {
      return apiRequest($http, {
        method: "DELETE",
        url: controller + "/delete/" + param.id,
        headers: mutationHeaders(),
      });
    },
  };
}

function programStudiService($http, helperServices) {
  var config = window.SITARA_PROGRAM_CONFIG || {};
  var controller = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "program-studi"));

  return {
    get: function () {
      return apiRequest($http, { method: "GET", url: controller + "/read" });
    },
    post: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/post",
        data: param,
        headers: mutationHeaders(),
      });
    },
    put: function (param) {
      return apiRequest($http, {
        method: "PUT",
        url: controller + "/put/" + param.id,
        data: param,
        headers: mutationHeaders(),
      });
    },
    activate: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/aktif/" + param.id,
        data: {},
        headers: mutationHeaders(),
      });
    },
    deleted: function (param) {
      return apiRequest($http, {
        method: "DELETE",
        url: controller + "/delete/" + param.id,
        headers: mutationHeaders(),
      });
    },
  };
}

function dosenService($http, helperServices) {
  var config = window.SITARA_LECTURER_CONFIG || {};
  var controller = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "master/dosen"));

  return {
    get: function () {
      return apiRequest($http, { method: "GET", url: controller + "/read" });
    },
    post: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/post",
        data: param,
        headers: mutationHeaders(),
      });
    },
    put: function (param) {
      return apiRequest($http, {
        method: "PUT",
        url: controller + "/put/" + param.id,
        data: param,
        headers: mutationHeaders(),
      });
    },
    importFile: function (file) {
      var formData = new FormData();
      formData.append("file", file);

      return apiRequest($http, {
        method: "POST",
        url: controller + "/import",
        data: formData,
        transformRequest: angular.identity,
        headers: uploadHeaders(),
      });
    },
    activate: function (param) {
      return apiRequest($http, {
        method: "POST",
        url: controller + "/aktif/" + param.id,
        data: {},
        headers: mutationHeaders(),
      });
    },
    deleted: function (param) {
      return apiRequest($http, {
        method: "DELETE",
        url: controller + "/delete/" + param.id,
        headers: mutationHeaders(),
      });
    },
  };
}

function mahasiswaService($http, helperServices) {
  var config = window.SITARA_STUDENT_CONFIG || {};
  var controller = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "master/mahasiswa"));

  return {
    get: function () { return apiRequest($http, { method: "GET", url: controller + "/read" }); },
    post: function (param) { return apiRequest($http, { method: "POST", url: controller + "/post", data: param, headers: mutationHeaders() }); },
    put: function (param) { return apiRequest($http, { method: "PUT", url: controller + "/put/" + param.id, data: param, headers: mutationHeaders() }); },
    activate: function (param) { return apiRequest($http, { method: "POST", url: controller + "/aktif/" + param.id, data: {}, headers: mutationHeaders() }); },
    activation: function (param) { return apiRequest($http, { method: "POST", url: controller + "/aktivasi/" + param.id, data: {}, headers: mutationHeaders() }); },
    deleted: function (param) { return apiRequest($http, { method: "DELETE", url: controller + "/delete/" + param.id, headers: mutationHeaders() }); },
    importFile: function (file) {
      var formData = new FormData();
      formData.append("file", file);
      return apiRequest($http, { method: "POST", url: controller + "/import", data: formData, transformRequest: angular.identity, headers: uploadHeaders() });
    },
  };
}

function jenisKegiatanService($http, helperServices) {
  var config = window.SITARA_ACTIVITY_TYPE_CONFIG || {};
  var controller = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "kegiatan"));
  return {
    get: function () { return apiRequest($http, { method: "GET", url: controller + "/read" }); },
    post: function (param) { return apiRequest($http, { method: "POST", url: controller + "/post", data: param, headers: mutationHeaders() }); },
    put: function (param) { return apiRequest($http, { method: "PUT", url: controller + "/put/" + param.id, data: param, headers: mutationHeaders() }); },
    activate: function (param) { return apiRequest($http, { method: "POST", url: controller + "/aktif/" + param.id, data: {}, headers: mutationHeaders() }); },
    deleted: function (param) { return apiRequest($http, { method: "DELETE", url: controller + "/delete/" + param.id, headers: mutationHeaders() }); },
  };
}

function aturanKegiatanService($http, helperServices) {
  var config = window.SITARA_ACTIVITY_RULE_CONFIG || {};
  var controller = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "aturan-kegiatan"));
  return {
    get: function () { return apiRequest($http, { method: "GET", url: controller + "/read" }); },
    post: function (param) { return apiRequest($http, { method: "POST", url: controller + "/post", data: param, headers: mutationHeaders() }); },
    put: function (param) { return apiRequest($http, { method: "PUT", url: controller + "/put/" + param.id, data: param, headers: mutationHeaders() }); },
    activate: function (param) { return apiRequest($http, { method: "POST", url: controller + "/aktif/" + param.id, data: {}, headers: mutationHeaders() }); },
    deleted: function (param) { return apiRequest($http, { method: "DELETE", url: controller + "/delete/" + param.id, headers: mutationHeaders() }); },
    copyPrevious: function (sourcePeriodId) { return apiRequest($http, { method: "POST", url: controller + "/copy-previous", data: { source_period_id: sourcePeriodId }, headers: mutationHeaders() }); },
  };
}

function metodePembayaranService($http, helperServices) {
  var config = window.SITARA_PAYMENT_METHOD_CONFIG || {};
  var controller = trimTrailingSlash(config.baseUrl || joinUrl(helperServices.url, "keuangan/metode-pembayaran"));
  return {
    get: function () { return apiRequest($http, { method: "GET", url: controller + "/read" }); },
    post: function (param) { return apiRequest($http, { method: "POST", url: controller + "/post", data: param, headers: mutationHeaders() }); },
    put: function (param) { return apiRequest($http, { method: "PUT", url: controller + "/put/" + param.id, data: param, headers: mutationHeaders() }); },
    activate: function (param) { return apiRequest($http, { method: "POST", url: controller + "/aktif/" + param.id, data: {}, headers: mutationHeaders() }); },
    deleted: function (param) { return apiRequest($http, { method: "DELETE", url: controller + "/delete/" + param.id, headers: mutationHeaders() }); },
  };
}
function tarifService($http,helperServices){var c=trimTrailingSlash((window.SITARA_FEE_CONFIG||{}).baseUrl||joinUrl(helperServices.url,"keuangan/tarif"));return{get:function(){return apiRequest($http,{method:"GET",url:c+"/read"});},detail:function(id){return apiRequest($http,{method:"GET",url:c+"/detail/"+id});},post:function(p){return apiRequest($http,{method:"POST",url:c+"/post",data:p,headers:mutationHeaders()});},deactivate:function(p){return apiRequest($http,{method:"POST",url:c+"/nonaktif/"+p.id,data:{},headers:mutationHeaders()});}};}
function kegiatanMahasiswaService($http,helperServices){var c=trimTrailingSlash((window.SITARA_STUDENT_ACTIVITY_CONFIG||{}).baseUrl||joinUrl(helperServices.url,"kegiatan-mahasiswa"));return{get:function(){return apiRequest($http,{method:"GET",url:c+"/read"});},detail:function(id){return apiRequest($http,{method:"GET",url:c+"/detail/"+id});},post:function(p){return apiRequest($http,{method:"POST",url:c+"/post",data:p,headers:mutationHeaders()});},put:function(p){return apiRequest($http,{method:"PUT",url:c+"/put/"+p.id,data:p,headers:mutationHeaders()});},deleted:function(p){return apiRequest($http,{method:"DELETE",url:c+"/delete/"+p.id,headers:mutationHeaders()});},importFile:function(file){var data=new FormData();data.append("file",file);return apiRequest($http,{method:"POST",url:c+"/import",data:data,transformRequest:angular.identity,headers:uploadHeaders()});},teamDocument:function(ids){return apiRequest($http,{method:"POST",url:c+"/dokumen/tim",data:{ids:ids},headers:mutationHeaders(),responseType:"blob"});},documentUrl:function(id,kind){return c+"/dokumen/"+id+"/"+kind;}};}
function tagihanMahasiswaService($http,helperServices){var c=trimTrailingSlash((window.SITARA_BILL_CONFIG||{}).baseUrl||joinUrl(helperServices.url,"keuangan/tagihan"));return{get:function(){return apiRequest($http,{method:"GET",url:c+"/read"});},preview:function(id){return apiRequest($http,{method:"GET",url:c+"/preview/"+id});},detail:function(id){return apiRequest($http,{method:"GET",url:c+"/detail/"+id});},post:function(p){return apiRequest($http,{method:"POST",url:c+"/post",data:p,headers:mutationHeaders()});}};}
function verifikasiPembayaranService($http,helperServices){var c=trimTrailingSlash((window.SITARA_PAYMENT_VERIFICATION_CONFIG||{}).baseUrl||joinUrl(helperServices.url,"keuangan/verifikasi"));return{get:function(){return apiRequest($http,{method:"GET",url:c+"/read"});},proofUrl:function(id){return c+"/bukti/"+id;},post:function(p){return apiRequest($http,{method:"POST",url:c+"/post",data:p,headers:mutationHeaders()});},accept:function(id){return apiRequest($http,{method:"POST",url:c+"/terima/"+id,data:{},headers:mutationHeaders()});},reject:function(id,notes){return apiRequest($http,{method:"POST",url:c+"/tolak/"+id,data:{notes:notes},headers:mutationHeaders()});}};}
function simpleApi($http,helperServices,key,path){var c=trimTrailingSlash((window[key]||{}).baseUrl||joinUrl(helperServices.url,path));return{base:c,get:function(q){return apiRequest($http,{method:"GET",url:c+"/read",params:q});},post:function(p){return apiRequest($http,{method:"POST",url:c+"/post",data:p,headers:mutationHeaders()});}};}
function tarifHonorService($http,helperServices){var a=simpleApi($http,helperServices,"SITARA_HONOR_RATE_CONFIG","honor/tarif");a.deactivate=function(id){return apiRequest($http,{method:"POST",url:a.base+"/nonaktif/"+id,data:{},headers:mutationHeaders()});};return a;}
function hakHonorService($http,helperServices){var c=trimTrailingSlash((window.SITARA_HONOR_ENTITLEMENT_CONFIG||{}).baseUrl||joinUrl(helperServices.url,"honor/hak"));return{get:function(){return apiRequest($http,{method:"GET",url:c+"/read"});},approve:function(id){return apiRequest($http,{method:"POST",url:c+"/setujui/"+id,data:{},headers:mutationHeaders()});}};}
function batchHonorService($http,helperServices){var a=simpleApi($http,helperServices,"SITARA_HONOR_BATCH_CONFIG","honor/pembayaran");a.detail=function(id){return apiRequest($http,{method:"GET",url:a.base+"/detail/"+id});};a.pay=function(id,p){return apiRequest($http,{method:"POST",url:a.base+"/bayar/"+id,data:p,headers:mutationHeaders()});};a.exportUrl=function(id){return a.base+"/export/"+id;};return a;}
function adminUserService($http,helperServices){var a=simpleApi($http,helperServices,"SITARA_ADMIN_USER_CONFIG","pengaturan/admin");a.put=function(p){return apiRequest($http,{method:"PUT",url:a.base+"/put/"+p.id,data:p,headers:mutationHeaders()});};a.password=function(id,p){return apiRequest($http,{method:"POST",url:a.base+"/password/"+id,data:{password:p},headers:mutationHeaders()});};return a;}
function auditLogService($http,helperServices){var a=simpleApi($http,helperServices,"SITARA_AUDIT_CONFIG","audit-log");a.detail=function(id){return apiRequest($http,{method:"GET",url:a.base+"/detail/"+id});};return a;}

function documentTemplateService($http,helperServices){var c=trimTrailingSlash(joinUrl(helperServices.url,"template-dokumen"));return{get:function(){return apiRequest($http,{method:"GET",url:c+"/read"});},upload:function(model,file){var d=new FormData();d.append("activity_type_id",model.activity_type_id);d.append("document_type",model.document_type);d.append("file",file);return apiRequest($http,{method:"POST",url:c+"/upload",data:d,transformRequest:angular.identity,headers:uploadHeaders()});},saveFields:function(id,fields){return apiRequest($http,{method:"PUT",url:c+"/fields/"+id,data:{fields:fields},headers:mutationHeaders()});},downloadUrl:function(id){return c+"/download/"+id;}};}

function apiRequest($http, request) {
  return $http(request).then(
    function (response) {
      syncCsrf(response.data && response.data.csrf);
      return response.data;
    },
    function (error) {
      syncCsrf(error.data && error.data.csrf);
      throw error;
    },
  );
}

function mutationHeaders() {
  var headers = { "Content-Type": "application/json" };
  var csrf = window.SITARA_CSRF || {};

  if (csrf.header && csrf.hash) {
    headers[csrf.header] = csrf.hash;
  }

  return headers;
}

function uploadHeaders() {
  var headers = { "Content-Type": undefined };
  var csrf = window.SITARA_CSRF || {};

  if (csrf.header && csrf.hash) {
    headers[csrf.header] = csrf.hash;
  }

  return headers;
}

function syncCsrf(csrf) {
  if (csrf && csrf.header && csrf.hash) {
    window.SITARA_CSRF = csrf;
  }
}

function joinUrl(baseUrl, path) {
  return trimTrailingSlash(baseUrl || "") + "/" + String(path || "").replace(/^\/+/, "");
}

function trimTrailingSlash(value) {
  return String(value || "").replace(/\/+$/, "");
}
