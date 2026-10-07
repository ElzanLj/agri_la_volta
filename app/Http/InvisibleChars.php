<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Removes characters that are invisible or change how text is displayed: zero-width spaces and joiners,
 * left-to-right/right-to-left marks and embeddings (U+200B–U+200F, U+202A–U+202E, U+2066–U+2069) and
 * the byte order mark (U+FEFF). In a guest name they can reorder what the admin sees ("Rossi" shown
 * backwards) or make two identical names look different.
 */
final class InvisibleChars
{
    public static function strip(string $value): string
    {
        if ($value === '') {
            return '';
        }
        // On text that is not valid UTF-8 the pattern fails; the value is then left as it is
        // (output is always escaped with e(), which replaces invalid sequences).
        return preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value) ?? $value;
    }
}
