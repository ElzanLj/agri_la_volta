<?php

declare(strict_types=1);

namespace App\Domain;

/** Result of a price calculation. Money is stored as integer cents (EUR). */
final class PriceQuote
{
    /** @param array<string, mixed> $breakdown */
    public function __construct(
        public readonly int $totalCents,
        public readonly array $breakdown = [],
    ) {
    }
}
