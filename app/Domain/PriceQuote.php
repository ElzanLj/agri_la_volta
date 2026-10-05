<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Result of a price calculation. Money is integer cents (EUR).
 *
 * totalCents is null when no reliable total exists (e.g. a night has no rate): the request is
 * still accepted and shown as "price to be confirmed". Lines computed so far are kept for the admin.
 *
 * Line shape: kind (base|adult|child|pet|supplement), label_it, label_en, quantity, nights
 * (null for per-stay charges), unit_cents, total_cents, basis, and for base lines start_date/end_date.
 * Issue shape: code, severity (blocking|info) plus code-specific details.
 */
final class PriceQuote
{
    public const SNAPSHOT_VERSION = 1;

    /**
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $issues
     */
    public function __construct(
        public readonly ?int $totalCents,
        public readonly array $lines = [],
        public readonly array $issues = [],
        public readonly ?int $minNights = null,
        public readonly int $nights = 0,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->totalCents !== null;
    }

    /** @return list<array<string, mixed>> */
    public function blockingIssues(): array
    {
        return array_values(array_filter($this->issues, static fn (array $i): bool => ($i['severity'] ?? '') === 'blocking'));
    }

    public function hasIssue(string $code): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue['code'] === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stored with the request (booking_requests.price_breakdown) so later price-list edits
     * never change a quote that was already given.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'version' => self::SNAPSHOT_VERSION,
            'currency' => 'EUR',
            'status' => $this->isComplete() ? 'quoted' : 'unquoted',
            'nights' => $this->nights,
            'total_cents' => $this->totalCents,
            'min_nights' => $this->minNights,
            'lines' => $this->lines,
            'issues' => $this->issues,
        ];
    }

    /**
     * Human-readable summary for the guest and the admin.
     *
     * @return array{lines: list<array{label: string, detail: string, amount: string}>, total: ?string, note: ?string}
     */
    public function summary(string $locale = 'it'): array
    {
        $en = $locale === 'en';
        $rows = [];

        foreach ($this->lines as $line) {
            $unit = Money::format((int) $line['unit_cents'], $locale);
            $nights = $line['nights'] === null ? null : (int) $line['nights'];
            $nightsText = $nights === null ? '' : $nights . ' ' . self::nightWord($nights, $en);

            $detail = match (true) {
                $line['kind'] === 'base' => $nightsText . ' × ' . $unit,
                $line['basis'] === 'per_night' => $line['quantity'] . ' × ' . $unit . ' × ' . $nightsText,
                default => $line['quantity'] . ' × ' . $unit,
            };
            $rows[] = [
                'label' => (string) ($en ? $line['label_en'] : $line['label_it']),
                'detail' => $detail,
                'amount' => Money::format((int) $line['total_cents'], $locale),
            ];
        }

        return [
            'lines' => $rows,
            'total' => $this->totalCents === null ? null : Money::format($this->totalCents, $locale),
            'note' => $this->totalCents === null ? ($en ? 'Price to be confirmed' : 'Prezzo da confermare') : null,
        ];
    }

    private static function nightWord(int $nights, bool $en): string
    {
        return $en ? ($nights === 1 ? 'night' : 'nights') : ($nights === 1 ? 'notte' : 'notti');
    }
}
