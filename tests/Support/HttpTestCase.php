<?php

declare(strict_types=1);

namespace Tests\Support;

use App\App;
use App\Http\Router;

/**
 * Base class for tests that talk to the real application over HTTP (PHP built-in server on the
 * test database). Provides a known admin account, logged-in clients and database snapshots.
 */
abstract class HttpTestCase extends DatabaseTestCase
{
    protected const ADMIN_USER = 'admin-test';
    protected const ADMIN_PASSWORD = 'correct horse battery staple 42';

    /** Tables whose content must not change when a request is refused. */
    protected const WATCHED_TABLES = [
        'apartments', 'apartment_translations', 'booking_requests', 'bookings', 'availability_blocks',
        'seasonal_rates', 'pricing_rules', 'audit_log', 'admin',
    ];

    protected static ?TestServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = TestServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->exec('DELETE FROM admin');
        $this->db->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')
            ->execute([self::ADMIN_USER, password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT)]);
    }

    protected function client(): HttpClient
    {
        return new HttpClient(self::$server->baseUrl());
    }

    /** A client with a valid admin session. */
    protected function loggedInClient(): HttpClient
    {
        $client = $this->client();
        $login = $client->get('/admin/login');
        $token = $login->csrfToken();
        self::assertNotNull($token, 'the login page must carry a CSRF token');

        $response = $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => self::ADMIN_PASSWORD]);
        self::assertSame(303, $response->status, 'login failed: ' . $response->body);
        self::assertStringEndsWith('/admin', (string) $response->location());
        return $client;
    }

    /** The CSRF token valid for the client's session (read from a page that has a form). */
    protected function tokenFor(HttpClient $client, string $page = '/admin'): string
    {
        $token = $client->get($page)->csrfToken();
        self::assertNotNull($token, "no CSRF token on $page");
        return $token;
    }

    /** @return array<string, string> table => checksum, for the watched tables */
    protected function snapshot(): array
    {
        $rows = $this->db->query('CHECKSUM TABLE ' . implode(', ', self::WATCHED_TABLES))->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['Table']] = (string) $row['Checksum'];
        }
        return $result;
    }

    protected function assertDatabaseUnchanged(array $before, string $message): void
    {
        self::assertSame($before, $this->snapshot(), $message);
    }

    /**
     * Every registered route under /admin, with {id}-style placeholders replaced by "1".
     *
     * @return list<array{method: string, pattern: string, path: string}>
     */
    protected function adminRoutes(): array
    {
        $router = new Router();
        (require dirname(__DIR__, 2) . '/app/routes.php')($router, App::current());

        $routes = [];
        foreach ($router->routes() as $route) {
            if ($route['pattern'] === '/admin' || str_starts_with($route['pattern'], '/admin/')) {
                $routes[] = $route + ['path' => (string) preg_replace('/\{[a-z_]+\}/', '1', $route['pattern'])];
            }
        }
        return $routes;
    }

    /** A request/booking pair for tests that need data to act on. @return array{id: int, reference: string} */
    protected function makeRequest(string $in = '2027-06-10', string $out = '2027-06-15', array $overrides = []): array
    {
        return $this->service->createRequest($this->requestInput($in, $out, $overrides));
    }

    protected function countRows(string $table, string $where = '1=1'): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    }
}
