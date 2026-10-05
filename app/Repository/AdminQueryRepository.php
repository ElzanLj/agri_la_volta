<?php

declare(strict_types=1);

namespace App\Repository;

use App\Http\Admin\ListFilters;
use PDO;

/** Read-only queries behind the admin lists, calendar, audit log and exports. No writes, no business rules. */
final class AdminQueryRepository
{
    private const EXPORT_LIMIT = 20000;

    public function __construct(private PDO $db)
    {
    }

    // --- dashboard ---------------------------------------------------------

    /** @return array{pending: int, upcoming: int, blocks: int, apartments: int} */
    public function dashboardCounts(string $today): array
    {
        $one = fn (string $sql, array $params = []): int => (int) $this->scalar($sql, $params);

        return [
            'pending' => $one("SELECT COUNT(*) FROM booking_requests WHERE status = 'pending'"),
            'upcoming' => $one("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND check_out > ?", [$today]),
            'blocks' => $one('SELECT COUNT(*) FROM availability_blocks WHERE end_date > ?', [$today]),
            'apartments' => $one('SELECT COUNT(*) FROM apartments'),
        ];
    }

    // --- requests ----------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function requests(ListFilters $f, bool $paginate = true): array
    {
        [$where, $params] = $this->requestWhere($f);
        $sql = 'SELECT r.*, a.name AS apartment_name FROM booking_requests r JOIN apartments a ON a.id = r.apartment_id'
            . $where . ' ORDER BY r.created_at DESC, r.id DESC'
            . ($paginate ? ' LIMIT ' . ListFilters::PER_PAGE . ' OFFSET ' . $f->offset() : ' LIMIT ' . self::EXPORT_LIMIT);
        return $this->all($sql, $params);
    }

    public function countRequests(ListFilters $f): int
    {
        [$where, $params] = $this->requestWhere($f);
        return (int) $this->scalar('SELECT COUNT(*) FROM booking_requests r' . $where, $params);
    }

    /** @return array<string, mixed>|null */
    public function request(int $id): ?array
    {
        return $this->all(
            'SELECT r.*, a.name AS apartment_name FROM booking_requests r JOIN apartments a ON a.id = r.apartment_id WHERE r.id = ?',
            [$id],
        )[0] ?? null;
    }

    /** @return array<string, mixed>|null the booking created from a request, if any */
    public function bookingOfRequest(int $requestId): ?array
    {
        return $this->all('SELECT * FROM bookings WHERE booking_request_id = ?', [$requestId])[0] ?? null;
    }

    // --- bookings ----------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function bookings(ListFilters $f, bool $paginate = true): array
    {
        [$where, $params] = $this->bookingWhere($f);
        $sql = 'SELECT b.*, a.name AS apartment_name, r.reference AS request_reference
                FROM bookings b JOIN apartments a ON a.id = b.apartment_id
                LEFT JOIN booking_requests r ON r.id = b.booking_request_id'
            . $where . ' ORDER BY b.check_in DESC, b.id DESC'
            . ($paginate ? ' LIMIT ' . ListFilters::PER_PAGE . ' OFFSET ' . $f->offset() : ' LIMIT ' . self::EXPORT_LIMIT);
        return $this->all($sql, $params);
    }

    public function countBookings(ListFilters $f): int
    {
        [$where, $params] = $this->bookingWhere($f);
        return (int) $this->scalar('SELECT COUNT(*) FROM bookings b' . $where, $params);
    }

    /** @return array<string, mixed>|null */
    public function booking(int $id): ?array
    {
        return $this->all(
            'SELECT b.*, a.name AS apartment_name, r.reference AS request_reference
             FROM bookings b JOIN apartments a ON a.id = b.apartment_id
             LEFT JOIN booking_requests r ON r.id = b.booking_request_id WHERE b.id = ?',
            [$id],
        )[0] ?? null;
    }

    // --- blocks, calendar --------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function blocks(): array
    {
        return $this->all(
            'SELECT k.*, a.name AS apartment_name FROM availability_blocks k JOIN apartments a ON a.id = k.apartment_id
             ORDER BY k.start_date DESC, k.id DESC LIMIT 500'
        );
    }

    /** @return list<array<string, mixed>> confirmed bookings sharing a night with [$start, $end) */
    public function bookingsInRange(string $start, string $end): array
    {
        return $this->all(
            "SELECT id, apartment_id, check_in, check_out, guest_name FROM bookings
             WHERE status = 'confirmed' AND check_in < ? AND check_out > ?",
            [$end, $start],
        );
    }

