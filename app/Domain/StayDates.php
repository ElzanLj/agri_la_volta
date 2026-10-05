<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A stay interval [checkIn, checkOut): the check-out day is not occupied.
 * Dates are plain calendar dates ("YYYY-MM-DD"); nights are computed in UTC so
 * daylight-saving changes cannot add or remove a night.
 */
final class StayDates
{
    /** Technical anti-abuse limits (not business rules). */
    public const MAX_NIGHTS_STAY = 60;
    public const MAX_NIGHTS_BLOCK = 366;
    public const MAX_ADVANCE_YEARS = 2;

    private const MIN_DATE = '2000-01-01';
    private const MAX_DATE = '2100-12-31';

    private function __construct(
        public readonly string $checkIn,
        public readonly string $checkOut,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public static function fromStrings(string $checkIn, string $checkOut, int $maxNights = self::MAX_NIGHTS_STAY): self
    {
        $errors = [];
        $in = self::parseDate($checkIn);
        $out = self::parseDate($checkOut);

        if ($in === null) {
            $errors['check_in'] = 'invalid_date';
        }
        if ($out === null) {
            $errors['check_out'] = 'invalid_date';
        }
        if ($errors === []) {
            if ($out <= $in) {
                $errors['check_out'] = 'check_out_not_after_check_in';
            } elseif (self::diffDays($in, $out) > $maxNights) {
                $errors['check_out'] = 'stay_too_long';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($checkIn, $checkOut);
    }

    /**
     * Extra rules for stays requested through the public form: not in the past
     * and not too far ahead. $today is a "YYYY-MM-DD" date in the business time zone.
     *
     * @throws ValidationException
     */
    public function assertBookableFromPublic(string $today): void
    {
        $limit = (new DateTimeImmutable($today, new DateTimeZone('UTC')))
            ->modify('+' . self::MAX_ADVANCE_YEARS . ' years')
            ->format('Y-m-d');

        if ($this->checkIn < $today) {
            throw new ValidationException(['check_in' => 'check_in_in_past']);
        }
        if ($this->checkIn > $limit) {
            throw new ValidationException(['check_in' => 'check_in_too_far']);
        }
    }

    public function nights(): int
    {
        return self::diffDays(self::parseDate($this->checkIn), self::parseDate($this->checkOut));
    }

    /**
     * The nights of the stay, as the "YYYY-MM-DD" date each night starts on.
     *
     * @return list<string>
     */
    public function nightDates(): array
    {
        $dates = [];
        $day = self::parseDate($this->checkIn);
        for ($i = 0, $n = $this->nights(); $i < $n; $i++) {
            $dates[] = $day->format('Y-m-d');
            $day = $day->modify('+1 day');
        }
        return $dates;
    }

    /** True when the two half-open intervals share at least one night. */
    public function overlaps(self $other): bool
    {
        // Y-m-d strings compare chronologically.
        return $this->checkIn < $other->checkOut && $this->checkOut > $other->checkIn;
    }

    /** True for a real calendar date written as YYYY-MM-DD within the supported range. */
    public static function isValidDate(string $value): bool
    {
        return self::parseDate($value) !== null;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $warnings = DateTimeImmutable::getLastErrors();
        if ($date === false || ($warnings !== false && ($warnings['warning_count'] > 0 || $warnings['error_count'] > 0))) {
            return null;
        }
        if ($date->format('Y-m-d') !== $value || $value < self::MIN_DATE || $value > self::MAX_DATE) {
            return null;
        }
        return $date;
    }

    private static function diffDays(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->format('%r%a');
    }
}
