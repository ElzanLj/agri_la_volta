<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\CanonicalHost;
use PHPUnit\Framework\TestCase;

final class CanonicalHostTest extends TestCase
{
    private const APP_URL = 'https://www.agriturismolavolta.com';

    public function testAnotherHostIsSentToTheHostOfAppUrlKeepingPathAndQuery(): void
    {
        self::assertSame(
            'https://www.agriturismolavolta.com/appartamenti/rosa?x=1',
            CanonicalHost::redirectTarget(self::APP_URL, 'agriturismolavolta.com', 'GET', '/appartamenti/rosa?x=1'),
        );
        self::assertSame(
            'https://www.agriturismolavolta.com/',
            CanonicalHost::redirectTarget(self::APP_URL, 'altro.example.org', 'HEAD', '/'),
        );
    }

    public function testTheCanonicalHostIsLeftAlone(): void
    {
        self::assertNull(CanonicalHost::redirectTarget(self::APP_URL, 'www.agriturismolavolta.com', 'GET', '/'));
        self::assertNull(CanonicalHost::redirectTarget(self::APP_URL, 'WWW.AgriturismoLaVolta.com', 'GET', '/'), 'host names are not case sensitive');
        self::assertNull(CanonicalHost::redirectTarget(self::APP_URL, 'www.agriturismolavolta.com:443', 'GET', '/'), 'the default port is the same address');
    }

    public function testNothingHappensWithoutAnAppUrlOrAHost(): void
    {
        self::assertNull(CanonicalHost::redirectTarget('', 'qualsiasi.example', 'GET', '/'));
        self::assertNull(CanonicalHost::redirectTarget('non un url', 'qualsiasi.example', 'GET', '/'));
        self::assertNull(CanonicalHost::redirectTarget('ftp://sito.example', 'qualsiasi.example', 'GET', '/'));
        self::assertNull(CanonicalHost::redirectTarget(self::APP_URL, '', 'GET', '/'));
    }

    public function testOnlyGetAndHeadAreRedirected(): void
    {
        foreach (['POST', 'PUT', 'DELETE', 'OPTIONS'] as $method) {
            self::assertNull(CanonicalHost::redirectTarget(self::APP_URL, 'agriturismolavolta.com', $method, '/admin/login'), $method);
        }
    }

    public function testNothingHappensBehindAProxyThatRewritesTheHost(): void
    {
        self::assertNull(CanonicalHost::redirectTarget(self::APP_URL, 'interno.local', 'GET', '/', forwardedHost: true));
    }

    public function testAPortOrSubdirectoryInAppUrlIsHonoured(): void
    {
        self::assertSame(
            'http://localhost:8080/pagina',
            CanonicalHost::redirectTarget('http://localhost:8080', '127.0.0.1:8080', 'GET', '/pagina'),
        );
        self::assertNull(CanonicalHost::redirectTarget('http://localhost:8080/sito', 'localhost:8080', 'GET', '/sito/pagina'));
    }

    public function testTheTargetNeverComesFromTheHostHeaderOrAHostileUri(): void
    {
        foreach (['evil.example', 'evil.example/', 'evil.example@www.agriturismolavolta.com', "evil.example\r\nSet-Cookie: x=1"] as $host) {
            $target = CanonicalHost::redirectTarget(self::APP_URL, $host, 'GET', '/x');
            self::assertSame('https://www.agriturismolavolta.com/x', $target, 'host: ' . json_encode($host));
        }
        // A request target that would change the host of the redirect is replaced by "/".
        self::assertSame('https://www.agriturismolavolta.com/', CanonicalHost::redirectTarget(self::APP_URL, 'altro.example', 'GET', '//evil.example/x'));
        self::assertSame('https://www.agriturismolavolta.com/', CanonicalHost::redirectTarget(self::APP_URL, 'altro.example', 'GET', "/x\r\nSet-Cookie: a=b"));
        self::assertSame('https://www.agriturismolavolta.com/', CanonicalHost::redirectTarget(self::APP_URL, 'altro.example', 'GET', '@evil.example'));
    }
}
