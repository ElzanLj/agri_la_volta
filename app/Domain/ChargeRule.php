<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * One additive charge: extra adults, children, pets or a flat supplement.
 * Fixed vocabulary on purpose: this is not a generic rules engine.
 */
final class ChargeRule
{
    public const APPLIES_TO = ['adult', 'child', 'pet', 'stay'];
    public const BASES = ['per_night', 'per_stay'];

    public function __construct(
        public readonly int $id,
        public readonly ?int $apartmentId,
        public readonly string $appliesTo,
        public readonly string $chargeBasis,
        public readonly int $freeUnits,
        public readonly int $amountCents,
        public readonly ?string $validFrom = null,
        public readonly ?string $validTo = null,
        public readonly bool $isActive = true,
        public readonly int $sortOrder = 0,
        public readonly string $labelIt = '',
        public readonly ?string $labelEn = null,
    ) {
    }

    /** @param array<string, mixed> $row a pricing_rules row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['apartment_id'] === null ? null : (int) $row['apartment_id'],
            (string) $row['applies_to'],
            (string) $row['charge_basis'],
            (int) $row['free_units'],
            (int) $row['amount_cents'],
            $row['valid_from'] === null ? null : (string) $row['valid_from'],
            $row['valid_to'] === null ? null : (string) $row['valid_to'],
            (bool) $row['is_active'],
            (int) $row['sort_order'],
            (string) $row['label_it'],
            $row['label_en'] === null ? null : (string) $row['label_en'],
        );
    }

    /** True when the night starting on $night is inside the validity window [validFrom, validTo). */
    public function coversNight(string $night): bool
    {
        return ($this->validFrom === null || $night >= $this->validFrom)
            && ($this->validTo === null || $night < $this->validTo);
    }

    public function labelEnOrIt(): string
    {
        return $this->labelEn !== null && $this->labelEn !== '' ? $this->labelEn : $this->labelIt;
    }
}
