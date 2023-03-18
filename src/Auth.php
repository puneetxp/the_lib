<?php

namespace The;

use App\Model\{
    User
};

/**
 * Description of Auth
 *
 * @author puneetxp
 */
class Auth
{

    public static function login()
    {
        $user = Req::only(['email', 'password']);
        $pass = hash('sha3-256', $user['password']);
        $auth = User::find($user['email'], 'email')?->array();
        if ($auth != null) {
            if ($auth['password'] == $pass) {
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
                return Response::json(array_intersect_key($auth, array_flip(["name", "email", "id", "roles"])));
            }
            Response::why("Password Not Correct");
        }
        return Response::not_found("User Not Found");
    }

    public static function g_auth()
    {
        return;
    }

    public static function register()
    {
        $user = Req::only(['name', 'email', 'password']);
        $user['password'] = hash('sha3-256', $user['password']);
        if (User::find($user['email'], 'email')?->array() == null) {
            $auth = User::create([$user])->getInserted()->array();
            if (is_array($auth)) {
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
                return Response::json(array_intersect_key($auth, array_flip(["name", "email", "id", "roles"])));
            }
            return Response::bad_req();
        } else {
            return Response::unprocessable(['email' => 'Email Already Taken']);
        }
    }

    public static function auth_roles()
    {
        return Sessions::roles();
    }

    public static function status()
    {
        if (isset($_SESSION['user_id'])) {
            $auth = User::find($_SESSION['user_id'])?->array();
            if ($auth !== null) {
                $auth['roles'] = Sessions::roles();
                return Response::json(array_intersect_key($auth, array_flip(["name", "email", "id", "roles"])));
            }

            session_destroy();
            return Response::json("user not found");
        } else {
            header('Content-Type: application/json; charset=utf-8');
            return Response::not_authorised(false);
        }
    }

    public static function logout()
    {
        session_destroy();
        return Response::json('logout');
    }
}
