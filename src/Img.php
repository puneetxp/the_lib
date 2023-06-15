<?php

namespace The;

use GdImage;

class Img
{

   public GdImage $im;
   public bool $isAlpha = false;

   public static function pathfile($source)
   {
      $dir = pathinfo($source, PATHINFO_DIRNAME);
      $name = pathinfo($source, PATHINFO_FILENAME);
      $ext = pathinfo($source, PATHINFO_EXTENSION);
      return [$dir, $name, $ext];
   }
   public static function webpImage($source, ?string $destination = null, $isaspect = true, $quality = 100, $removeOld = false, ?int $x = null, ?int $y = null)
   {
      $dir = pathinfo($destination, PATHINFO_DIRNAME);
      $name = pathinfo($destination, PATHINFO_FILENAME);
      if (!is_dir($dir)) {
         mkdir($dir);
      }
      $destination = $dir . DIRECTORY_SEPARATOR . $name . '.webp';
      $info = getimagesize($source);
      $isAlpha = false;
      if ($info['mime'] == 'image/jpeg' || $info['mime'] == 'image/jpg') {
         $image = imagecreatefromjpeg($source);
      } elseif ($isAlpha = $info['mime'] == 'image/gif') {
         $image = imagecreatefromgif($source);
      } elseif ($isAlpha = $info['mime'] == 'image/png') {
         $image = imagecreatefrompng($source);
      } elseif ($info['mime'] == 'image/webp') {
         $image = imagecreatefromwebp($source);
      } else {
         return $source;
      }
      if ($isAlpha) {
         imagepalettetotruecolor($image);
         imagealphablending($image, true);
         imagesavealpha($image, true);
      }
      if ($isaspect) {
         $aspect = $info[0] / $info[1];
         if ($info[0] < $x || $info[1] < $y) {
            $resolution = [$info[0], $info[1]];
         } else {
            $resolution = [$x, $x / $aspect];
         }
      } else {
         imageresolution($image, $x, $y);
         $resolution = imageresolution($image);
      }
      $thumb = imagecreatetruecolor($resolution[0], $resolution[1]);
      imagecopyresized($thumb, $image, 0, 0, 0, 0, $resolution[0], $resolution[1], $info[0], $info[1]);
      imagewebp($thumb, $destination, $quality);
      if ($removeOld)
         unlink($source);

      return $destination;
   }

   public function __construct(public $source, public ?string $destination = null)
   {
      if ($this->destination) {
         $this->destination = $this->source;
      }
   }

   public function resoultion(?int $x = null, ?int $y = null)
   {
      // dd($this->im);
      imageresolution($this->im, $x, $y);
      return $this;
   }

   public function webpImg($quality = 100, $removeOld = false)
   {
      imagewebp($this->im, $this->destination, $quality);
      if ($removeOld) {
         unlink($this->$this->source);
      }
      return $this;
   }

   public function create()
   {
      $info = getimagesize($this->source);
      if ($info['mime'] == "image/gif" || $info['mime'] == "image/png") {
         $this->isAlpha = true;
      }
      // dd($info['mime']);
      switch ($info['mime']) {
         case "image/gif":
            $this->im = imageCreateFromGif($this->source);
            break;
            // jpg
         case "image/jpeg":
            $this->im = imagecreatefromjpeg($this->source);
            break;
         case "image/jpg":
            $this->im = imagecreatefromjpeg($this->source);
            break;
            // png
         case "image/png":
            $this->im = imageCreatefrompng($this->source);
            break;
            // bmp
         case "image/bmp":
            $this->im = imageCreateFromBmp($this->source);
            break;
         default:
      }
      if ($this->isAlpha) {
         imagepalettetotruecolor($this->im);
         imagealphablending($this->im, true);
         imagesavealpha($this->im, true);
      }

      return $this;
      // dd($this->im);
   }
}
