angular.module("auth.service", [])
    .factory("AuthService", AuthService);

function AuthService($http, $q, helperServices, pesan) {
    var service = {};
    return {
        login: login,
        setRole: setRole,
        // logOff: logoff,
        // userIsLogin: userIsLogin,
        // getUserName: getUserName,
        // userIsLogin: userIsLogin,
        // userInRole: userInRole,
        getHeader: getHeader,
        // getToken: getToken,
        // getUserId: getUserId
    }

    function login(user) {
        var def = $q.defer();
        $http({
            method: 'POST',
            url: helperServices.url + "/auth/login",
            data: user,
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(res => {
            var user = res.data;
            def.resolve(user);
        }, err => {
            def.reject(err.data.messages.error);
            pesan.error(err.data.messages.error);
        });
        return def.promise;
    }

    function setRole(user) {
        var def = $q.defer();
        $http({
            method: 'POST',
            url: helperServices.url + "/login/set",
            data: user,
            headers: {
                'Content-Type': 'application/json'
            }
        }).then(res => {
            var user = res.data;
            def.resolve(user);
        }, err => {
            def.reject(err.data.messages.error);
            message.error(err.data.messages.error);
        });
        return def.promise;
    }

    function getHeader() {

        try {
            if (userIsLogin()) {
                return {
                    'Content-Type': 'application/json'
                }
            }
            throw new Error("Not Found Token");
        } catch {
            return {
                'Content-Type': 'application/json'
            }
        }
    }

    // function logoff() {
    //     StorageService.clear();
    //     $state.go("login");

    // }

    // function getUserName() {
    //     if (userIsLogin) {
    //         var result = StorageService.getObject("user");
    //         return result.Username;
    //     }
    // }

    // function getToken() {
    //     if (userIsLogin) {
    //         var result = StorageService.getObject("user");
    //         return result.token;
    //     }
    // }
    // function getUserId() {
    //     if (userIsLogin) {
    //         var result = StorageService.getObject("user");
    //         return result.id;
    //     }
    // }

    // function userIsLogin() {
    //     var result = StorageService.getObject("user");
    //     if (result) {
    //         return true;
    //     }
    // }

    // function userInRole(role) {
    //     var result = StorageService.getItem("user");
    //     if (result && result.roles.find(x => x.name = role)) {

    //         return true;
    //     }
    // }
}