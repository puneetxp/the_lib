<?php
function fopen_dir($link)
{
 $filename = $link;
 $dirname = dirname($filename);
 if (!is_dir($dirname)) {
  mkdir($dirname, 0755, true);
 }
 return fopen($filename, 'w');
}
function createfile($dir, $string)
{
 fwrite(fopen_dir($dir), $string);
}
