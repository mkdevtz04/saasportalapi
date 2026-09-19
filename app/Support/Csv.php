<?php

namespace App\Support;

class Csv
{
    /**
     * Make a value safe to open in a spreadsheet. A cell that starts with = + - or @ is read as a
     * formula, so a customer who typed one into a name field could run it on the owner's computer.
     */
    public static function cell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $text : $text;
    }

    /**
     * Write one row to an open stream.
     *
     * @param resource $handle
     * @param array<int,mixed> $row
     */
    public static function write($handle, array $row): void
    {
        fputcsv($handle, array_map([self::class, 'cell'], $row));
    }
}
