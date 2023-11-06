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
            return User::find($load->email, 'email');
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
}
