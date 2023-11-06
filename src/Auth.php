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
class Auth {
    public static function login() {
        $user = Req::only(['email', 'password']);
        $auth = User::find($user['email'], 'email')?->array();
        if ($auth != null) {
            $pass = hash('sha3-256', $user['password']);
            if ($auth['password'] == $pass) {
                return Sessions::create($auth);
            }
            Response::why("Password Not Correct");
        }
        return Response::not_found("User Not Found");
    }


    public static function register() {
        $user = Req::only(['name', 'email', 'password']);
        $user['password'] = hash('sha3-256', $user['password']);
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

    public static function status() {
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

    public static function logout() {
        session_destroy();
        return Response::json('logout');
    }
}
