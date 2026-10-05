<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSV writer for the admin exports: ";" delimiter (Excel in Italian locales, LibreOffice and
 * Google Sheets open it correctly), CRLF line ends, RFC 4180 quoting.
 *
 * Formula injection: a cell that a spreadsheet could interpret as a formula (it starts with
 * = + - @ tab or CR) is prefixed with an apostrophe, so guest-supplied text is always data.
 */
final class Csv
{
    public const DELIMITER = ';';

    /** @param list<scalar|null> $cells */
    public static function row(array $cells): string
    {
        return implode(self::DELIMITER, array_map(self::cell(...), $cells)) . "\r\n";
    }

    public static function cell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $text = "'" . $text;
        }
        if ($text !== '' && preg_match('/[;"\r\n]/', $text)) {
            $text = '"' . str_replace('"', '""', $text) . '"';
        }
        return $text;
    }
}
