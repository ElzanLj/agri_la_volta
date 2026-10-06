<?php

declare(strict_types=1);

namespace App\Site;

/** Amenities are stored as plain text, one per line; this is the single place that reads them. */
final class Amenities
{
    public const MAX_LINES = 30;
    public const MAX_LINE_LENGTH = 100;

    /** @return list<string> trimmed, non-empty lines (at most MAX_LINES) */
    public static function lines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return array_slice($lines, 0, self::MAX_LINES);
    }

    /** True when every non-empty line is short enough and there are not too many. */
    public static function isValid(string $text): bool
    {
        $count = 0;
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > self::MAX_LINE_LENGTH || ++$count > self::MAX_LINES) {
                return false;
            }
        }
        return true;
    }

    /** Normalised text for storage: trimmed lines joined by "\n", or null when empty. */
    public static function normalise(string $text): ?string
    {
        $lines = self::lines($text);
        return $lines === [] ? null : implode("\n", $lines);
    }
}
