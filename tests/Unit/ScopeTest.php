<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\App;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

/**
 * Scope guarantees of SPEC §3 and §41, checked on the code itself: no payment system, no card
 * data, no guest accounts, and only the admin area can decide about requests and stays.
 * (The Firebase-era `legacy/` folder is reference material, not part of the site, and is excluded.)
 */
final class ScopeTest extends TestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = dirname(__DIR__, 2);
    }

    /** @return list<string> every source file that makes up the site */
    private function siteFiles(): array
    {
        $files = [];
        foreach (['app', 'templates', 'content', 'bin', 'migrations', 'public'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.(php|sql|css|js|svg|htaccess|txt|json)$/', $file->getFilename()) === 1) {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach (['composer.json', 'composer.lock', 'docker-compose.yml', '.env.example'] as $file) {
            if (is_file(self::$root . '/' . $file)) {
                $files[] = self::$root . '/' . $file;
            }
        }
        return $files;
    }

    public function testTheScanSeesTheWholeSite(): void
    {
        $files = $this->siteFiles();

        self::assertGreaterThan(100, count($files));
        self::assertNotEmpty(array_filter($files, static fn (string $f): bool => str_ends_with($f, 'BookingService.php')));
        self::assertNotEmpty(array_filter($files, static fn (string $f): bool => str_contains($f, 'migrations')));
    }

    public function testNoPaymentProviderAndNoCardOrBankFieldExistsAnywhere(): void
    {
        $pattern = '/\b(stripe|paypal|braintree|adyen|nexi|satispay|klarna|mollie|payplug|worldpay|checkout\.session|payment[_-]?intent|card[_-]?number|numero[_ ]carta|cvv|cvc|iban|bic|swift|carta di credito|credit card|debit card|cc-number|cc-exp|cc-csc|expiry[_-]?date|pagamento online)\b/i';
        $hits = [];
        foreach ($this->siteFiles() as $file) {
            if (str_ends_with($file, 'composer.lock')) {
                continue; // third-party metadata is checked by name below
            }
            if (preg_match_all($pattern, (string) file_get_contents($file), $m) > 0) {
                $hits[] = str_replace(self::$root . '/', '', $file) . ': ' . implode(', ', array_unique($m[0]));
            }
        }

        self::assertSame([], $hits, "possible payment code or card data:\n" . implode("\n", $hits));
    }

    public function testDependenciesAreOnlyMailAndTestingLibraries(): void
    {
        $composer = json_decode((string) file_get_contents(self::$root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['php', 'phpmailer/phpmailer'], array_keys($composer['require']));
        self::assertSame(['phpunit/phpunit'], array_keys($composer['require-dev']));

        $lock = json_decode((string) file_get_contents(self::$root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
            self::assertDoesNotMatchRegularExpression('/stripe|paypal|payment|braintree|adyen|mollie|klarna/i', $package['name'], 'payment library: ' . $package['name']);
        }
    }

    public function testThePublicSiteAsksOnlyForTheDataOfTheRequestFormAndNoPassword(): void
    {
        $allowed = ['_form', 'contact_website', 'check_in', 'check_out', 'adults', 'children', 'pets', 'apartment', 'first_name', 'last_name', 'email', 'phone', 'notes', 'privacy_accepted'];
        $names = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$root . '/templates/public', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $source = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString('type="password"', $source, $file->getFilename());
            preg_match_all('#<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"#', $source, $m);
            foreach ($m[1] as $name) {
                $names[$name] = $file->getFilename();
            }
        }
        // The summary and details forms copy the same fields through a PHP loop over $fields / $hidden.
        foreach (array_keys($names) as $name) {
            if (!str_contains($name, '<?')) {
                self::assertContains($name, $allowed, "unexpected public form field: $name in " . $names[$name]);
            }
        }
        self::assertNotEmpty($names);
    }

    public function testNoGuestAccountsExistInTheDatabaseSchema(): void
    {
        $tables = [];
        foreach (glob(self::$root . '/migrations/*.sql') ?: [] as $migration) {
            preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?([a-z_]+)`?/i', (string) file_get_contents($migration), $m);
            array_push($tables, ...$m[1]);
        }
        sort($tables);

        // Exactly these tables: a customer/user/account table must be a deliberate, reviewed change.
        self::assertSame([
            'admin', 'apartment_translations', 'apartments', 'audit_log', 'availability_blocks', 'booking_requests',
            'bookings', 'email_outbox', 'pricing_rules', 'rate_limit_hits', 'schema_migrations', 'seasonal_rates',
        ], $tables);
    }

    public function testTheOnlyLoginIsTheAdminLoginAndThereIsNoSignUp(): void
    {
        $router = new Router();
        (require self::$root . '/app/routes.php')($router, App::current());

        $suspicious = [];
        foreach ($router->routes() as $route) {
            $pattern = $route['pattern'];
            if (preg_match('#login|logout|register|registr|signup|sign-up|account|profil|iscri|password|reset|utente|user#i', $pattern) === 1) {
                $suspicious[] = $route['method'] . ' ' . $pattern;
            }
        }
        sort($suspicious);

        self::assertSame(['GET /admin/login', 'POST /admin/login', 'POST /admin/logout'], $suspicious);
    }

    public function testOnlyTheAdminControllersCanDecideAboutRequestsAndStays(): void
    {
        $decisions = '/\b(confirmRequest|rejectRequest|cancelBooking|createManualBooking|createBlock|removeBlock)\s*\(/';
        $callers = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$root . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'BookingService.php') {
                continue;
            }
            if (preg_match($decisions, (string) file_get_contents($file->getPathname())) === 1) {
                $callers[] = str_replace('\\', '/', substr($file->getPathname(), strlen(self::$root) + 1));
            }
        }
        sort($callers);

        // Every caller is an admin controller (guarded by RequireAdmin + VerifyCsrf on the /admin prefix).
        foreach ($callers as $caller) {
            self::assertStringStartsWith('app/Http/Controllers/Admin/', $caller, "$caller can change bookings but is not an admin controller");
        }
        self::assertSame(['app/Http/Controllers/Admin/BlockController.php', 'app/Http/Controllers/Admin/BookingController.php', 'app/Http/Controllers/Admin/RequestController.php'], $callers);
    }

    public function testThePublicFlowOnlyEverCreatesPendingRequests(): void
    {
        $flow = (string) file_get_contents(self::$root . '/app/Http/Controllers/Site/RequestFlowController.php');

        self::assertStringContainsString('createRequest(', $flow);
        self::assertStringContainsString('previewRequest(', $flow);
        self::assertDoesNotMatchRegularExpression('/status.{0,20}confirmed/i', $flow);

        $schema = (string) file_get_contents(self::$root . '/migrations/0001_initial_schema.sql');
        self::assertMatchesRegularExpression("/CREATE TABLE booking_requests.*?status VARCHAR\(20\) NOT NULL DEFAULT 'pending'/s", $schema, 'a new request is pending by default, in the schema itself');
    }

    public function testNoPublicTextCallsARequestAConfirmedBooking(): void
    {
        foreach (['it', 'en'] as $locale) {
            foreach (require self::$root . '/content/' . $locale . '.php' as $key => $text) {
                if (!str_starts_with($key, 'flow.') && !str_starts_with($key, 'request.') && !str_starts_with($key, 'form.')) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression('/prenotazione confermata|booking confirmed|reservation confirmed|prenotazione effettuata|booked successfully/i', $text, "$locale:$key");
            }
        }
    }
}
