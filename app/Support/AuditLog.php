<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Records what changed in the system (SPEC §15). There is a single shared admin
 * account, so the actor is not stored.
 */
final class AuditLog
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(
        string $entityType,
        ?int $entityId,
        string $action,
        ?string $summary = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $this->db->prepare(
            'INSERT INTO audit_log (entity_type, entity_id, action, summary, old_values, new_values)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $entityType,
            $entityId,
            $action,
            $summary,
            $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
            $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
