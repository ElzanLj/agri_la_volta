<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** The requested dates are not free (confirmed booking or block in the way). */
final class ConflictException extends RuntimeException
{
    /** @param list<array{type: string, id: int, start_date: string, end_date: string}> $conflicts */
    public function __construct(private array $conflicts)
    {
        parent::__construct('Dates not available');
    }

    /** @return list<array{type: string, id: int, start_date: string, end_date: string}> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
