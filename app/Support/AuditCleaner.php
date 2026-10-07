<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Removes the free-text reasons that older versions copied into the history (audit_log) when a booking
 * was cancelled or a block was created or removed. Free text can contain names or health details, and
 * it survived the anonymisation of the guest. The history now records only "reason_present": true/false.
 *
 * Idempotent: running it again finds nothing to do. Every operation is a dry run unless $apply is true.
 */
final class AuditCleaner
{
    /** Entity types whose history rows used to carry a "reason". */
    private const ENTITY_TYPES = ['booking', 'availability_block'];

    public function __construct(private PDO $db)
    {
    }

    /** @return int number of history rows that carry (or carried) a free-text reason */
    public function clean(bool $apply): int
    {
        $stmt = $this->db->prepare(
            "SELECT id, old_values, new_values FROM audit_log
             WHERE entity_type IN ('booking', 'availability_block')
               AND (old_values LIKE '%\"reason\"%' OR new_values LIKE '%\"reason\"%')"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $changed = 0;
        $update = $this->db->prepare('UPDATE audit_log SET old_values = ?, new_values = ? WHERE id = ?');
        foreach ($rows as $row) {
            $old = self::withoutReason($row['old_values']);
            $new = self::withoutReason($row['new_values']);
            if ($old === $row['old_values'] && $new === $row['new_values']) {
                continue;
            }
            $changed++;
            if ($apply) {
                $update->execute([$old, $new, $row['id']]);
            }
        }
        return $changed;
    }

    /** The same JSON with "reason" replaced by "reason_present"; anything that is not a JSON object is left alone. */
    private static function withoutReason(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return $json;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !array_key_exists('reason', $data)) {
            return $json;
        }
        $reason = $data['reason'];
        unset($data['reason']);
        $data['reason_present'] = is_string($reason) && trim($reason) !== '';
        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: $json;
    }
}
