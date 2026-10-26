<?php

namespace App\Support;

/** A number of bytes as people read it: "820 KB", "1.5 MB", "2 GB". */
final class Bytes
{
    private const UNITS = ['bytes', 'KB', 'MB', 'GB', 'TB'];

    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes === 1 ? '1 byte' : "{$bytes} bytes";
        }

        $power  = min((int) floor(log($bytes, 1024)), count(self::UNITS) - 1);
        $value  = $bytes / (1024 ** $power);
        $number = number_format($value, $value < 10 ? 1 : 0, '.', '');

        // "1.0 MB" reads "1 MB"; "10 MB" keeps its zero.
        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return "{$number} ".self::UNITS[$power];
    }
}
