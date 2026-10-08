<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Login for the single shared admin account (SPEC §14). The account is created only from the
 * command line (bin/create-admin.php, also with --print-sql for hosting without SSH); there is no
 * public sign-up. The password can be changed from the Account page.
 *
 * The session is bound to a fingerprint of the stored password hash and to a session version:
 * changing the password (or deleting the account) or "close all devices" ends every other open
 * session on its next request.
 */
final class AdminAuth
{
    /** Failed password checks (login and re-confirmation): the dashboard and the Account page count them over 24 hours. */
    public const FAILED_BUCKET = 'admin_login_failed';

    private const IDLE_TIMEOUT = 7200;
    private const ABSOLUTE_TIMEOUT = 43200;

    public function __construct(private PDO $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT id, password_hash, session_version, last_login_at FROM admin WHERE username = ?');
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
        $_SESSION['admin'] = [
            'id' => $id,
            'login_at' => $now,
            'seen_at' => $now,
            'pwfp' => self::fingerprint($hash),
            'sv' => (int) $row['session_version'],
            // The previous successful login (read before this one overwrote it): shown on the Account page
            // so that an access that was not the owner's own can be noticed.
            'prev_login' => $row['last_login_at'] === null ? null : (string) $row['last_login_at'],
        ];
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

        $stmt = $this->db->prepare('SELECT password_hash, session_version FROM admin WHERE id = ?');
        $stmt->execute([(int) $admin['id']]);
        $row = $stmt->fetch();
        if ($row === false || !hash_equals((string) $admin['pwfp'], self::fingerprint((string) $row['password_hash']))) {
            unset($_SESSION['admin']); // account removed or password changed
            return false;
        }
        // "Close all devices" raised the version in the database: sessions that carry the old one end here.
        if ((int) ($admin['sv'] ?? 1) !== (int) $row['session_version']) {
            unset($_SESSION['admin']);
            return false;
        }

        $_SESSION['admin']['seen_at'] = $now;
        return true;
    }

    /** Id of the admin of the current (already authenticated) session, or null. */
    public function adminId(): ?int
    {
        $id = $_SESSION['admin']['id'] ?? null;
        return is_int($id) ? $id : null;
    }

    /**
     * True when $password is the current password of the logged-in admin. It changes nothing (no rehash,
     * no login date): it is the check behind "enter your current password again".
     */
    public function confirmPassword(string $password): bool
    {
        $id = $this->adminId();
        if ($id === null || $password === '' || strlen($password) > PasswordPolicy::MAX_LENGTH) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT password_hash FROM admin WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) && password_verify($password, $hash);
    }

    /**
     * Binds the CURRENT session to the password and session version now stored in the database, with a new
     * session id and a new CSRF token. Call it after the password was changed or the other sessions were
     * closed: every other session fails its next check, this one stays valid.
     */
    public function refreshCurrentSession(): void
    {
        $id = $this->adminId();
        if ($id === null) {
            return;
        }
        $stmt = $this->db->prepare('SELECT password_hash, session_version FROM admin WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return;
        }
        Session::regenerate();
        Csrf::rotate();
        $_SESSION['admin']['pwfp'] = self::fingerprint((string) $row['password_hash']);
        $_SESSION['admin']['sv'] = (int) $row['session_version'];
        $_SESSION['admin']['seen_at'] = time();
        unset($_SESSION['reauth_at']); // a confirmation never survives such a change
    }

    /**
     * What the Account page shows about accesses.
     *
     * @return array{username: string, this_login: ?string, previous_login: ?string}
     */
    public function accessInfo(): array
    {
        $id = $this->adminId();
        $stmt = $this->db->prepare('SELECT username FROM admin WHERE id = ?');
        $stmt->execute([(int) $id]);
        $loginAt = $_SESSION['admin']['login_at'] ?? null;
        $previous = $_SESSION['admin']['prev_login'] ?? null;
        return [
            'username' => (string) $stmt->fetchColumn(),
            'this_login' => is_int($loginAt) ? gmdate('Y-m-d H:i:s', $loginAt) : null,
            'previous_login' => is_string($previous) ? $previous : null,
        ];
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
