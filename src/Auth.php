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
        $auth = User::find($user['email'] ?? '', 'email')?->array();
        if ($auth == null) {
            return Response::not_found("User Not Found");
        }
        if (isset($auth['enable']) && (int) $auth['enable'] === 0) {
            return Response::not_authorised("This account has been disabled");
        }
        [$ok, $rehash] = Password::verify((string) ($user['password'] ?? ''), $auth['password'] ?? null);
        if (!$ok) {
            return Response::NotLogin("Password is Incorrect");
        }
        if ($rehash) {
            // An old SHA3 hash: store bcrypt now that the password is known.
            User::where(['id' => [$auth['id']]])->update(['password' => Password::hash($user['password'])]);
        }
        return Sessions::create($auth);
    }

    public static function register()
    {
        $user = Req::only(['name', 'email', 'password']);
        if (empty($user['email']) || empty($user['password'])) {
            return Response::unprocessable("Email and password are required");
        }
        if (strlen($user['password']) > Password::MAX_BYTES) {
            return Response::unprocessable(['password' => 'Password must be at most ' . Password::MAX_BYTES . ' bytes']);
        }
        $user['password'] = Password::hash($user['password']);
        if (User::find($user['email'], 'email')?->array() == null) {
            $auth = User::create($user)->getInserted()->array();
            if (is_array($auth)) {
                return Sessions::create($auth);
            }
            return Response::bad_req();
        } else {
            return Response::unprocessable(['email' => 'Email Already Taken']);
        }
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

    public static function profile()
    {
        $auth = User::where(["user_id" => $_SESSION['user_id']])->first(["name", "phone", "email"]);
        if ($auth !== null) {
            return $auth;
        }
        return Response::json("user not found");
    }

    public static function profileupdate()
    {
        $auth = User::where(["user_id" => $_SESSION['user_id']])->update($_POST)->first(["name", "phone", "email"]);
        if ($auth !== null) {
            return $auth;
        }
        return Response::json("user not found");
    }

    public static function logout()
    {
        session_destroy();
        return Response::json('logout');
    }
}
