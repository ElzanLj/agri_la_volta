<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\AuditLog;
use PDO;

/**
 * Changes to the admin account that come from the Account page. Every change is written to the history
 * with a fixed sentence: never the password, never a hash, never a value.
 */
final class AccountService
{
    public function __construct(private PDO $db)
    {
    }

    /** Stores the new password. The caller has already checked the current password and the policy. */
    public function changePassword(int $adminId, string $newPassword): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE admin SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);
            (new AuditLog($this->db))->record('admin', $adminId, 'password_changed', 'Password cambiata dall\'area amministrativa');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Ends every session of the account by raising the version stored in the database. */
    public function closeAllSessions(int $adminId): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE admin SET session_version = session_version + 1 WHERE id = ?')->execute([$adminId]);
            (new AuditLog($this->db))->record('admin', $adminId, 'sessions_closed', 'Tutte le sessioni chiuse dall\'area amministrativa');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** The stored hash of the admin, for the "same as the current password" check. */
    public function currentHash(int $adminId): ?string
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM admin WHERE id = ?');
        $stmt->execute([$adminId]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) ? $hash : null;
    }
}
