<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Rules for the admin password, used by the Account page and by bin/create-admin.php.
 *
 * Besides the length, a password is refused when it is predictable: a common password (also written
 * with capitals, digits or symbols added, or with look-alike characters such as "@" for "a"), or one
 * that contains the user name or the name of the farm. The list of common passwords is a small file
 * in the repository (common-passwords.txt): no external service is involved and nothing is sent anywhere.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 1024;

    /** Names that must not appear in the password, written in lower case without spaces. */
    private const BRAND_WORDS = ['agriturismo', 'lavolta'];

    /** Look-alike characters read as the letter they imitate. */
    private const LOOK_ALIKES = ['@' => 'a', '4' => 'a', '3' => 'e', '1' => 'i', '!' => 'i', '0' => 'o', '$' => 's', '5' => 's', '7' => 't'];

    /** @var list<string>|null */
    private static ?array $common = null;

    /**
     * What is wrong with a candidate password.
     *
     * @return list<string> messages for the user, in Italian; empty when the password is acceptable
     */
    public static function problems(string $password, string $username = '', ?string $currentHash = null): array
    {
        $problems = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            return [sprintf('La password deve avere almeno %d caratteri.', self::MIN_LENGTH)];
        }
        if ($length > self::MAX_LENGTH || strlen($password) > self::MAX_LENGTH) {
            return [sprintf('La password può avere al massimo %d caratteri.', self::MAX_LENGTH)];
        }

        if (self::tooRepetitive($password)) {
            $problems[] = 'La password è troppo ripetitiva: usa caratteri diversi.';
        }

        $alnum = (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($password));
        $letters = self::readLookAlikes($password);

        $usernameKey = (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($username));
        if (strlen($usernameKey) >= 3 && (str_contains($alnum, $usernameKey) || str_contains($letters, $usernameKey))) {
            $problems[] = 'La password non può contenere il nome utente.';
        }
        foreach (self::BRAND_WORDS as $word) {
            if (str_contains($alnum, $word) || str_contains($letters, $word)) {
                $problems[] = 'La password non può contenere il nome dell\'agriturismo.';
                break;
            }
        }
        if (self::isCommon($letters, $alnum)) {
            $problems[] = 'La password è troppo comune o prevedibile (per esempio una parola semplice con una data o un numero aggiunti). Scegline una lunga e diversa, anche una frase.';
        }
        if ($currentHash !== null && $currentHash !== '' && password_verify($password, $currentHash)) {
            $problems[] = 'La nuova password è uguale a quella attuale.';
        }
        return $problems;
    }

    /** Lower case, look-alikes read as letters, everything that is not a letter dropped. */
    private static function readLookAlikes(string $password): string
    {
        $lower = strtr(mb_strtolower($password), self::LOOK_ALIKES);
        return (string) preg_replace('/[^a-z]/', '', $lower);
    }

    /** Fewer than five different characters, or only digits: not a password worth having. */
    private static function tooRepetitive(string $password): bool
    {
        $chars = preg_split('//u', $password, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return count(array_unique($chars)) < 5 || preg_match('/^[0-9\s]+$/', $password) === 1;
    }

    private static function isCommon(string $letters, string $alnum): bool
    {
        foreach (self::commonList() as $word) {
            // A word counts when it is the whole password once digits and symbols are taken away
            // ("Password2026!" is "password"); a word of six letters or more also counts when it is at least
            // half of the letters ("password-password-1"), so a long phrase that merely contains
            // "famiglia" is not refused.
            if ($letters === $word || $alnum === $word) {
                return true;
            }
            if (strlen($word) >= 6 && str_contains($letters, $word) && strlen($word) * 2 >= strlen($letters)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    private static function commonList(): array
    {
        if (self::$common === null) {
            $file = __DIR__ . '/common-passwords.txt';
            $words = [];
            foreach (is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [] as $line) {
                $line = strtolower(trim($line));
                if ($line !== '' && $line[0] !== '#') {
                    $words[] = $line;
                }
            }
            self::$common = array_values(array_unique($words));
        }
        return self::$common;
    }
}
