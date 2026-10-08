<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    private const USER = 'gestore-lavolta';

    /** @return array<string, array{string}> */
    public static function acceptable(): array
    {
        return [
            'long random' => ['k7#Qv9!xTz2$mWp4Lr8'],
            'a phrase' => ['la nonna beve il caffè sul balcone ogni mattina'],
            'a phrase with a common word inside' => ['la mia famiglia ama passeggiare nel parco al tramonto'],
            'exactly twelve' => ['Tz9#kLm2$vQx'],
            'unicode' => ['Zoë-Müller-ha-un-gatto-blu'],
            'test password of the suite' => ['correct horse battery staple 42'],
        ];
    }

    #[DataProvider('acceptable')]
    public function testAGoodPasswordIsAccepted(string $password): void
    {
        self::assertSame([], PasswordPolicy::problems($password, self::USER), $password);
    }

    public function testTooShortIsRefusedWithTheLimitInTheMessage(): void
    {
        $problems = PasswordPolicy::problems('Corta1!', self::USER);

        self::assertCount(1, $problems);
        self::assertStringContainsString('almeno 12 caratteri', $problems[0]);
        self::assertSame(['La password deve avere almeno 12 caratteri.'], PasswordPolicy::problems('', self::USER));
        self::assertNotSame([], PasswordPolicy::problems('abcdefghijk', self::USER), 'eleven characters');
    }

    public function testTooLongIsRefused(): void
    {
        self::assertSame(['La password può avere al massimo 1024 caratteri.'], PasswordPolicy::problems(str_repeat('aB3$kLm9', 200), self::USER), '1600 characters');
        self::assertSame([], PasswordPolicy::problems(str_repeat('aB3$kLm9xQ', 100), self::USER), 'exactly 1000 characters is fine');
    }

    /** @return array<string, array{string}> */
    public static function common(): array
    {
        return [
            'the example of the prompt' => ['Agriturismo2026!'],
            'classic' => ['Password123456'],
            'classic with capitals and a year' => ['PASSWORD2026!!!'],
            'look-alikes' => ['P@ssw0rd-P@ssw0rd'],
            'keyboard' => ['qwertyuiop1234'],
            'italian word and digits' => ['Ciaociao123456'],
            'welcome' => ['Welcome2026!!!!'],
            'month and year' => ['Settembre2026!!'],
            'the place' => ['Salsomaggiore-2026'],
        ];
    }

    #[DataProvider('common')]
    public function testCommonPasswordsAreRefusedEvenWithCapitalsDigitsAndSymbolsAdded(string $password): void
    {
        $problems = PasswordPolicy::problems($password, self::USER);

        self::assertNotSame([], $problems, $password);
    }

    public function testTheMessageForACommonPasswordExplainsWhy(): void
    {
        $problems = PasswordPolicy::problems('Password123456', self::USER);

        self::assertCount(1, $problems);
        self::assertStringContainsString('troppo comune o prevedibile', $problems[0]);
    }

    /** @return array<string, array{string}> */
    public static function withTheUserName(): array
    {
        return [
            'as it is' => ['gestore-lavolta-2026'],
            'upper case' => ['GESTORE-LAVOLTA-xyz-98'],
            'without the dash' => ['il-gestorelavolta-di-casa-mia'],
            'spaced' => ['Ho scelto Gestore Lavolta per sempre'],
        ];
    }

    #[DataProvider('withTheUserName')]
    public function testThePasswordCannotContainTheUserName(string $password): void
    {
        $problems = PasswordPolicy::problems($password, self::USER);

        self::assertContains('La password non può contenere il nome utente.', $problems, $password);
    }

    /** @return array<string, array{string}> */
    public static function withTheFarmName(): array
    {
        return [
            'agriturismo' => ['x9Q-Agriturismo-k3p-7Lm'],
            'la volta' => ['mi piace La Volta ogni giorno 77'],
            'lavolta' => ['LAVOLTA-k8#zq-92'],
            'look-alike' => ['Agr1tur1sm0-Qk7z!x2'],
        ];
    }

    #[DataProvider('withTheFarmName')]
    public function testThePasswordCannotContainTheNameOfTheFarm(string $password): void
    {
        self::assertContains('La password non può contenere il nome dell\'agriturismo.', PasswordPolicy::problems($password, self::USER), $password);
    }

    public function testAShortUserNameIsNotUsedAsAGlobalBan(): void
    {
        // Two characters would reject too many good passwords by chance: only names of 3+ characters count.
        self::assertSame([], PasswordPolicy::problems('k7#Qv9!xTz2$mWp4Lr8', 'ab'));
        self::assertNotSame([], PasswordPolicy::problems('xx-abc-k7#Qv9!xTz2', 'abc'));
    }

    public function testRepetitivePasswordsAreRefused(): void
    {
        self::assertNotSame([], PasswordPolicy::problems('aaaaaaaaaaaaaaaa', self::USER));
        self::assertNotSame([], PasswordPolicy::problems('abababababababab', self::USER));
        self::assertNotSame([], PasswordPolicy::problems('123456789012345', self::USER), 'digits only');
        self::assertNotSame([], PasswordPolicy::problems('1234 5678 9012', self::USER), 'digits and spaces only');
    }

    public function testTheSameAsTheCurrentPasswordIsRefused(): void
    {
        $current = 'k7#Qv9!xTz2$mWp4Lr8';
        $hash = password_hash($current, PASSWORD_DEFAULT);

        self::assertContains('La nuova password è uguale a quella attuale.', PasswordPolicy::problems($current, self::USER, $hash));
        self::assertSame([], PasswordPolicy::problems('un-altra-frase-lunga-e-casuale-77', self::USER, $hash));
        self::assertSame([], PasswordPolicy::problems($current, self::USER, null), 'without a current hash there is nothing to compare');
    }

    public function testSeveralProblemsAreAllReported(): void
    {
        $problems = PasswordPolicy::problems('Agriturismo-gestore-lavolta-1111', self::USER);

        self::assertGreaterThanOrEqual(2, count($problems));
    }

    public function testTheListOfCommonPasswordsIsAPlainFileInTheRepository(): void
    {
        $file = dirname(__DIR__, 2) . '/app/Security/common-passwords.txt';

        self::assertFileExists($file);
        $words = array_filter(array_map('trim', file($file) ?: []), static fn (string $l): bool => $l !== '' && $l[0] !== '#');
        self::assertGreaterThan(80, count($words));
        foreach ($words as $word) {
            self::assertMatchesRegularExpression('/^[a-z]+$/', $word, 'letters only, lower case: ' . $word);
        }
        self::assertSame(count($words), count(array_unique($words)), 'no duplicates');
    }
}
