<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\GuestCounts;
use App\Domain\StayDates;
use PDO;

/**
 * Read-only availability queries. Only confirmed bookings and blocks occupy dates;
 * pending requests never do. Overlap test for half-open intervals:
 * existing.start < new.end AND existing.end > new.start.
 */
final class AvailabilityRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Confirmed bookings (and, optionally, blocks) of an apartment that share a night with $stay.
     *
     * @return list<array{type: string, id: int, start_date: string, end_date: string}>
     */
    public function conflicts(int $apartmentId, StayDates $stay, bool $includeBlocks = true): array
    {
        $sql = "SELECT 'booking' AS type, id, check_in AS start_date, check_out AS end_date
                FROM bookings
                WHERE apartment_id = ? AND status = 'confirmed' AND check_in < ? AND check_out > ?";
        $params = [$apartmentId, $stay->checkOut, $stay->checkIn];

        if ($includeBlocks) {
            $sql .= " UNION ALL
                SELECT 'block' AS type, id, start_date, end_date
                FROM availability_blocks
                WHERE apartment_id = ? AND start_date < ? AND end_date > ?";
            array_push($params, $apartmentId, $stay->checkOut, $stay->checkIn);
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY start_date, id');
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => [
                'type' => (string) $row['type'],
                'id' => (int) $row['id'],
                'start_date' => (string) $row['start_date'],
                'end_date' => (string) $row['end_date'],
            ],
            $stmt->fetchAll(),
        );
    }

    /**
     * Active apartments with no confirmed booking and no block in the interval.
     *
     * @return list<array<string, mixed>>
     */
    public function availableApartments(StayDates $stay, ?GuestCounts $guests = null, bool $onlineRequestsOnly = true): array
    {
        $sql = 'SELECT a.id, a.slug, a.name, a.max_guests, a.indicative_price_cents
                FROM apartments a
                WHERE a.is_active = 1
                  AND NOT EXISTS (SELECT 1 FROM bookings b
                                  WHERE b.apartment_id = a.id AND b.status = \'confirmed\'
                                    AND b.check_in < ? AND b.check_out > ?)
                  AND NOT EXISTS (SELECT 1 FROM availability_blocks k
                                  WHERE k.apartment_id = a.id AND k.start_date < ? AND k.end_date > ?)';
        $params = [$stay->checkOut, $stay->checkIn, $stay->checkOut, $stay->checkIn];

        if ($onlineRequestsOnly) {
            $sql .= ' AND a.accepts_online_requests = 1';
        }
        if ($guests !== null) {
            $sql .= ' AND (a.max_guests IS NULL OR a.max_guests >= ?)';
            $params[] = $guests->people();
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY a.sort_order, a.id');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
