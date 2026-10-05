<?php

declare(strict_types=1);

namespace App\Domain;

/** Base nightly price of one apartment for the period [startDate, endDate). */
final class RatePeriod
{
    public function __construct(
        public readonly int $id,
        public readonly int $apartmentId,
        public readonly string $labelIt,
        public readonly ?string $labelEn,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $nightlyRateCents,
        public readonly ?int $minNights = null,
        public readonly bool $isActive = true,
    ) {
    }

    /** @param array<string, mixed> $row a seasonal_rates row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['apartment_id'],
            (string) $row['label'],
            $row['label_en'] === null ? null : (string) $row['label_en'],
            (string) $row['start_date'],
            (string) $row['end_date'],
            (int) $row['nightly_rate_cents'],
            $row['min_nights'] === null ? null : (int) $row['min_nights'],
            (bool) $row['is_active'],
        );
    }

    /** $night is a "YYYY-MM-DD" date: the night starting on that day. */
    public function coversNight(string $night): bool
    {
        return $this->startDate <= $night && $night < $this->endDate;
    }

    public function labelEnOrIt(): string
    {
        return $this->labelEn !== null && $this->labelEn !== '' ? $this->labelEn : $this->labelIt;
    }
}
