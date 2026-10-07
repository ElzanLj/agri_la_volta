<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Work to do after the HTTP response has been sent (SPEC: do not make the visitor wait for SMTP).
 * Where the server supports it (PHP-FPM: fastcgi_finish_request) the response is closed first;
 * elsewhere callers run the work inline, with the SMTP timeout as the only bound.
 */
final class DeferredWork
{
    /** @var list<callable(): void> */
    private static array $jobs = [];

    /**
     * The function that closes the connection to the client, when the server has one: PHP-FPM
     * (fastcgi_finish_request) or LiteSpeed (litespeed_finish_request).
     *
     * @param (callable(string): bool)|null $exists injectable for tests (defaults to function_exists)
     */
    public static function finishFunction(?callable $exists = null): ?string
    {
        $exists ??= 'function_exists';
        foreach (['fastcgi_finish_request', 'litespeed_finish_request'] as $name) {
            if ($exists($name)) {
                return $name;
            }
        }
        return null;
    }

    public static function available(): bool
    {
        return self::finishFunction() !== null;
    }

    /** @param callable(): void $job */
    public static function defer(callable $job): void
    {
        self::$jobs[] = $job;
    }

    /**
     * Runs the queued jobs. Call once, right after the response was sent. A failing job never
     * affects the others or the already-delivered response.
     *
     * @param (callable(): mixed)|null $finishRequest closes the connection to the client (injectable for tests)
     */
    public static function flush(?callable $finishRequest = null): void
    {
        if (self::$jobs === []) {
            return;
        }
        $jobs = self::$jobs;
        self::$jobs = [];

        // A visitor who closes the page must not stop the jobs halfway (an e-mail left in "sending").
        ignore_user_abort(true);

        $finishRequest ??= self::finishFunction();
        if ($finishRequest !== null) {
            try {
                $finishRequest();
            } catch (Throwable) {
                // Carry on: the jobs still run, only a little later for the client.
            }
        }
        foreach ($jobs as $job) {
            try {
                $job();
            } catch (Throwable $e) {
                error_log('Deferred job failed: ' . $e::class);
            }
        }
    }

    public static function reset(): void
    {
        self::$jobs = [];
    }
}
