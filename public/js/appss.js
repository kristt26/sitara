angular.module('appss', [
    'adminctrl',
    'helper.service',
    'admin.service',
    'auth.service',
    'naif.base64',
    'message.service',
    'ngLocale',
    'datatables',
    // 'cur.$mask',
    'ngAnimate',
    'ui.router',
    'ui.select2',
    "component"

])
    .controller('indexController', indexController)
    .directive('emaudio', emaudio)
    .controller('formController', formController)
    .config(function ($stateProvider, $urlRouterProvider) {
        $stateProvider
            .state('add', {
                url: '/add',
                templateUrl: '../../assets/apps/form.html',
                controller: 'formController'
            })
            .state('data', {
                url: '/data',
                templateUrl: '../../assets/apps/data.html',
                controller: 'formController'
            })
            .state('add.keluarga', {
                url: '/keluarga',
                templateUrl: '../../assets/apps/form-profile.html'
            })
            .state('add.anggota', {
                url: '/anggota',
                templateUrl: '../../assets/apps/form-interests.html'
            })
            .state('add.finish', {
                url: '/finish',
                templateUrl: '../../assets/apps/form-finish.html'
            });
        $urlRouterProvider.otherwise('/data');
    })
    ;


function indexController($scope, dashboardServices) {
    $scope.titleHeader = "Laboratorium Assets";
    $scope.header = "";
    $scope.breadcrumb = "";
    $scope.title;
    $scope.warning = 0;
    $scope.$on("SendUp", function (evt, data) {
        $scope.header = data;
        $scope.header = data;
        $scope.breadcrumb = data;
        $scope.title = data;
        $.LoadingOverlay("hide");
    });
    $scope.$on("send", function (evt, data) {
        $scope.warning = data;
    });
    dashboardServices.getLayanan().then(res=>{
        $scope.layanan = res;
        $scope.menuLayanan = res.baptis+res.sidi+res.nikah;
    })
}

function emaudio() {
    var template = '<div class="audio-container"><div class="album-container"><img ng-src="{{album}}" /></div><span class="audio-controls"><div class="audio-row">{{artist}} - {{title}}</div><div class="audio-row"><span class="audio-play" ng-click="play()">{{isplaying ? "&#9632;" : "&#9658;"}}</span><div class="progress" ng-mousedown="setTime($event)" ng-mouseup="reset()"><div class="bar" data-ng-style="barstyle"></div></div><span class="audio-time">{{duration}}</span></div></span></div>';
    return {
        restrict: 'E',
        template: template,
        replace: true,
        scope: {
            album: '@',
            url: '@',
            artist: '@',
            title: '@',
        },
        link: function ($scope, $element) {
            //Width of progress bar element
            $scope.timelineWidth = $element[0].querySelectorAll(".progress")[0].offsetWidth;
            $scope.audio = new Audio();
            $scope.audio.type = "audio/mpeg";
            $scope.audio.src = $scope.url;
            $scope.duration = '0:00';
            $scope.barstyle = { width: "0%" };
            $scope.isplaying = false;
            $scope.play = function () {
                if ($scope.isplaying) {
                    $scope.audio.pause();
                    $scope.isplaying = false;
                } else {
                    $scope.audio.play();
                    $scope.isplaying = true;
                }
            };


            $scope.setTime = function ($event) {
                // remove listener on audio
                $scope.audio.removeEventListener('timeupdate', timeupdate, true);

                var position = $event.clientX - $event.target.offsetLeft;

                $scope.time = (position / $scope.timelineWidth) * 100;
                $scope.audio.currentTime = ($scope.time * $scope.audio.duration) / 100;

                $scope.barstyle.width = $scope.time + "%";
            };

            $scope.reset = function () {
                $scope.audio.addEventListener('timeupdate', timeupdate);
            };

            $scope.audio.addEventListener('timeupdate', timeupdate);


            function timeupdate() {
                var sec_num = $scope.audio.currentTime;
                var minutes = Math.floor(sec_num / 60);
                var seconds = sec_num - (minutes * 60);
                if (minutes < 10) {
                    minutes = "0" + minutes;
                }
                minutes += "";
                if (seconds < 10) {
                    seconds = "0" + seconds;
                }
                seconds += "";

                var time = minutes + ':' + seconds.substring(0, 2);
                $scope.duration = time;

                $scope.barstyle.width = ($scope.audio.currentTime / $scope.audio.duration) * 100 + "%";
                $scope.$apply();
            };

        }
    };
}

