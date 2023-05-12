<?php

namespace The;

use App\Model\{
    Active_role
};

class Sessions
{

    public static function create($user_id)
    {
        $_SESSION['user_id'] = $user_id;
    }

    public static function roles()
    {
        $roles = [];
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $x = Active_role::where(["user_id" => [$_SESSION['user_id']]])->get()?->wfast(['role']);
            if ($x != null) {
                if ($_SESSION['user_id'] == 1) {
                    array_push($roles , "isuper");
                    return [...array_values(array_column($x->array()['role'], 'name')), ...array_values($roles)];
                }
                return [array_values(array_column($x->array()['role'], 'name'))];
            }
            return [];
        }
        return [];
    }

    public static function update($id)
    {
    }

    public static function delete($id)
    {
    }
}
