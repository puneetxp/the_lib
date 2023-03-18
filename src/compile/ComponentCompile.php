<?php

namespace The\compile;

class ComponentCompile
{

    public $y;
    public $x = "<?php namespace App\Compiled; \n class Component {";
    public $dir = './View/';

    public function __construct()
    {
        $this->y = fopen(__DIR__ . '/../Compiled/Component.php', 'w');
    }

    function folderscan($dir)
    {
        $x = '';
        foreach (scandir($dir) as $file) {
            if ($file == '.') {
            } elseif ($file == "..") {
            } elseif (is_file("$dir/$file")) {
                $x .= $this->addfun("$dir/$file", $file);
            } elseif (is_dir("$dir/$file")) {
                $x .= $this->folderscan("$dir/$file");
            }
        }
        return $x;
    }

    function addfun($filename, $file)
    {
        $small = str_replace(["\n", "\r\n", "\r", "\t", "    ", "   ", "                  "], "", fread(fopen($filename, "r"), filesize($filename)));
        preg_match_all("/\(\(([^}]+)\)\)/gm", $small, $use_temp_multiple, PREG_SET_ORDER);
        return "public static function $file " . '($param){' .  $use_temp_multiple[0][1] . "}";
    }

    function run()
    {
        $this->x .= $this->folderscan($this->dir);
        $this->x .= " }";
        $this->x = str_replace('?><?php', '', $this->x);
        $this->x = str_replace('function ', 'public static function ', $this->x);
        fwrite($this->y, $this->x);
    }
}
