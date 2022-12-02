<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace App\TheDep;

/**
 * Get The $_POST value 
 *
 * @author puneetxp
 */
class Req {

   public static function only(array $array) {
      return array_filter($_POST,
              fn($key) => in_array($key, $array),
              ARRAY_FILTER_USE_KEY);
   }

   public static function one(string $one) {
      return self::only([$one]);
   }

   //put your code here
}
