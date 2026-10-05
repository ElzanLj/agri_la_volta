<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Database-level checks of the core booking invariants, independent of application code. */
final class Invariants
{
    /**
     * Counts violations of the rules that must always hold:
     * two confirmed bookings of one apartment sharing a night, or a confirmed booking
     * created over a block. Overlaps are evaluated with the half-open rule.
     */
    public static function overlapViolations(PDO $db): int
    {
        $bookingPairs = (int) $db->query(
            "SELECT COUNT(*) FROM bookings a JOIN bookings b
               ON a.apartment_id = b.apartment_id AND a.id < b.id
              AND a.status = 'confirmed' AND b.status = 'confirmed'
              AND a.check_in < b.check_out AND a.check_out > b.check_in"
        )->fetchColumn();

        $bookingOverBlock = (int) $db->query(
            "SELECT COUNT(*) FROM bookings a JOIN availability_blocks k
               ON a.apartment_id = k.apartment_id AND a.status = 'confirmed'
              AND a.check_in < k.end_date AND a.check_out > k.start_date"
        )->fetchColumn();

        return $bookingPairs + $bookingOverBlock;
    }
}
