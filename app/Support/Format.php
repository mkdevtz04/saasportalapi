<?php

namespace App\Support;

class Format
{
    /** 1536 becomes "1.5 KB", 5368709120 becomes "5 GB". */
    public static function bytes(int|float|null $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        $number = number_format($bytes, $i === 0 ? 0 : 1);

        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return $number . ' ' . $units[$i];
    }

    /** 3725 seconds becomes "1h 2m". */
    public static function duration(int|float|null $seconds): string
    {
        $seconds = max(0, (int) $seconds);

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $days    = intdiv($seconds, 86400);
        $hours   = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return trim(($days ? $days . 'd ' : '') . ($hours ? $hours . 'h ' : '') . ($minutes ? $minutes . 'm' : ''));
    }
}
