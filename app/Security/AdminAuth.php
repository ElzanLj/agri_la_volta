<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Login for the single shared admin account (SPEC §14). Accounts are created
 * only from the command line (bin/create-admin.php); there is no public sign-up.
 */
final class AdminAuth
{
    private const IDLE_TIMEOUT = 7200;
    private const ABSOLUTE_TIMEOUT = 43200;

    public function __construct(private PDO $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT id, password_hash FROM admin WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Spend comparable time on unknown usernames so they cannot be told apart.
            password_verify($password, password_hash('unknown-user', PASSWORD_DEFAULT));
            return false;
        }
        if (!password_verify($password, $row['password_hash'])) {
            return false;
        }

        $id = (int) $row['id'];
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE admin SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        }
        $this->db->prepare('UPDATE admin SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);

        Session::regenerate();
        $now = time();
        $_SESSION['admin'] = ['id' => $id, 'login_at' => $now, 'seen_at' => $now];
        return true;
    }

    public static function check(): bool
    {
        Session::start();
        $admin = $_SESSION['admin'] ?? null;
        if (!is_array($admin)) {
            return false;
        }

        $now = time();
        if ($now - (int) $admin['seen_at'] > self::IDLE_TIMEOUT || $now - (int) $admin['login_at'] > self::ABSOLUTE_TIMEOUT) {
            unset($_SESSION['admin']);
            return false;
        }
        $_SESSION['admin']['seen_at'] = $now;
        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
