<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace The;

/**
 * Get The $_POST value 
 *
 * @author puneetxp
 */
class Req {

    public static function only(array $array) {
        return array_filter(
                $_POST,
                fn($key) => in_array($key, $array),
                ARRAY_FILTER_USE_KEY
        );
    }

    public static function get(array $keys, array $data) {
        // print_r($data);
        return array_filter(
                $data,
                fn($key) => in_array($key, $keys),
                ARRAY_FILTER_USE_KEY
        );
    }

    public static function array(array $keys, array $data) {
        return array_map(fn($item) => Req::get($keys, (array) $item), $data);
    }

    public static function one(string $one) {
        return self::only([$one]);
    }

    //put your code here
}
