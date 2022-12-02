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
        if ($auth['password'] == $pass) {
            $_SESSION['user_id'] = $auth['id'];
            session_destroy();
            (Req::one('remember_me')) ?
                session_start([
                    'cookie_lifetime' => 1440,
                    'cookie_secure' => secure,
                    "cookie_path" => '/',
                    'cookie_domain' => web,
                    'cookie_httponly' => httponly,
                    'cookie_samesite' => same_site
                ]) :
                session_start([
                    'cookie_lifetime' => 0,
                    'cookie_secure' => secure,
                    "cookie_path" => '/',
                    'cookie_domain' => web,
                    'cookie_httponly' => httponly,
                    'cookie_samesite' => same_site
                ]);
            $_SESSION['user_id'] = $auth['id'];
            $auth['roles'] = Sessions::roles();
            return Response::json(array_intersect_key($auth, array_flip(["name", "email", "id", "roles"])));
        }
        return Response::not_found();
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
            $auth = User::create($user)->array();
            if (is_array($auth)) {
                session_destroy();
                (Req::one('remember_me')) ?
                    session_start([
                        'cookie_lifetime' => 1440,
                        'cookie_secure' => secure,
                        "cookie_path" => '/',
                        'cookie_domain' => web,
                        'cookie_httponly' => httponly,
                        'cookie_samesite' => same_site
                    ]) :
                    session_start([
                        'cookie_lifetime' => 0,
                        'cookie_secure' => secure,
                        "cookie_path" => '/',
                        'cookie_domain' => web,
                        'cookie_httponly' => httponly,
                        'cookie_samesite' => same_site
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
            $auth['roles'] = Sessions::roles();
            return Response::json(array_intersect_key($auth, array_flip(["name", "email", "id", "roles"])));
        } else {
            return Response::not_authorised(false);
        }
    }

    public static function logout()
    {
        session_destroy();
        return Response::json('logout');
    }
}
