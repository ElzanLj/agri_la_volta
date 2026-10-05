<?php

declare(strict_types=1);

namespace App\Domain;

final class GuestCounts
{
    /** Technical anti-abuse limits (not business rules). */
    private const MAX_PEOPLE = 20;
    private const MAX_PETS = 10;

    private function __construct(
        public readonly int $adults,
        public readonly int $children,
        public readonly int $pets,
    ) {
    }

    /**
     * @param mixed $adults
     * @param mixed $children
     * @param mixed $pets
     * @throws ValidationException
     */
    public static function from(mixed $adults, mixed $children = 0, mixed $pets = 0): self
    {
        $errors = [];
        $a = self::toInt($adults);
        $c = self::toInt($children);
        $p = self::toInt($pets);

        if ($a === null || $a < 1 || $a > self::MAX_PEOPLE) {
            $errors['adults'] = 'invalid_adults';
        }
        if ($c === null || $c < 0 || $c > self::MAX_PEOPLE) {
            $errors['children'] = 'invalid_children';
        }
        if ($p === null || $p < 0 || $p > self::MAX_PETS) {
            $errors['pets'] = 'invalid_pets';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($a, $c, $p);
    }

    public function people(): int
    {
        return $this->adults + $this->children;
    }

    /** Pets do not count towards capacity. A NULL capacity means "not configured": no check. */
    public function exceedsCapacity(?int $maxGuests): bool
    {
        return $maxGuests !== null && $this->people() > $maxGuests;
    }

    private static function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d{1,3}$/', trim($value))) {
            return (int) $value;
        }
        return null;
    }
}
