<?php

namespace App\TheDep;

/**
 * Description of Auth
 *
 * @author puneetxp
 */
class FileAct {

   public function __construct(
           protected $file,
           protected $dir
   ) {
      
   }

   public static function init($file, $dir = "public") {
      $dir = "../storage/" . $dir;
      if (!is_dir($dir)) {
         mkdir($dir, 0755, true);
      }
      return new self(file: $file, dir: $dir);
   }

   public function webpImage($source, $quality = 100, $removeOld = false) {
      $dir = pathinfo($source, PATHINFO_DIRNAME);
      $name = pathinfo($source, PATHINFO_FILENAME);
      $destination = $dir . DIRECTORY_SEPARATOR . $name . '.webp';
      $info = getimagesize($source);
      $isAlpha = false;
      if ($info['mime'] == 'image/jpeg')
         $image = imagecreatefromjpeg($source);
      elseif ($isAlpha = $info['mime'] == 'image/gif') {
         $image = imagecreatefromgif($source);
      } elseif ($isAlpha = $info['mime'] == 'image/png') {
         $image = imagecreatefrompng($source);
      } else {
         return $source;
      }
      if ($isAlpha) {
         imagepalettetotruecolor($image);
         imagealphablending($image, true);
         imagesavealpha($image, true);
      }
      imagewebp($image, $destination, $quality);
      if ($removeOld)
         unlink($source);
      return $destination;
   }

   public function up($name = '') {
      if ($name == '') {
         $target_file = $this->dir . basename($_FILES[$this->file]["name"]);
      } else {
         $target_file = $this->dir . $name . pathinfo($_FILES[$this->file]['name'], PATHINFO_EXTENSION);
      }
      if (move_uploaded_file($_FILES[$this->file]["tmp_name"], $target_file)) {
         return $this;
      } else {
         return false;
      }
   }

   public function ups() {
      foreach ($this->reArrayFiles($_FILES[$this->file]) as $file) {
         move_uploaded_file($file['tmp_name'], $this->dir);
      }
      return $this;
   }

   function reArrayFiles(&$file_post) {
      $file_ary = array();
      $file_count = count($file_post['name']);
      $file_keys = array_keys($file_post);
      for ($i = 0; $i < $file_count; $i++) {
         foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
         }
      }
      return $file_ary;
   }

   public static function delete($path) {
      unlink($path);
   }

}
