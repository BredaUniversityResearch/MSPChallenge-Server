<?php

/* This captures all PHP errors and warnings to ensure the standard return format */
set_error_handler('exceptions_error_handler');

function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    $errorNotices = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if ($severity & $errorNotices) { // only real errors
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}
/* End of PHP error and warning capturing code */

function isJsonObject($string)
{
    return is_object(json_decode($string));
}

function rrmdir($src)
{
    $dir = opendir($src);
    while (false !== ( $file = readdir($dir))) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if (is_dir($full)) {
                rrmdir($full);
            } else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

function rcopy($src, $dst)
{
    if (file_exists($dst)) {
        rrmdir($dst);
    }
    if (is_dir($src)) {
        mkdir($dst);
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                rcopy("$src/$file", "$dst/$file");
            }
        }
    } elseif (file_exists($src)) {
        copy($src, $dst);
    }
}
