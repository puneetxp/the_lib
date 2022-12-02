<?php

namespace App\TheDep;

use App\The\Model\{
    Session,
    User
};

class Sessions {

    public static function create($user_id) {
        $_SESSION['user_id'] = $user_id;
    }

    public static function roles() {
        if (isset($_SESSION['user_id'])) {
            return array_values(array_column(User::find($_SESSION['user_id'])->wfast([['active_role' => 'role']])->array()['role'], 'name'));
        }
        return [];
    }

    public static function update($id) {
        
    }

    public static function delete($id) {
        
    }

}
