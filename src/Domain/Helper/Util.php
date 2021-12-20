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

        $format = '%03ums';
        $milliseconds = $milliseconds % 1000;
        $args[] = $milliseconds;

        $seconds = $seconds % 60;
        if ($seconds > 0) {
            $format = '%02us.' . $format;
            $args[] = $seconds;
        }

        $minutes = $minutes % 60;
        if ($minutes > 0) {
            $format = '%02um:' . $format;
            $args[] = $minutes;
        }

        $args[] = $format;

        $time = call_user_func_array('sprintf', array_reverse($args));
        return rtrim($time, '0');
    }
}
