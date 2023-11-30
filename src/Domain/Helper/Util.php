<?php

namespace App\Domain\Helper;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Util
{
    public static function getHumanReadableSize(int $bytes): string
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($bytes/pow(1024, ($i=floor(log($bytes, 1024)))), 2).' '.$unit[$i];
    }

    public static function formatMilliseconds(float $milliseconds): string
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);

        $format = '%ums';
        $milliseconds = (int)$milliseconds % 1000;
        $args[] = $milliseconds;

        $seconds = $seconds % 60;
        if ($seconds > 0) {
            $format = '%us.' . $format;
            $args[] = $seconds;
        }

        $minutes = $minutes % 60;
        if ($minutes > 0) {
            $format = '%um:' . $format;
            $args[] = $minutes;
        }

        $args[] = $format;

        return call_user_func_array('sprintf', array_reverse($args));
    }

    public static function getMedian(array $values): ?float
    {
        $values = array_values($values);
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        asort($values);
        $half = (int)floor($count / 2);
        if ($count % 2) {
            return $values[$half];
        }
        return ($values[$half - 1] + $values[$half]) / 2.0;
    }

    public static function hasPrefix($strHaystack, $mixPrefixes): ?string
    {
        // @note (MH) : one or more prefixes
        if (!is_array($mixPrefixes)) {
            $mixPrefixes = array($mixPrefixes);
        }

        foreach ($mixPrefixes as $strPrefix) {
            $strHaystackPrefix = substr($strHaystack, 0, strlen($strPrefix));
            if ($strPrefix === $strHaystackPrefix) {
                return $strPrefix;
            }
        }

        return null;
    }

    /**
     * @param string $strHaystack
     * @param string|array $mixPostfixes
     * @return null|string
     */
    public static function hasPostfix(string $strHaystack, $mixPostfixes): ?string
    {
        if (!is_array($mixPostfixes)) {
            $mixPostfixes = array($mixPostfixes);
        }

        foreach ($mixPostfixes as $strPostfix) {
            $strHaystackPostfix = substr($strHaystack, -strlen($strPostfix));
            if ($strPostfix === $strHaystackPostfix) {
                return $strPostfix;
            }
        }

        return null;
    }

    public static function removeDirectory(string $dir): void
    {
        // Ensure the path is a directory
        if (!is_dir($dir)) {
            return;
        }
        // Get the list of files and subdirectories in the directory
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $currentPath = $dir . DIRECTORY_SEPARATOR . $file;

            // Recursively remove files and subdirectories
            if (is_dir($currentPath)) {
                self::removeDirectory($currentPath);
            } else {
                // Unlink (delete) the file
                unlink($currentPath);
            }
        }

        // Remove the directory itself
        rmdir($dir);
    }
}
