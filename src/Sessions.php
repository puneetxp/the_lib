<?php

namespace The;

use App\Model\{
    Active_role
};

class Sessions {
    public static function createSessoion(){
        $sessionId = session_id();
        header("X-Session-Id: $sessionId");
        header("Access-Control-Expose-Headers: X-Session-Id"); // Allows frontend to read session_id
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Id");
    }
    public static function create($auth) {
        $_SESSION['user_id'] = $auth['id'];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        (Req::one('remember_me')) ?
                        session_start([
                            'cookie_lifetime' => 1440,
                            'cookie_secure' => secure,
                            "cookie_path" => '/',
                            'cookie_domain' => sslhost,
                            'cookie_httponly' => httponly,
                            'cookie_samesite' => samesite
                        ]) :
                        session_start([
                            'cookie_lifetime' => 0,
                            'cookie_secure' => secure,
                            "cookie_path" => '/',
                            'cookie_domain' => sslhost,
                            'cookie_httponly' => httponly,
                            'cookie_samesite' => samesite
        ]);
        $_SESSION['user_id'] = $auth['id'];
        $auth['roles'] = Sessions::roles();
        Sessions::createSessoion();
        $auth['roles'] = Sessions::roles();
        return Response::json(array_intersect_key($auth, array_flip(["name", "email", "id", "roles"])));
    }

    public static function roles() {
        $roles = [];
        $x = Active_role::where(["user_id" => [$_SESSION['user_id']]])->getnull()?->with(['role']);
        if ($_SESSION['user_id'] == 1) {
            array_push($roles, "isuper");
        }
        if ($x != null) {
            return [...array_values(array_column($x->array()['role'], 'name')), ...array_values($roles)];
        }
        return $roles;
    }

    public static function get_current_user() {
        return (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : null;
    }

    public static function update($id) {

    }

    public static function delete($id) {

    }
}
