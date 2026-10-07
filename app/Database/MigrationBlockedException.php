<?php

declare(strict_types=1);

namespace App\Database;

use RuntimeException;

/** A migration was not started because the existing data would break it. Nothing was changed. */
final class MigrationBlockedException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly string $version, public readonly array $problems)
    {
        parent::__construct(
            "La migrazione {$version} non è stata eseguita: i dati esistenti non rispettano le nuove regole. Non è stato modificato nulla. Da correggere: "
            . implode('; ', $problems) . '.'
        );
    }
}
