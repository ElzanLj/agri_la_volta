<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guardians that stay active for the whole roadmap: they fail when a later change breaks a rule
 * the project relies on. Each check also has a "deliberate violation" test, to prove it can fail.
 */
final class ArchitectureGuardTest extends TestCase
{
    /** The project root, without ".." so that relative paths can be cut from absolute ones. */
    private static function root(): string
    {
        return (string) realpath(__DIR__ . '/../..');
    }

    /** Output that is safe without `e()`: literal-only conditions and values reviewed one by one. */
    private const SAFE_ECHO = [
        // helpers that escape (or build escaped markup) themselves
        '/^e\(/', '/^url\(/', '/^lurl\(/', '/^asset\(/', '/^csrf_field\(\)$/', '/^field_error\(/', '/^invalid_attrs\(/',
        '/^(\\\\App\\\\Http\\\\)?View::capture\(/', '/^ImageSet::picture\(/', '/^nl2br\(e\(/',
        // closures defined in the same template; each one escapes what it prints
        '/^\$(input|area|form)\(/',
        // literal choices between fixed strings
        '/^\(int\) \$r\[\'[a-z_]+\'\] === 1 \? \'[^\'<>&"]*\' : \'[^\'<>&"]*\'$/',
        '/^old\(\$values, \'[a-z_]+\'(, \'[a-z]+\')?\) === \'[^\'<>&"]*\' \? \' (checked|selected)\' : \'\'$/',
        '/^\$lang === \'en\' \? \'[a-zA-Z_]+\' : \'[a-zA-Z_]+\'$/',
        '/^\$id === null \? \'[^\'<>&"]*\' : \'[^\'<>&"]*\'$/',
        '/^\$r\[\'management_mode\'\] === \'agency\' \? \'agenzia\' \. \(\$r\[\'managing_agency\'\] \? \' \(\' \. e\(\$r\[\'managing_agency\'\]\) \. \'\)\' : \'\'\) : \'diretta\'$/',
        '/^\$row\[\'[a-z_]+\'\] === null( \|\| \$row\[\'[a-z_]+\'\] === \'\')? \? \'—\' : nl2br\(e\(\$row\[\'[a-z_]+\'\]\)\)$/',
        // integers and fixed values: heading level, counters
        '/^\$level$/', '/^\(int\) \$[a-zA-Z_]+$/', '/^\$i$/',
    ];

    /** Raw HTML the layouts receive already rendered by a view, and the JSON-LD block (encoded with hex flags). */
    private const SAFE_ECHO_BY_FILE = [
        'templates/layout.php' => ['/^\$content$/', '/^json_encode\(\$data, \$jsonFlags\)$/'],
        'templates/admin/layout.php' => ['/^\$content$/'],
    ];

