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
     * Active apartments for the public pages, with the text of the given language (NULL when the
     * admin has not written it yet: the pages then simply omit it).
     *
     * @return list<array<string, mixed>>
     */
    public function listPublic(string $locale): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, t.description, t.rules, t.meta_title, t.meta_description
             FROM apartments a
             LEFT JOIN apartment_translations t ON t.apartment_id = a.id AND t.locale = ?
             WHERE a.is_active = 1
             ORDER BY a.sort_order, a.id'
        );
        $stmt->execute([$locale]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findPublicBySlug(string $slug, string $locale): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, t.description, t.rules, t.meta_title, t.meta_description
             FROM apartments a
             LEFT JOIN apartment_translations t ON t.apartment_id = a.id AND t.locale = ?
             WHERE a.is_active = 1 AND a.slug = ?'
        );
        $stmt->execute([$locale, $slug]);
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
