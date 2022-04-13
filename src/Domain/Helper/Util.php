<?php

namespace App\Domain\Helper;

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
}