    /** @return list<string> template files, relative to the project root */
    private function templates(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/templates', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = 'templates/' . str_replace('\\', '/', substr($file->getPathname(), strlen(self::root() . '/templates') + 1));
            }
        }
        sort($files);
        return $files;
    }

    /**
     * Every `<?= expression ?>` that is not on the allowlist.
     *
     * @return list<string> "file: expression"
     */
    private function unsafeEchoes(string $file, string $source): array
    {
        preg_match_all('/<\?=\s*(.*?)\s*\?>/s', $source, $matches);
        $allowed = array_merge(self::SAFE_ECHO, self::SAFE_ECHO_BY_FILE[$file] ?? []);
        $bad = [];
        foreach ($matches[1] as $expression) {
            $expression = trim($expression);
            if ($this->isTernaryOfSafeValues($expression)) {
                continue;
            }
            foreach ($allowed as $pattern) {
                if (preg_match($pattern, $expression) === 1) {
                    continue 2;
                }
            }
            $bad[] = $file . ': ' . $expression;
        }
        return $bad;
    }

    /**
     * "condition ? A : B" where A and B can only be fixed text or escaped text: single-quoted literals,
     * `e(...)` calls, and their concatenations. Whatever the condition says, the output is safe.
     * Nested ternaries and anything else are not recognised (and so must be reviewed).
     */
    private function isTernaryOfSafeValues(string $expression): bool
    {
        $split = $this->splitTopLevel($expression, ['?', ':']);
        if ($split === null || count($split) !== 3) {
            return false;
        }
        foreach ([$split[1], $split[2]] as $branch) {
            $parts = $this->splitTopLevel(trim($branch), ['.']);
            if ($parts === null) {
                return false;
            }
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/^\'(?:[^\'\\\\]|\\\\.)*\'$/s', $part) !== 1 && preg_match('/^e\(.*\)$/s', $part) !== 1) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Splits at the given single characters when they are outside quotes and brackets.
     *
     * @param list<string> $separators
     * @return list<string>|null the pieces, or null when quotes or brackets are not balanced
     */
    private function splitTopLevel(string $text, array $separators): ?array
    {
        $pieces = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $text[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif (str_contains('([{', $char)) {
                $depth++;
            } elseif (str_contains(')]}', $char)) {
                $depth--;
            } elseif ($depth === 0 && in_array($char, $separators, true) && !($char === ':' && ($text[$i + 1] ?? '') === ':') && !($char === ':' && ($text[$i - 1] ?? '') === ':')) {
                // "??" and "?->" are not ternary separators
                if ($char === '?' && ($text[$i + 1] ?? '') === '?') {
                    $current .= '??';
                    $i++;
                    continue;
                }
                if ($char === '?' && ($text[$i + 1] ?? '') === '-') {
                    $current .= $char;
                    continue;
                }
                $pieces[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $pieces[] = $current;
        return $quote === null && $depth === 0 ? $pieces : null;
    }

    public function testEveryOutputInATemplateIsEscapedOrOnTheReviewedList(): void
    {
        $templates = $this->templates();
        self::assertGreaterThan(30, count($templates), 'the scan must find the templates');

        $bad = [];
        $echoes = 0;
        foreach ($templates as $file) {
            $source = (string) file_get_contents(self::root() . '/' . $file);
            $echoes += preg_match_all('/<\?=/', $source);
            array_push($bad, ...$this->unsafeEchoes($file, $source));
        }

        self::assertGreaterThan(500, $echoes, 'the scan must really read the outputs');
        self::assertSame([], $bad, "These outputs are not escaped with e() and are not on the reviewed list.\n"
            . 'Escape them, or review them and add a pattern to SAFE_ECHO with the reason.');
    }

    public function testTheOutputGuardianCatchesAnUnescapedValue(): void
    {
        $bad = $this->unsafeEchoes('templates/x.php', '<p><?= $row[\'notes\'] ?></p><p><?= e($row[\'notes\']) ?></p><p><?= $_GET[\'q\'] ?></p><p><?= $content ?></p>');

        self::assertSame(["templates/x.php: \$row['notes']", "templates/x.php: \$_GET['q']", 'templates/x.php: $content'], $bad);
    }

    public function testTheTernaryRuleAcceptsFixedValuesAndRefusesAnythingElse(): void
    {
        $bad = $this->unsafeEchoes('templates/x.php', implode('', [
            "<?= \$a === 1 ? ' selected' : '' ?>",
            "<?= \$a ? e(\$b) : '' ?>",
            "<?= \$a ? ', ' . e(\$b) : '' ?>",
            "<?= \$a ? 'x' : \$b ?>",
            "<?= \$a ? \$b : 'x' ?>",
            "<?= \$a ? 'x' : (\$b ? 'y' : 'z') ?>",
            "<?= \$a ? 'x' . \$b : '' ?>",
            "<?= \$a ?? \$b ?>",
        ]));

        self::assertSame([
            "templates/x.php: \$a ? 'x' : \$b",
            "templates/x.php: \$a ? \$b : 'x'",
            "templates/x.php: \$a ? 'x' : (\$b ? 'y' : 'z')",
            "templates/x.php: \$a ? 'x' . \$b : ''",
            'templates/x.php: $a ?? $b',
        ], $bad);
    }

    public function testOnlyTheLayoutsMayPrintRawContent(): void
    {
        self::assertSame([], $this->unsafeEchoes('templates/layout.php', '<?= $content ?>'));
        self::assertSame([], $this->unsafeEchoes('templates/admin/layout.php', '<?= $content ?>'));
        self::assertCount(1, $this->unsafeEchoes('templates/public/home.php', '<?= $content ?>'));
        self::assertCount(1, $this->unsafeEchoes('templates/admin/layout.php', '<?= json_encode($data, $jsonFlags) ?>'));
    }

    // === Functions that run commands or code ===================================

    private const DANGEROUS = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec', 'unserialize', 'create_function', 'assert'];

    /**
     * Calls of dangerous functions (not methods such as $pdo->exec()), eval and the backtick operator.
     *
     * @return list<string> "name (line N)"
     */
    private function dangerousCalls(string $source): array
    {
        $tokens = token_get_all($source);
        $found = [];
        $count = count($tokens);
        $insideBackticks = false;
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '`') {
                if (!$insideBackticks) {
                    $found[] = 'backtick operator';
                }
                $insideBackticks = !$insideBackticks;
                continue;
            }
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_EVAL) {
                $found[] = 'eval (line ' . $token[2] . ')';
                continue;
            }
            if ($token[0] !== T_STRING || !in_array(strtolower($token[1]), self::DANGEROUS, true)) {
                continue;
            }
            $previous = $this->neighbour($tokens, $i, -1);
            $next = $this->neighbour($tokens, $i, 1);
            $isMethodOrDeclaration = is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true);
            if ($next === '(' && !$isMethodOrDeclaration) {
                $found[] = $token[1] . ' (line ' . $token[2] . ')';
            }
        }
        return $found;
    }

    /** @param list<array{int, string, int}|string> $tokens */
    private function neighbour(array $tokens, int $index, int $step): mixed
    {
        for ($i = $index + $step; isset($tokens[$i]); $i += $step) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $tokens[$i];
        }
        return null;
    }

    public function testTheApplicationNeverRunsCommandsOrEvaluatesCode(): void
    {
        $files = array_merge(
            glob(self::root() . '/public/index.php') ?: [],
            iterator_to_array(new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/app', \FilesystemIterator::SKIP_DOTS)), '/\.php$/'), false),
        );
        self::assertGreaterThan(80, count($files));

        $found = [];
        foreach ($files as $file) {
            $path = $file instanceof \SplFileInfo ? $file->getPathname() : $file;
            foreach ($this->dangerousCalls((string) file_get_contents($path)) as $call) {
                $found[] = substr($path, strlen(self::root()) + 1) . ': ' . $call;
            }
        }

        self::assertSame([], $found, 'app/ and public/ must not run commands, evaluate code or unserialize data');
    }

    public function testTheCodeGuardianCatchesEachKindOfViolationAndIgnoresMethodsAndText(): void
    {
        $violations = $this->dangerousCalls(<<<'PHP'
<?php
exec('ls');
shell_exec('ls');
system('ls');
passthru('ls');
proc_open('ls', [], $p);
popen('ls', 'r');
eval('1;');
unserialize($_POST['x']);
$out = `ls`;
PHP);
        self::assertCount(9, $violations, implode(', ', $violations));

        $fine = $this->dangerousCalls(<<<'PHP'
<?php
// exec('ls') in a comment, and "system(" in a string
$text = 'passthru(x) and eval(y)';
$pdo->exec('SET x');
$this->db->exec('SET y');
Foo::system();
function exec_something() {}
class A { public function exec() {} }
PHP);
        self::assertSame([], $fine);
    }

    // === No third-party address in the pages ====================================

    /** Addresses that are identifiers, not resources: XML/JSON-LD namespaces. */
    private const ALLOWED_ADDRESSES = ['http://www.w3.org/2000/svg', 'https://schema.org'];

    /** @return list<string> */
    private function externalAddresses(string $source): array
    {
        preg_match_all('#(?:https?:)?//[A-Za-z0-9][A-Za-z0-9.-]*\.[A-Za-z]{2,}[^\s"\'<>)]*#', $source, $m);
        return array_values(array_filter($m[0], static function (string $url): bool {
            foreach (self::ALLOWED_ADDRESSES as $allowed) {
                if (str_starts_with($url, $allowed)) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function testTemplatesAndPublicFilesLoadNothingFromOtherSites(): void
    {
        $files = $this->templates();
        $public = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . '/public', \FilesystemIterator::SKIP_DOTS));
        $count = count($files);
        foreach ($public as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'css', 'js', 'svg', 'html', 'htm', 'json', 'webmanifest'], true)) {
                $files[] = 'public/' . str_replace('\\', '/', substr($file->getPathname(), strlen(self::root() . '/public') + 1));
            }
        }
        self::assertGreaterThan($count, count($files), 'the public files are scanned too');

        $found = [];
        foreach ($files as $file) {
            foreach ($this->externalAddresses((string) file_get_contents(self::root() . '/' . $file)) as $url) {
                $found[] = $file . ': ' . $url;
            }
        }

        self::assertSame([], $found, 'no font, script, image, stylesheet or frame may come from another site (privacy, speed, CSP)');
    }

    public function testTheAddressGuardianCatchesExternalResourcesAndAcceptsNamespaces(): void
    {
        self::assertSame(
            ['https://cdn.example.com/x.js', '//fonts.example.org/a.css', 'http://tracker.example.net/p.gif'],
            $this->externalAddresses('<script src="https://cdn.example.com/x.js"></script><link href="//fonts.example.org/a.css"><img src=\'http://tracker.example.net/p.gif\'>'),
        );
        self::assertSame([], $this->externalAddresses('<svg xmlns="http://www.w3.org/2000/svg"></svg> {"@context": "https://schema.org"}'));
    }

    // === Folders that must never be served ======================================

    public function testEveryPrivateFolderHasADenialFileForApache22And24(): void
    {
        // Partial protection: it helps only where the server reads .htaccess. The real one is public/ as document root.
        foreach (['storage', 'app', 'migrations', 'bin', 'templates', 'content', 'docs', 'prompts', 'tests', 'docker'] as $folder) {
            $file = self::root() . "/$folder/.htaccess";
            self::assertFileExists($file, "$folder/ needs a denial .htaccess");
            $content = (string) file_get_contents($file);
            self::assertStringContainsString('Require all denied', $content, $folder);
            self::assertStringContainsString('Deny from all', $content, "$folder: fallback for servers without mod_authz_core");
        }
        self::assertStringNotContainsString('denied', (string) file_get_contents(self::root() . '/public/.htaccess'), 'public/ is the folder that IS served');
    }

    public function testNoNodeOrFrontendBuildIsNeededToRunOrBuildTheSite(): void
    {
        foreach (['package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', 'vite.config.js', 'webpack.config.js', 'node_modules'] as $name) {
            self::assertFileDoesNotExist(self::root() . '/' . $name, "$name: the site needs no Node.js");
        }
    }
}
