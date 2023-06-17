<?php
function imd(string $path, string $ext)
{
 $dir = pathinfo($path, PATHINFO_DIRNAME);
 $name = pathinfo($path, PATHINFO_FILENAME);
 $ext = pathinfo($path, PATHINFO_EXTENSION);
 return $dir . DIRECTORY_SEPARATOR . $ext . DIRECTORY_SEPARATOR . $name . $ext;
}
