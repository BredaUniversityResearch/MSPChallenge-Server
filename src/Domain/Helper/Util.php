<?php

namespace App\Domain\Helper;

class Util
{
    public static function getHumanReadableSize(int $bytes): string
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($bytes/pow(1024, ($i=floor(log($bytes, 1024)))), 2).' '.$unit[$i];
    }
}
