<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\ChargeRule;
use App\Domain\RatePeriod;
use App\Domain\StayDates;
use PDO;

/** SQL for rate periods (seasonal_rates) and charge rules (pricing_rules). */
final class PricingRepository
{
    public function __construct(private PDO $db)
    {
    }

    // --- reads used by the quoter ----------------------------------------------------------

    /** @return list<RatePeriod> active periods of the apartment that touch the stay */
    public function ratePeriodsFor(int $apartmentId, StayDates $stay): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM seasonal_rates WHERE apartment_id = ? AND is_active = 1 AND start_date < ? AND end_date > ?'
        );
        $stmt->execute([$apartmentId, $stay->checkOut, $stay->checkIn]);
        return array_map(RatePeriod::fromRow(...), $stmt->fetchAll());
    }

    /** @return list<ChargeRule> active rules (apartment-specific and global) whose window touches the stay */
    public function rulesFor(int $apartmentId, StayDates $stay): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM pricing_rules
             WHERE is_active = 1 AND (apartment_id IS NULL OR apartment_id = ?)
               AND (valid_from IS NULL OR valid_from < ?) AND (valid_to IS NULL OR valid_to > ?)
             ORDER BY sort_order, id'
        );
        $stmt->execute([$apartmentId, $stay->checkOut, $stay->checkIn]);
        return array_map(ChargeRule::fromRow(...), $stmt->fetchAll());
    }

    // --- rate periods: configuration ---------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function rate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM seasonal_rates WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<int> ids of ACTIVE periods of the apartment sharing a night with [start, end) */
    public function overlappingActiveRateIds(int $apartmentId, string $start, string $end, ?int $excludeId = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM seasonal_rates
             WHERE apartment_id = ? AND is_active = 1 AND start_date < ? AND end_date > ? AND id <> ?
             ORDER BY start_date, id'
        );
        $stmt->execute([$apartmentId, $end, $start, $excludeId ?? 0]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<RatePeriod> */
    public function activeRatesBetween(int $apartmentId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM seasonal_rates WHERE apartment_id = ? AND is_active = 1 AND start_date < ? AND end_date > ?'
        );
        $stmt->execute([$apartmentId, $end, $start]);
        return array_map(RatePeriod::fromRow(...), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $v normalised values */
    public function insertRate(array $v): int
    {
        $this->db->prepare(
            'INSERT INTO seasonal_rates (apartment_id, label, label_en, start_date, end_date, nightly_rate_cents, min_nights, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$v['apartment_id'], $v['label_it'], $v['label_en'], $v['start_date'], $v['end_date'], $v['nightly_rate_cents'], $v['min_nights'], (int) $v['is_active']]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $v normalised values */
    public function updateRate(int $id, array $v): void
    {
        $this->db->prepare(
            'UPDATE seasonal_rates SET label = ?, label_en = ?, start_date = ?, end_date = ?, nightly_rate_cents = ?, min_nights = ?, is_active = ?
             WHERE id = ?'
        )->execute([$v['label_it'], $v['label_en'], $v['start_date'], $v['end_date'], $v['nightly_rate_cents'], $v['min_nights'], (int) $v['is_active'], $id]);
    }

    public function deleteRate(int $id): void
    {
        $this->db->prepare('DELETE FROM seasonal_rates WHERE id = ?')->execute([$id]);
    }

    // --- charge rules: configuration ---------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function rule(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pricing_rules WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param array<string, mixed> $v normalised values */
    public function insertRule(array $v): int
    {
        $this->db->prepare(
            'INSERT INTO pricing_rules
                (apartment_id, applies_to, charge_basis, free_units, amount_cents, valid_from, valid_to, is_active, sort_order, label_it, label_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $v['apartment_id'], $v['applies_to'], $v['charge_basis'], $v['free_units'], $v['amount_cents'],
            $v['valid_from'], $v['valid_to'], (int) $v['is_active'], $v['sort_order'], $v['label_it'], $v['label_en'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $v normalised values */
    public function updateRule(int $id, array $v): void
    {
        $this->db->prepare(
            'UPDATE pricing_rules SET apartment_id = ?, applies_to = ?, charge_basis = ?, free_units = ?, amount_cents = ?,
                    valid_from = ?, valid_to = ?, is_active = ?, sort_order = ?, label_it = ?, label_en = ?
             WHERE id = ?'
        )->execute([
            $v['apartment_id'], $v['applies_to'], $v['charge_basis'], $v['free_units'], $v['amount_cents'],
            $v['valid_from'], $v['valid_to'], (int) $v['is_active'], $v['sort_order'], $v['label_it'], $v['label_en'], $id,
        ]);
    }

    public function deleteRule(int $id): void
    {
        $this->db->prepare('DELETE FROM pricing_rules WHERE id = ?')->execute([$id]);
    }
}
