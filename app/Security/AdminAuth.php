<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Login for the single shared admin account (SPEC §14). Accounts are created only from the
 * command line (bin/create-admin.php); there is no public sign-up.
 *
 * The session is bound to a fingerprint of the stored password hash: changing the password
 * (or deleting the account) ends every open session on its next request.
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
        $hash = (string) $row['password_hash'];
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $this->db->prepare('UPDATE admin SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        }
        $this->db->prepare('UPDATE admin SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);

        Session::regenerate(); // new session id: defeats session fixation
        Csrf::rotate();
        $now = time();
        $_SESSION['admin'] = ['id' => $id, 'login_at' => $now, 'seen_at' => $now, 'pwfp' => self::fingerprint($hash)];
        return true;
    }

    /** True when the request belongs to a live admin session that is still valid in the database. */
    public function isAuthenticated(): bool
    {
        if (!Session::hasCookie()) {
            return false; // anonymous traffic never creates a session
        }
        Session::start();

        $admin = $_SESSION['admin'] ?? null;
        if (!is_array($admin) || !isset($admin['id'], $admin['login_at'], $admin['seen_at'], $admin['pwfp'])) {
            return false;
        }

        $now = time();
        if ($now - (int) $admin['seen_at'] > self::IDLE_TIMEOUT || $now - (int) $admin['login_at'] > self::ABSOLUTE_TIMEOUT) {
            unset($_SESSION['admin']);
            return false;
        }

        $stmt = $this->db->prepare('SELECT password_hash FROM admin WHERE id = ?');
        $stmt->execute([(int) $admin['id']]);
        $hash = $stmt->fetchColumn();
        if (!is_string($hash) || !hash_equals((string) $admin['pwfp'], self::fingerprint($hash))) {
            unset($_SESSION['admin']); // account removed or password changed
            return false;
        }

        $_SESSION['admin']['seen_at'] = $now;
        return true;
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    private static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }
}