    /** @return list<array<string, mixed>> */
    public function blocksInRange(string $start, string $end): array
    {
        return $this->all(
            'SELECT id, apartment_id, start_date, end_date, reason FROM availability_blocks WHERE start_date < ? AND end_date > ?',
            [$end, $start],
        );
    }

    // --- apartments --------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function apartments(): array
    {
        return $this->all('SELECT * FROM apartments ORDER BY sort_order, id');
    }

    /** @return array<string, mixed>|null */
    public function apartment(int $id): ?array
    {
        return $this->all('SELECT * FROM apartments WHERE id = ?', [$id])[0] ?? null;
    }

    /** @return array<string, array<string, mixed>> locale => translation row */
    public function apartmentTranslations(int $id): array
    {
        $rows = $this->all('SELECT * FROM apartment_translations WHERE apartment_id = ?', [$id]);
        return array_column($rows, null, 'locale');
    }

    // --- pricing -----------------------------------------------------------

    /** @return list<array<string, mixed>> all rate periods of an apartment, inactive ones included */
    public function ratesOf(int $apartmentId): array
    {
        return $this->all('SELECT * FROM seasonal_rates WHERE apartment_id = ? ORDER BY start_date, id', [$apartmentId]);
    }

    /** @return array<string, mixed>|null */
    public function rate(int $id): ?array
    {
        return $this->all('SELECT * FROM seasonal_rates WHERE id = ?', [$id])[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function rule(int $id): ?array
    {
        return $this->all('SELECT * FROM pricing_rules WHERE id = ?', [$id])[0] ?? null;
    }

    /** @return list<array<string, mixed>> rules for one apartment plus the global ones */
    public function rulesOf(int $apartmentId): array
    {
        return $this->all(
            'SELECT * FROM pricing_rules WHERE apartment_id IS NULL OR apartment_id = ? ORDER BY sort_order, id',
            [$apartmentId],
        );
    }

    // --- audit log ---------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function audit(ListFilters $f): array
    {
        [$where, $params] = $this->auditWhere($f);
        return $this->all(
            'SELECT * FROM audit_log' . $where . ' ORDER BY id DESC LIMIT ' . ListFilters::PER_PAGE . ' OFFSET ' . $f->offset(),
            $params,
        );
    }

    public function countAudit(ListFilters $f): int
    {
        [$where, $params] = $this->auditWhere($f);
        return (int) $this->scalar('SELECT COUNT(*) FROM audit_log' . $where, $params);
    }

    // --- internals ---------------------------------------------------------

    /** @return array{string, list<mixed>} */
    private function requestWhere(ListFilters $f): array
    {
        $clauses = [];
        $params = [];
        if ($f->status !== null) {
            $clauses[] = 'r.status = ?';
            $params[] = $f->status;
        }
        if ($f->apartmentId !== null) {
            $clauses[] = 'r.apartment_id = ?';
            $params[] = $f->apartmentId;
        }
        $this->periodClause('r', $f, $clauses, $params);
        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /** @return array{string, list<mixed>} */
    private function bookingWhere(ListFilters $f): array
    {
        $clauses = [];
        $params = [];
        if ($f->status !== null) {
            $clauses[] = 'b.status = ?';
            $params[] = $f->status;
        }
        if ($f->apartmentId !== null) {
            $clauses[] = 'b.apartment_id = ?';
            $params[] = $f->apartmentId;
        }
        if ($f->origin !== null) {
            $clauses[] = 'b.origin = ?';
            $params[] = $f->origin;
        }
        $this->periodClause('b', $f, $clauses, $params);
        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /** Stays that share a night with the period [from, to); either end may be open. */
    private function periodClause(string $alias, ListFilters $f, array &$clauses, array &$params): void
    {
        if ($f->from !== null) {
            $clauses[] = "{$alias}.check_out > ?";
            $params[] = $f->from;
        }
        if ($f->to !== null) {
            $clauses[] = "{$alias}.check_in < ?";
            $params[] = $f->to;
        }
    }

    /** @return array{string, list<mixed>} */
    private function auditWhere(ListFilters $f): array
    {
        return $f->entity === null ? ['', []] : [' WHERE entity_type = ?', [$f->entity]];
    }

    /** @return list<array<string, mixed>> */
    private function all(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
