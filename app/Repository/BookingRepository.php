<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** SQL for booking requests, bookings and availability blocks. No business rules here. */
final class BookingRepository
{
    public function __construct(private PDO $db)
    {
    }

    // --- booking requests -------------------------------------------------

    /** @param array<string, mixed> $row */
    public function insertRequest(array $row): int
    {
        $this->db->prepare(
            'INSERT INTO booking_requests
                (reference, apartment_id, check_in, check_out, adults, children, pets,
                 first_name, last_name, email, phone, notes, locale,
                 quoted_total_cents, price_breakdown, status, privacy_accepted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', UTC_TIMESTAMP())'
        )->execute([
            $row['reference'], $row['apartment_id'], $row['check_in'], $row['check_out'],
            $row['adults'], $row['children'], $row['pets'],
            $row['first_name'], $row['last_name'], $row['email'], $row['phone'], $row['notes'], $row['locale'],
            $row['quoted_total_cents'], $row['price_breakdown'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function request(int $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM booking_requests WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function setRequestStatus(int $id, string $status, bool $setDecidedAt): void
    {
        $this->db->prepare(
            'UPDATE booking_requests SET status = ?' . ($setDecidedAt ? ', decided_at = UTC_TIMESTAMP()' : '') . ' WHERE id = ?'
        )->execute([$status, $id]);
    }

    // --- bookings ---------------------------------------------------------

    /** @param array<string, mixed> $row */
    public function insertBooking(array $row): int
    {
        $this->db->prepare(
            'INSERT INTO bookings
                (apartment_id, booking_request_id, origin, status, check_in, check_out,
                 adults, children, pets, guest_name, email, phone, total_cents, notes)
             VALUES (?, ?, ?, \'confirmed\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $row['apartment_id'], $row['booking_request_id'], $row['origin'], $row['check_in'], $row['check_out'],
            $row['adults'], $row['children'], $row['pets'],
            $row['guest_name'], $row['email'], $row['phone'], $row['total_cents'], $row['notes'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function booking(int $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function markBookingCancelled(int $id, ?string $reason): void
    {
        $this->db->prepare(
            'UPDATE bookings SET status = \'cancelled\', cancelled_at = UTC_TIMESTAMP(), cancellation_reason = ? WHERE id = ?'
        )->execute([$reason, $id]);
    }

    // --- availability blocks ----------------------------------------------

    public function insertBlock(int $apartmentId, string $startDate, string $endDate, ?string $reason): int
    {
        $this->db->prepare(
            'INSERT INTO availability_blocks (apartment_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)'
        )->execute([$apartmentId, $startDate, $endDate, $reason]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function block(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM availability_blocks WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function deleteBlock(int $id): void
    {
        $this->db->prepare('DELETE FROM availability_blocks WHERE id = ?')->execute([$id]);
    }
}
