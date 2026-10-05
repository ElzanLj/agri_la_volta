<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ApartmentRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM apartments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Locking read: the apartment row is the per-apartment mutex that serialises every
     * operation able to change its occupation. Must be called inside a transaction.
     *
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM apartments WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