function formController($scope, helperServices, keluargaServices, wilayahServices, pesan, $sce, $window, $state, DTOptionsBuilder) {
    $scope.$emit("SendUp", "Pembobotan Faktor");
    $scope.datas;
    $scope.title = "Biodata Anggota";
    $scope.model = {};
    $scope.model.anggota = [];
    $scope.button1 = true;
    $scope.head = ""
    $scope.data = [];
    $scope.penilaian = false;
    $scope.formData = {};
    $scope.provinsi = [];
    $scope.kabupaten = [];
    $scope.kecamatan = [];
    $scope.kelurahan = [];
    $scope.golonganDarah = helperServices.golonganDarah;
    $scope.agama = helperServices.agama;
    $scope.hubungan = helperServices.hubungan;
    $scope.pendidikan = helperServices.pendidikan;
    $scope.pekerjaan = helperServices.pekerjaan;
    $scope.tambahAnggota = false;
    $scope.statusEdit = false;
    $scope.dtOptions = DTOptionsBuilder.newOptions().withOption('scrollX', '100%');
    setTimeout(() => {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }, 300);
    $.LoadingOverlay("show");
    keluargaServices.get().then(res => {
        $scope.datas = res;
        var item;
        if (window.localStorage.getItem('biodata')) {
            $.LoadingOverlay("hide");
            wilayahServices.prov().then(res => {
                $scope.provinsi = res;
                $scope.model = JSON.parse(window.localStorage.getItem('biodata'));
                if($scope.model.id){
                    $scope.prov = $scope.provinsi.find(x => x.nama == $scope.model.provinsi);
                    $scope.getData('kab', $scope.provinsi.find(x => x.nama == $scope.model.provinsi).id);
                    $scope.wijk = $scope.datas.wijk.find(x => x.id == $scope.model.wijk_id);
                    $scope.ksp = $scope.wijk.ksp.find(x => x.id == $scope.model.ksp_id);
                }else{
                }
                var lastPath = helperServices.lastPath;
                if(lastPath == "data"){
                    $state.go('add.keluarga');
                }
            })
        } else {
            $.LoadingOverlay("hide");
        }
        // if (res) {
        //     $scope.datas.berkas.forEach(element => {
        //         var sp = element.file.split('.');
        //         element.ext = sp[1];
        //     });
        //     $state.go('data');
        //     console.log($scope.datas.berkas);
        // } else {
        // }
    })

    $scope.setTambah = (item) => {
        $scope.tambahAnggota = item;
        $scope.itemAnggota = {};
        $scope.baptis = {};
        $scope.sidi = {};
        $scope.nikah = {};
    }

    $scope.tambah = () => {
        var item = {};
        item.setPage = "add";
        window.localStorage.setItem('biodata', JSON.stringify(item));
        $state.go('add.keluarga');
    }

    $scope.addAnggota = () => {
        $scope.itemAnggota.tanggal_lahir = helperServices.dateToString($scope.itemAnggota.tanggal_lahir);
        $scope.baptis.tanggal_baptis ? $scope.baptis.tanggal_baptis = helperServices.dateToString($scope.baptis.tanggal_baptis) : undefined;
        $scope.sidi.tanggal_sidi ? $scope.sidi.tanggal_sidi = helperServices.dateToString($scope.sidi.tanggal_sidi) : undefined;
        $scope.nikah.tanggal_nikah ? $scope.nikah.tanggal_nikah = helperServices.dateToString($scope.nikah.tanggal_nikah) : undefined;
        $scope.itemAnggota.baptis = $scope.baptis;
        $scope.itemAnggota.sidi = $scope.sidi;
        $scope.itemAnggota.nikah = $scope.nikah;
        if (!$scope.statusEdit) {
            if(!$scope.model.anggota){
                $scope.model.anggota = [];
            }
            $scope.model.anggota.push(angular.copy($scope.itemAnggota));
        }else{
            $scope.itemAnggota.baptis.anggotakk_id = $scope.itemAnggota.id;
            $scope.itemAnggota.sidi.anggotakk_id = $scope.itemAnggota.id;
            $scope.itemAnggota.nikah.anggotakk_id = $scope.itemAnggota.id;
        }        
        $scope.itemAnggota = {};
        $scope.baptis = {};
        $scope.sidi = {};
        $scope.nikah = {};
        $scope.tambahAnggota = false;
        $scope.statusEdit = false;
    }

    $scope.removeAnggota = (item) => {
        var index = $scope.model.anggota.indexOf(item);
        $scope.model.anggota.splice(index, 1);
    }

    $scope.editAnggota = (item) => {
        $scope.itemAnggota = item;
        $scope.baptis = $scope.itemAnggota.baptis
        $scope.sidi = $scope.itemAnggota.sidi
        $scope.nikah = $scope.itemAnggota.nikah
        !$scope.baptis ? $scope.baptis={}: false;
        !$scope.sidi ? $scope.sidi={}: false;
        !$scope.nikah ? $scope.nikah={}: false;
        $scope.itemAnggota.tanggal_lahir = new Date($scope.itemAnggota.tanggal_lahir);
        $scope.baptis.tanggal_baptis ? $scope.baptis.tanggal_baptis = new Date($scope.baptis.tanggal_baptis) : undefined;
        $scope.sidi.tanggal_sidi ? $scope.sidi.tanggal_sidi = new Date($scope.sidi.tanggal_sidi) : undefined;
        $scope.nikah.tanggal_nikah ? $scope.nikah.tanggal_nikah = new Date($scope.nikah.tanggal_nikah) : undefined;
        $scope.tambahAnggota = true;
        $scope.statusEdit = true;
    }

    $scope.show = (item) => {
        // console.log(item);
    }

    $scope.getData = (set, id) => {
        if (set == 'kab') {
            wilayahServices.kab(id).then(res => {
                $scope.kabupaten = res
                // console.log($scope.kabupaten);
                if ($scope.model.kabupaten) {
                    $scope.kab = $scope.kabupaten.find(x => x.nama == $scope.model.kabupaten);
                    $scope.getData('kec', $scope.kabupaten.find(x => x.nama == $scope.model.kabupaten).id);
                }
            })
        } else if (set == 'kec') {
            wilayahServices.kec(id).then(res => {
                $scope.kecamatan = res
                if ($scope.model.kecamatan) {
                    $scope.kec = $scope.kecamatan.find(x => x.nama == $scope.model.kecamatan);
                    $scope.getData('kel', $scope.kecamatan.find(x => x.nama == $scope.model.kecamatan).id);
                }
            })
        } else {
            wilayahServices.kel(id).then(res => {
                $scope.kelurahan = res
                if ($scope.model.kelurahan) {
                    $scope.kel = $scope.kelurahan.find(x => x.nama == $scope.model.kelurahan);
                }
                $.LoadingOverlay("hide");
            })
        }
    }

    $scope.save = function () {
        pesan.dialog("Yakin ingin menlanjutkan?", "Ya", "Tidak").then(x => {
            $.LoadingOverlay("show");
            if($scope.model.setPage=="edit"){
                keluargaServices.put($scope.model).then(res => {
                    pesan.Success("Proses Berhasil");
                    window.localStorage.removeItem('biodata');
                    $.LoadingOverlay("hide");
                    $state.go('data', {}, {reload: false});
                })
            }else if($scope.model.setPage=="add"){
                keluargaServices.post($scope.model).then(res => {
                    pesan.Success("Proses Berhasil");
                    window.localStorage.removeItem('biodata');
                    $.LoadingOverlay("hide");
                    $state.go('data', {}, {reload: false});
                })
            }else{
                if($scope.model.anggota.length>1){
                    var indexKepala = $scope.model.anggota.indexOf($scope.model.anggota.find(x=x.hubungan_keluarga =="KEPALA KELUARAGA"));
                    var indexIstri = $scope.model.anggota.indexOf($scope.model.anggota.find(x=x.hubungan_keluarga =="ISTRI"));
                    if(indexKepala!=0){
                        var element = $scope.model.anggota[indexKepala];
                        $scope.model.anggota.splice(indexKepala, 1);
                        $scope.model.anggota.splice(indexIstri, 0, element);
                    }
                }
                keluargaServices.pecah($scope.model).then(res => {
                    pesan.Success("Proses Berhasil");
                    window.localStorage.removeItem('biodata');
                    $.LoadingOverlay("hide");
                    $state.go('data', {}, {reload: false});
                })
            }
        })
    };

    $scope.batal = ()=>{
        window.localStorage.removeItem('biodata');
        $state.go('data', {}, {reload: false});
    }

    $scope.detailData = (item)=>{
        document.location.href = helperServices.url + "keluarga/detail/" + item.id
    }

    $scope.edit = (id)=>{
        keluargaServices.getId(id).then(res=>{
            res.setPage = "edit";
            window.localStorage.setItem('biodata', JSON.stringify(res)); 
            $state.go('add.keluarga');
        })
    }

    $scope.next = () => {
        window.localStorage.setItem('biodata', JSON.stringify($scope.model));
    }

    $scope.formTambah = (id)=>{
        document.location.href = helperServices.url + 'anggota/add/' + id;
    }

    let myWin;
    $scope.cetak = (item)=>{
        myWin = window.open(helperServices.url+"keluarga/cetak/"+item.id, "_blank");
    }
    $scope.cetakAll = ()=>{
        // window.open(helperServices.url+"keluarga/cetakall", "_blank");
        async function myDisplay() {
            await new Promise(resolve=> {
                myWin = window.open(helperServices.url+"laporan/print?item="+helperServices.encript('kepalaKeluarga'), "_blank");
                resolve(myWin);
              }, reject=>{
                  reject;
              });
          }
        myDisplay();
        
    }

    var temWijk = ""
    var temKsp = ""
    $scope.setKodeKK = (value, set)=>{
        set=="wijk" ? temWijk = value : temKsp = value;
        $scope.model.kode_kk = temWijk+temKsp;
    }

    $scope.hapus = (param)=>{
        pesan.dialog("Yakin ingin menlanjutkan?", "Ya", "Tidak").then(x => {
            $.LoadingOverlay("show");
            keluargaServices.deleted(param).then(res => {
                pesan.Success("Proses Berhasil");
                $.LoadingOverlay("hide");
            })
        })
    }
}