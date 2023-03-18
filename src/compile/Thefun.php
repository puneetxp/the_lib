<?php

namespace The\compile;

class Thefun
{

    public static  function fopen_dir($link)
    {
        $filename = $link;
        $dirname = dirname($filename);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        return fopen($filename, 'w');
    }
    public static function php_wrapper($data)
    {
        return '<?php ' . $data . '?> ';
    }

    public static function php_w($data)
    {
        return '<?php ' . $data;
    }
}
