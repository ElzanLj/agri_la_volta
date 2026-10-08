<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Security\AdminAuth;
use App\Security\RateLimiter;
use App\Security\ReauthGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseTestCase;

/**
 * "Enter your password again": a confirmation lasts five minutes, only in the session that made it, and
 * wrong passwords are counted. The session is a plain array here (the clock is injected), the HTTP behaviour
 * is in Http/AccountTest.
 */
final class ReauthGuardTest extends DatabaseTestCase
{
    private const PASSWORD = 'k7#Qv9!xTz2$mWp4Lr8';
    private const CLIENT = '203.0.113.50';

    private int $now = 1_800_000_000;
    private ReauthGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->exec('DELETE FROM admin');
        $this->db->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')->execute(['gestore-test', password_hash(self::PASSWORD, PASSWORD_DEFAULT)]);
        $_SESSION = ['admin' => ['id' => (int) $this->db->lastInsertId()]];
        $this->guard = new ReauthGuard(new AdminAuth($this->db), new RateLimiter($this->db), fn (): int => $this->now);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->db->exec('DELETE FROM admin');
    }

    public function testNothingIsConfirmedAtTheStart(): void
    {
        self::assertFalse($this->guard->isFresh());
    }

    public function testTheRightPasswordConfirmsForFiveMinutesAndNotLonger(): void
    {
        self::assertSame(ReauthGuard::OK, $this->guard->confirm(self::PASSWORD, self::CLIENT));

        self::assertTrue($this->guard->isFresh());
        $this->now += 299;
        self::assertTrue($this->guard->isFresh(), 'after 4 minutes 59 seconds');
        $this->now += 1;
        self::assertTrue($this->guard->isFresh(), 'exactly five minutes');
        $this->now += 1;
        self::assertFalse($this->guard->isFresh(), 'after five minutes and one second');
    }

    public function testAWrongPasswordConfirmsNothingAndIsCounted(): void
    {
        self::assertSame(ReauthGuard::WRONG, $this->guard->confirm('password-sbagliata-1234', self::CLIENT));

        self::assertFalse($this->guard->isFresh());
        $limiter = new RateLimiter($this->db);
        self::assertSame(1, $limiter->countRecent(ReauthGuard::BUCKET, 3600), 'the attempt counts towards the limit');
        self::assertSame(1, $limiter->countRecent(AdminAuth::FAILED_BUCKET, 3600), 'and appears among the failed accesses');
    }

    public function testAWrongPasswordEndsAConfirmationThatWasStillValid(): void
    {
        $this->guard->confirm(self::PASSWORD, self::CLIENT);
        self::assertTrue($this->guard->isFresh());

        $this->guard->confirm('password-sbagliata-1234', self::CLIENT);

        self::assertFalse($this->guard->isFresh());
    }

    public function testAfterFiveWrongPasswordsEvenTheRightOneIsRefused(): void
    {
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(ReauthGuard::WRONG, $this->guard->confirm("sbagliata-$i-xxxxxxx", self::CLIENT));
        }

        self::assertSame(ReauthGuard::LIMITED, $this->guard->confirm(self::PASSWORD, self::CLIENT));
        self::assertFalse($this->guard->isFresh());
        self::assertSame(ReauthGuard::OK, $this->guard->confirm(self::PASSWORD, '203.0.113.99'), 'another client is not affected');
    }

    public function testASuccessForgetsThePreviousMistakes(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->guard->confirm("sbagliata-$i-xxxxxxx", self::CLIENT);
        }
        self::assertSame(ReauthGuard::OK, $this->guard->confirm(self::PASSWORD, self::CLIENT));

        for ($i = 0; $i < 4; $i++) {
            self::assertSame(ReauthGuard::WRONG, $this->guard->confirm("ancora-$i-xxxxxxx", self::CLIENT), 'a new series of four is still allowed');
        }
    }

    public function testTheConfirmationIsLostWhenItIsClearedOrTheAdminIsNotInTheSession(): void
    {
        $this->guard->confirm(self::PASSWORD, self::CLIENT);
        $this->guard->clear();
        self::assertFalse($this->guard->isFresh());

        $this->guard->confirm(self::PASSWORD, self::CLIENT);
        self::assertTrue($this->guard->isFresh());
        unset($_SESSION['admin']);
        self::assertFalse($this->guard->isFresh(), 'a confirmation does not count without a logged-in admin');
    }

    public function testAClockThatWentBackwardsNeverMakesAConfirmationFresh(): void
    {
        $this->guard->confirm(self::PASSWORD, self::CLIENT);
        $this->now -= 3600;

        self::assertFalse($this->guard->isFresh());
    }

    public function testNoAdminInTheSessionMeansNoPasswordCheckAtAll(): void
    {
        unset($_SESSION['admin']);

        self::assertSame(ReauthGuard::WRONG, $this->guard->confirm(self::PASSWORD, self::CLIENT));
    }

    public function testAnEmptyOrHugePasswordIsRefusedWithoutCostingAHash(): void
    {
        self::assertSame(ReauthGuard::WRONG, $this->guard->confirm('', self::CLIENT));
        self::assertSame(ReauthGuard::WRONG, $this->guard->confirm(str_repeat('a', 5000), self::CLIENT));
    }

    /** @return array<string, array{string, string}> */
    public static function targets(): array
    {
        return [
            'the admin home' => ['/admin', '/admin'],
            'a page' => ['/admin/impostazioni', '/admin/impostazioni'],
            'a nested page' => ['/admin/pagine/privacy', '/admin/pagine/privacy'],
            'trailing slash' => ['/admin/account/', '/admin/account/'],
            'another site' => ['https://evil.example/admin', '/admin'],
            'protocol relative' => ['//evil.example', '/admin'],
            'protocol relative after admin' => ['/admin//evil.example', '/admin'],
            'parent directory' => ['/admin/../../etc/passwd', '/admin'],
            'a path that only starts with admin' => ['/administrator', '/admin'],
            'with a query' => ['/admin/x?y=1', '/admin'],
            'with a fragment' => ['/admin/x#y', '/admin'],
            'javascript' => ['javascript:alert(1)', '/admin'],
            'a line break' => ["/admin/x\r\nSet-Cookie: a=b", '/admin'],
            'a backslash' => ['/admin\\evil', '/admin'],
            'empty' => ['', '/admin'],
            'the public home' => ['/', '/admin'],
        ];
    }

    #[DataProvider('targets')]
    public function testOnlyPlainAdminPathsAreAcceptedAsAReturnAddress(string $given, string $expected): void
    {
        self::assertSame($expected, ReauthGuard::safeTarget($given), json_encode($given));
    }
}
