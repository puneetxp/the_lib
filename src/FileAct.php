<?php

namespace The;

/**
 * Description of Auth
 *
 * @author puneetxp
 */
class FileAct {

    protected string $public;

    public function __construct(
            protected $file,
            protected string $dir,
    ) {

    }

    public $files = [];

    public function public(string $public, string $pre = '/storage') {
        $this->public = $pre . $public;
        if ($public == "") {
            $this->dir .= '/public';
            $this->public = $pre . $public;
        } else {
            $this->dir .= '/public/' . $public;
            $this->public = $pre . "/" . $public;
        }
        return $this;
    }

    public function checkdir() {
        if (!is_dir($this->dir)) {
            mkdir(directory: $this->dir, recursive: true);
        }
    }

    public static function init($file, $prefix = "../storage") {
        return new self(file: $file, dir: $prefix);
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
        $this->checkdir();
        if ($name == '') {
            $target_file = $this->dir . DIRECTORY_SEPARATOR . basename($_FILES[$this->file]["name"]);
        } else {
            $target_file = $this->dir . DIRECTORY_SEPARATOR . $name . pathinfo($_FILES[$this->file]['name'], PATHINFO_EXTENSION);
        }
        if (move_uploaded_file($_FILES[$this->file]["tmp_name"], $target_file)) {
            $this->files[] = $target_file;
        }
        return $this;
    }

    public function fileupload($file, $name) {
        $x = "Can't Upload";
        $this->checkdir();
        if ($name == '') {
            $target_file = $this->dir . DIRECTORY_SEPARATOR . "." . basename($file["name"]);
        } else {
            $target_file = $this->dir . DIRECTORY_SEPARATOR . $name . "." . pathinfo($file['name'], PATHINFO_EXTENSION);
        }
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $x = ['name' => $file['name'], 'path' => $this->dir . "/" . $file["name"], 'dir' => $this->dir, 'public' => $this->public . "/" . $name . "." . pathinfo($file['name'], PATHINFO_EXTENSION)];
        }
        return $x;
    }

    public function ups() {
        $this->checkdir();
        foreach ($this->reArrayFiles($this->file) as $file) {
            // $this->files[] = ['name' => $file['name'], 'dir' => $this->dir . "/" . $file["name"], 'public' => $this->public . "/" . $file["name"]];
            $this->files[] = ['name' => $file['name'], 'path' => $this->dir . "/" . $file["name"], 'dir' => $this->dir, 'public' => $this->public . "/" . $file["name"]];
            // $this->files[] = ['name' => $file['name'], 'dir' => $this->dir, 'path' => $this->dir . "/" . $file["name"], 'public' => $this->public . "/" . $file["name"]];
            move_uploaded_file($file['tmp_name'], $this->dir . "/" . $file['name']);
        }
        return $this;
    }

    public function reArrayFiles(&$file_post) {
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

    public static function fopen_dir($link) {
        $filename = $link;
        $dirname = dirname($filename);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }
        return fopen($filename, 'w');
    }

    public static function createfile($dir, $string) {
        fwrite(self::fopen_dir($dir), $string);
    }
}
