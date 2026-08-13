angular
  .module("apps", [
    "adminctrl",
    "admin.service",
    "helper.service",
    // "auth.service",
    // "naif.base64",
    // "message.service",
    // "ngLocale",
    // "datatables",
    // 'cur.$mask',
    "ngAnimate",
    "ngSanitize",
    "ui.router",
    // "ui.select2",
  ])

  // .run(['uiSelect2Config', function(uiSelect2Config) {
  //     uiSelect2Config.placeholder = "Placeholder text";
  //     // $scope.$on('place', function(evt, data){
  //     // })
  // }])
  .controller("indexController", indexController)
  .directive("emaudio", emaudio);
// .directive('dynamic', ['$compile', function ($compile) {
//     return {
//       restrict: 'A',
//       replace: true,
//       link: function (scope, ele, attrs) {
//         scope.$watch(attrs.dynamic, function (html) {
//           ele.html(html);
//           $compile(ele.contents())(scope);
//         });
//       }
//     };
//   }])

function indexController($scope, helperServices) {
  $scope.titleHeader = "Mega Futsal";
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
  setTimeout(() => {
    let tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]'),
    );
    tooltipTriggerList.map(function (el) {
      return new bootstrap.Tooltip(el);
    });
  }, 200);
}

function emaudio() {
  var template =
    '<div class="audio-container"><div class="album-container"><img ng-src="{{album}}" /></div><span class="audio-controls"><div class="audio-row">{{artist}} - {{title}}</div><div class="audio-row"><span class="audio-play" ng-click="play()">{{isplaying ? "&#9632;" : "&#9658;"}}</span><div class="progress" ng-mousedown="setTime($event)" ng-mouseup="reset()"><div class="bar" data-ng-style="barstyle"></div></div><span class="audio-time">{{duration}}</span></div></span></div>';
  return {
    restrict: "E",
    template: template,
    replace: true,
    scope: {
      album: "@",
      url: "@",
      artist: "@",
      title: "@",
    },
    link: function ($scope, $element) {
      //Width of progress bar element
      $scope.timelineWidth =
        $element[0].querySelectorAll(".progress")[0].offsetWidth;
      $scope.audio = new Audio();
      $scope.audio.type = "audio/mpeg";
      $scope.audio.src = $scope.url;
      $scope.duration = "0:00";
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
        $scope.audio.removeEventListener("timeupdate", timeupdate, true);

        var position = $event.clientX - $event.target.offsetLeft;

        $scope.time = (position / $scope.timelineWidth) * 100;
        $scope.audio.currentTime = ($scope.time * $scope.audio.duration) / 100;

        $scope.barstyle.width = $scope.time + "%";
      };

      $scope.reset = function () {
        $scope.audio.addEventListener("timeupdate", timeupdate);
      };

      $scope.audio.addEventListener("timeupdate", timeupdate);

      function timeupdate() {
        var sec_num = $scope.audio.currentTime;
        var minutes = Math.floor(sec_num / 60);
        var seconds = sec_num - minutes * 60;
        if (minutes < 10) {
          minutes = "0" + minutes;
        }
        minutes += "";
        if (seconds < 10) {
          seconds = "0" + seconds;
        }
        seconds += "";

        var time = minutes + ":" + seconds.substring(0, 2);
        $scope.duration = time;

        $scope.barstyle.width =
          ($scope.audio.currentTime / $scope.audio.duration) * 100 + "%";
        $scope.$apply();
      }
    },
  };
}
