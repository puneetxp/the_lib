<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

namespace The;

use Google\Client;
use App\Model\{
    User
};

class SocialAuth {

    public static function g_auth($token) {
        $vars = preg_split("/\./", $token);
        $load = json_decode(base64_decode($vars[1]));
        $client = new Client(['client_id' => $_ENV["login_method"]['google']['client_id']]);
        $payload = $client->verifyIdToken($token);
        if ($payload) {
            $auth = User::find($load->email, 'email') ?? User::create(["name" => $load->name, "email" => $load->email, "google_id" => $load->sub, "photo" => $load->picture])->getInserted();
            $user = $auth->array();
            if (!($user['google_id'])) {
                $auth->update(["google_id" => $load->sub]);
            }
            return Sessions::create($user);
            //   header('Location : ' . website . '/auth/profile');
        } else {
            Response::why("Token Not Correct");
            //   header('Location: ' . website . '/auth/login');
        }
    }

    public static function f_auth($token) {
        $load = json_decode(file_get_contents("https://graph.facebook.com/" . $_ENV["login_method"]["facebook"]["api_version"] . "/me?access_token=" . $token . "&fields=name,email,picture,first_name,last_name&method=get&pretty=0&sdk=joey&suppress_http_code=1"));
        if ($load) {
            $auth = User::find($load->email, 'email') ?? User::create(["name" => $load->name, "email" => $load->email, "facebook_id" => $load->id])->getInserted();
            $user = $auth->array();
            if (!($user['google_id'])) {
                $auth->update(["google_id" => $load->id]);
            }
            return Sessions::create($user);
            //   header('Location : ' . website . '/auth/profile');
        } else {
            Response::why("Token Not Correct");
            //   header('Location: ' . website . '/auth/login');
        }
    }
}
