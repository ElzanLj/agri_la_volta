<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/**
 * Read-only readiness check for a production installation (docs/RELEASE_GUIDE.md).
 *
 * It looks at the configuration, the files and the database the application is pointed at. It never
 * contacts SMTP or any other external service, never writes anything, and never reports a secret:
 * messages only say whether a value is set. Results are `ok`, `warn` (should be fixed, not blocking)
 * or `fail` (must be fixed before the site goes live).
 */
final class ProductionCheck
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'ctype', 'json', 'session', 'openssl', 'hash', 'filter'];
    private const NOT_FOR_PRODUCTION = ['tests', 'docs', 'docker', 'docker-compose.yml', 'prompts', 'node_modules', '.phpunit.cache'];

    /** @var list<array{level: string, check: string, message: string}> */
    private array $results = [];

    /**
     * @param array<string, string> $env configuration values by name ("" when not set)
     * @param string $root project root
     * @param PDO|null $db connection to the configured database, or null when it could not be opened
     * @param string|null $dbProblem short reason when $db is null (never the server's message)
     * @param array{version: string, extensions: list<string>}|null $php defaults to the running PHP
     */
    public function __construct(
        private array $env,
        private string $root,
        private ?PDO $db,
        private ?string $dbProblem = null,
        private ?array $php = null,
    ) {
    }

    /** @return list<array{level: string, check: string, message: string}> */
    public function run(): array
    {
        $this->results = [];
        $this->environment();
        $this->phpRuntime();
        $this->files();
        $this->database();
        $this->mail();
        $this->content();
        return $this->results;
    }

    /** @param list<array{level: string, check: string, message: string}> $results */
    public static function count(array $results, string $level): int
    {
        return count(array_filter($results, static fn (array $r): bool => $r['level'] === $level));
    }

    private function add(string $level, string $check, string $message): void
    {
        $this->results[] = ['level' => $level, 'check' => $check, 'message' => $message];
    }

    private function value(string $key): string
    {
        return trim($this->env[$key] ?? '');
    }

    // === Configuration ======================================================

    private function environment(): void
    {
        $this->value('APP_ENV') === 'production'
            ? $this->add(self::OK, 'APP_ENV', 'production')
            : $this->add(self::FAIL, 'APP_ENV', 'deve essere "production" (oggi: "' . $this->value('APP_ENV') . '")');

        in_array(strtolower($this->value('APP_DEBUG')), ['', '0', 'false', 'no', 'off'], true)
            ? $this->add(self::OK, 'APP_DEBUG', 'spento')
            : $this->add(self::WARN, 'APP_DEBUG', 'acceso: in produzione viene ignorato, ma va messo a false');

        $url = $this->value('APP_URL');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') === '' || ($parts['host'] ?? '') === '') {
            $this->add(self::FAIL, 'APP_URL', 'mancante o non valido (es. https://www.agriturismolavolta.com)');
        } elseif (($parts['scheme']) !== 'https') {
            $this->add(self::FAIL, 'APP_URL', 'deve iniziare con https:// (cookie Secure e HSTS dipendono da questo)');
        } elseif (in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($parts['host'], '.test') || str_ends_with($parts['host'], '.local')) {
            $this->add(self::FAIL, 'APP_URL', 'punta a un indirizzo locale o di prova');
        } elseif (str_ends_with($url, '/') || isset($parts['query']) || isset($parts['fragment'])) {
            $this->add(self::FAIL, 'APP_URL', 'senza barra finale, parametri o frammento');
        } else {
            $this->add(self::OK, 'APP_URL', 'https, host ' . $parts['host']);
        }

        $secret = $this->value('APP_SECRET');
        if ($secret === '') {
            $this->add(self::WARN, 'APP_SECRET', 'non impostato: viene derivato dalle credenziali del database; impostare una stringa casuale lunga');
        } elseif (strlen($secret) < 32) {
            $this->add(self::WARN, 'APP_SECRET', 'troppo corto (almeno 32 caratteri)');
        } else {
            $this->add(self::OK, 'APP_SECRET', 'impostato');
        }

        $hsts = $this->value('HSTS_MAX_AGE');
        if ($hsts !== '' && ctype_digit($hsts) && (int) $hsts === 0) {
            $this->add(self::WARN, 'HSTS_MAX_AGE', 'HSTS disattivato: attivarlo quando HTTPS funziona su tutto il dominio');
        } else {
            $this->add(self::OK, 'HSTS_MAX_AGE', $hsts === '' ? 'predefinito (180 giorni, solo con https)' : $hsts . ' secondi');
        }

        $retention = $this->value('DATA_RETENTION_MONTHS');
        $retention === '' || !ctype_digit($retention) || (int) $retention < 1
            ? $this->add(self::WARN, 'DATA_RETENTION_MONTHS', 'non impostato: nessuna pulizia dei dati personali (decisione del titolare)')
            : $this->add(self::OK, 'DATA_RETENTION_MONTHS', $retention . ' mesi (la pulizia va lanciata con bin/privacy.php purge)');
    }

    // === PHP and files ======================================================

    private function phpRuntime(): void
    {
        $php = $this->php ?? ['version' => PHP_VERSION, 'extensions' => get_loaded_extensions()];
        version_compare($php['version'], '8.1.0', '>=')
            ? $this->add(self::OK, 'PHP', $php['version'])
            : $this->add(self::FAIL, 'PHP', 'serve 8.1 o superiore (oggi ' . $php['version'] . ')');

        $loaded = array_map('strtolower', $php['extensions']);
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, static fn (string $e): bool => !in_array($e, $loaded, true)));
        $missing === []
            ? $this->add(self::OK, 'Estensioni PHP', implode(', ', self::REQUIRED_EXTENSIONS))
            : $this->add(self::FAIL, 'Estensioni PHP', 'mancano: ' . implode(', ', $missing));
    }

    private function files(): void
    {
        $vendor = $this->root . '/vendor';
        if (!is_file($vendor . '/autoload.php') || !is_dir($vendor . '/phpmailer/phpmailer')) {
            $this->add(self::FAIL, 'vendor/', 'manca (o manca PHPMailer): generarla con "composer install --no-dev --optimize-autoloader" e caricarla');
        } elseif (is_dir($vendor . '/phpunit')) {
            $this->add(self::WARN, 'vendor/', 'contiene PHPUnit: generarla con --no-dev');
        } else {
            $this->add(self::OK, 'vendor/', 'PHPMailer presente, senza strumenti di sviluppo');
        }

        foreach (['logs', 'sessions'] as $dir) {
            $path = $this->root . '/storage/' . $dir;
            is_dir($path) && is_writable($path)
                ? $this->add(self::OK, 'storage/' . $dir, 'scrivibile')
                : $this->add(self::FAIL, 'storage/' . $dir, 'manca o non è scrivibile da PHP');
        }

        $envFile = $this->root . '/.env';
        if (is_file($envFile)) {
            $mode = fileperms($envFile) & 0777;
            ($mode & 0004) !== 0 && DIRECTORY_SEPARATOR === '/'
                ? $this->add(self::WARN, '.env', 'leggibile da tutti gli utenti del server: ridurre i permessi (es. 640 o 600)')
                : $this->add(self::OK, '.env', 'presente');
        } else {
            $this->add(self::OK, '.env', 'file assente: le impostazioni arrivano dalle variabili del pannello');
        }
        if (is_file($this->root . '/public/.env') || is_dir($this->root . '/public/.git')) {
            $this->add(self::FAIL, 'public/', 'contiene .env o .git: file riservati dentro la cartella pubblica');
        }

        $extras = array_values(array_filter(self::NOT_FOR_PRODUCTION, fn (string $n): bool => file_exists($this->root . '/' . $n)));
        $extras === []
            ? $this->add(self::OK, 'File di sviluppo', 'nessuno')
            : $this->add(self::WARN, 'File di sviluppo', 'presenti (non servono in produzione): ' . implode(', ', $extras));
    }

    // === Database ===========================================================

    private function database(): void
    {
        if ($this->db === null) {
            $this->add(self::FAIL, 'Database', 'non raggiungibile' . ($this->dbProblem !== null ? ' (' . $this->dbProblem . ')' : '') . ': controllare DB_HOST, DB_NAME, DB_USER, DB_PASSWORD');
            return;
        }
        $name = $this->value('DB_NAME');
        str_ends_with($name, '_test')
            ? $this->add(self::FAIL, 'Database', 'il nome finisce con _test: è il database dei test, non quello di produzione')
            : $this->add(self::OK, 'Database', 'raggiungibile');

        try {
            $applied = $this->db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $this->add(self::FAIL, 'Migrazioni', 'tabella schema_migrations assente: importare le migrazioni (migrations/*.sql)');
            return;
        }
        $files = array_map(static fn (string $f): string => basename($f, '.sql'), glob($this->root . '/migrations/*.sql') ?: []);
        sort($files);
        $pending = array_values(array_diff($files, $applied));
        $pending === [] && $files !== []
            ? $this->add(self::OK, 'Migrazioni', count($files) . ' applicate')
            : $this->add(self::FAIL, 'Migrazioni', $files === [] ? 'nessun file in migrations/' : 'da applicare: ' . implode(', ', $pending));

        try {
            $admins = (int) $this->db->query('SELECT COUNT(*) FROM admin')->fetchColumn();
            if ($admins < 1) {
                $this->add(self::FAIL, 'Amministratore', 'nessun account: crearlo con bin/create-admin.php o con SQL (docs/INSTALL_SHARED_HOSTING.md)');
            } else {
                $hash = (string) $this->db->query('SELECT password_hash FROM admin LIMIT 1')->fetchColumn();
                password_get_info($hash)['algo'] === null
                    ? $this->add(self::FAIL, 'Amministratore', 'la password non è un hash valido')
                    : $this->add(self::OK, 'Amministratore', 'account presente');
            }
            $rates = (int) $this->db->query('SELECT COUNT(*) FROM seasonal_rates WHERE is_active = 1')->fetchColumn();
            $rates > 0
                ? $this->add(self::OK, 'Listino', $rates . ' periodi di tariffa attivi')
                : $this->add(self::WARN, 'Listino', 'nessuna tariffa: le richieste resteranno "prezzo da confermare"');
            $apartments = (int) $this->db->query('SELECT COUNT(*) FROM apartments WHERE is_active = 1')->fetchColumn();
            $apartments > 0
                ? $this->add(self::OK, 'Appartamenti', $apartments . ' attivi')
                : $this->add(self::FAIL, 'Appartamenti', 'nessun appartamento attivo');
            $texts = (int) $this->db->query("SELECT COUNT(DISTINCT apartment_id) FROM apartment_translations WHERE description IS NOT NULL AND description <> ''")->fetchColumn();
            $texts >= $apartments && $apartments > 0
                ? $this->add(self::OK, 'Descrizioni', 'inserite per tutti gli appartamenti attivi')
                : $this->add(self::WARN, 'Descrizioni', 'mancano per ' . max(0, $apartments - $texts) . ' appartamenti attivi (Admin → Appartamenti)');
        } catch (Throwable) {
            $this->add(self::FAIL, 'Dati', 'tabelle non leggibili: schema incompleto');
        }
    }

    // === Mail and public details ===============================================

    private function mail(): void
    {
        $transport = $this->value('MAIL_TRANSPORT') ?: 'smtp';
        if ($transport !== 'smtp') {
            $this->add(self::FAIL, 'MAIL_TRANSPORT', '"' . $transport . '" non è ammesso in produzione: usare smtp');
            return;
        }
        $this->add(self::OK, 'MAIL_TRANSPORT', 'smtp');

        $this->value('SMTP_HOST') === ''
            ? $this->add(self::FAIL, 'SMTP_HOST', 'mancante: senza, le email restano in coda')
            : $this->add(self::OK, 'SMTP_HOST', 'impostato');
        $port = $this->value('SMTP_PORT');
        $port === '' || (ctype_digit($port) && (int) $port >= 1 && (int) $port <= 65535)
            ? $this->add(self::OK, 'SMTP_PORT', $port === '' ? 'predefinita' : $port)
            : $this->add(self::FAIL, 'SMTP_PORT', 'non valida');
        in_array(strtolower($this->value('SMTP_ENCRYPTION')), ['tls', 'ssl'], true)
            ? $this->add(self::OK, 'SMTP_ENCRYPTION', strtolower($this->value('SMTP_ENCRYPTION')))
            : $this->add(self::WARN, 'SMTP_ENCRYPTION', 'nessuna cifratura (usare tls o ssl)');
        $this->value('SMTP_USERNAME') !== '' && $this->value('SMTP_PASSWORD') !== ''
            ? $this->add(self::OK, 'SMTP_USERNAME / SMTP_PASSWORD', 'impostati')
            : $this->add(self::WARN, 'SMTP_USERNAME / SMTP_PASSWORD', 'non impostati: la maggior parte dei provider richiede l\'autenticazione');

        foreach (['MAIL_FROM_ADDRESS', 'MAIL_ADMIN_ADDRESS'] as $key) {
            filter_var($this->value($key), FILTER_VALIDATE_EMAIL) !== false
                ? $this->add(self::OK, $key, 'indirizzo valido')
                : $this->add(self::FAIL, $key, 'mancante o non valido');
        }
    }

    private function content(): void
    {
        $missing = [];
        foreach (['PUBLIC_PHONE' => 'telefono', 'PUBLIC_EMAIL' => 'email', 'PUBLIC_ADDRESS' => 'indirizzo', 'WHATSAPP_NUMBER' => 'WhatsApp'] as $key => $label) {
            if ($this->value($key) === '') {
                $missing[] = $label;
            }
        }
        $missing === []
            ? $this->add(self::OK, 'Recapiti pubblici', 'tutti compilati')
            : $this->add(self::WARN, 'Recapiti pubblici', 'non compilati: ' . implode(', ', $missing) . ' (il sito li omette)');
    }
}
