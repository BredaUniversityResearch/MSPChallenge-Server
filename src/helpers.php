<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
namespace App;

use ErrorException;

// This captures all PHP errors and warnings to ensure the standard return format
//   It only captures non-exceptions, so basically it converts all errors to ErrorException exceptions.
set_error_handler(function (int $errno, string $message, string $filename, int $lineno) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting, so let it fall
        // through to the standard PHP error handler
        return false;
    }

    $errorNotices = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if (!($errno & $errorNotices)) { // we are only interested in real errors to be converted to ErrorException
        /* Don't execute PHP internal error handler */
        return true;
    }

    throw new ErrorException($message, 0, $errno, $filename, $lineno);
});

/* End of PHP error and warning capturing code */

function isJsonObject($string): bool
{
    return is_object(json_decode($string));
}

function isBase64Encoded($string): bool
{
    return base64_encode(base64_decode($string, true)) === $string;
}

function rrmdir($src): void
{
    if (false === $dir = opendir($src)) {
        return;
    }
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

function rcopy($src, $dst): void
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
