<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Money;
use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListFiltersTest extends TestCase
{
    /** @param array<string, mixed> $query */
    private function filters(array $query, bool $withOrigin = false, array $entities = []): ListFilters
    {
        return ListFilters::fromRequest(new Request('GET', '/admin/richieste', [], [], $query), Labels::REQUEST_STATUSES, $withOrigin, $entities);
    }

    public function testNoFiltersMeansEverythingOnPageOne(): void
    {
        $f = $this->filters([]);

        self::assertTrue($f->isValid());
        self::assertSame([null, null, null, null, null, 1, 0], [$f->status, $f->apartmentId, $f->origin, $f->from, $f->to, $f->page, $f->offset()]);
        self::assertSame([], $f->queryParams());
    }

    public function testValidFilters(): void
    {
        $f = $this->filters(['stato' => 'pending', 'appartamento' => '3', 'dal' => '2027-06-01', 'al' => '2027-07-01', 'pagina' => '3']);

        self::assertTrue($f->isValid());
        self::assertSame(['pending', 3, '2027-06-01', '2027-07-01', 3, 100], [$f->status, $f->apartmentId, $f->from, $f->to, $f->page, $f->offset()]);
        self::assertSame(['stato' => 'pending', 'appartamento' => '3', 'dal' => '2027-06-01', 'al' => '2027-07-01'], $f->queryParams(), 'links keep the filters but not the page');
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalid(): array
    {
        return [
            'unknown status' => [['stato' => 'archived'], 'stato'],
            'status injection' => [['stato' => "pending' OR 1=1"], 'stato'],
            'apartment text' => [['appartamento' => 'abc'], 'appartamento'],
            'apartment injection' => [['appartamento' => '1 OR 1=1'], 'appartamento'],
            'apartment negative' => [['appartamento' => '-1'], 'appartamento'],
            'apartment too long' => [['appartamento' => '12345678901'], 'appartamento'],
            'bad date' => [['dal' => '2027-02-30'], 'dal'],
            'date with time' => [['al' => '2027-06-01 12:00'], 'al'],
            'reversed period' => [['dal' => '2027-07-10', 'al' => '2027-07-01'], 'al'],
            'equal period' => [['dal' => '2027-07-10', 'al' => '2027-07-10'], 'al'],
            'page zero' => [['pagina' => '0'], 'pagina'],
            'page negative' => [['pagina' => '-2'], 'pagina'],
            'page text' => [['pagina' => '2;DROP'], 'pagina'],
            'array status' => [['stato' => ['pending']], 'stato'],
            'array page' => [['pagina' => ['1']], 'pagina'],
            'origin not allowed here' => [['origine' => 'phone'], 'origine'],
            'entity not allowed here' => [['tipo' => 'booking'], 'tipo'],
        ];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalid')]
    public function testInvalidValuesAreReportedAndNeverUsed(array $query, string $field): void
    {
        $f = $this->filters($query);

        self::assertFalse($f->isValid());
        self::assertArrayHasKey($field, $f->errors);
    }

    public function testOriginAndEntityAreAcceptedWhenTheListSupportsThem(): void
    {
        $withOrigin = ListFilters::fromRequest(new Request('GET', '/', [], [], ['origine' => 'agency']), [], true);
        self::assertTrue($withOrigin->isValid());
        self::assertSame('agency', $withOrigin->origin);

        $audit = ListFilters::fromRequest(new Request('GET', '/', [], [], ['tipo' => 'booking']), [], false, Labels::ENTITIES);
        self::assertTrue($audit->isValid());
        self::assertSame('booking', $audit->entity);
        self::assertSame(['tipo' => 'booking'], $audit->queryParams());
    }

    public function testRejectedValuesAreDroppedFromTheFilterSet(): void
    {
        $f = $this->filters(['stato' => 'nope', 'appartamento' => 'x', 'dal' => 'y']);

        self::assertNull($f->status);
        self::assertNull($f->apartmentId);
        self::assertNull($f->from);
        self::assertSame([], $f->queryParams());
    }

    // --- amounts typed by the admin -------------------------------------------

    /** @return array<string, array{string, ?int}> */
    public static function amounts(): array
    {
        return [
            'whole euros' => ['80', 8000],
            'comma decimals' => ['80,50', 8050],
            'dot decimals' => ['80.5', 8050],
            'one decimal' => ['80,5', 8050],
            'zero' => ['0', 0],
            'cents only' => ['0,05', 5],
            'spaces around' => ['  12,00 ', 1200],
            'big but valid' => ['9999999,99', 999999999],
            'three decimals' => ['1,234', null],
            'thousands separator' => ['1.000,00', null],
            'negative' => ['-5', null],
            'text' => ['abc', null],
            'empty' => ['', null],
            'currency sign' => ['€ 5', null],
            'two separators' => ['1,2,3', null],
            'too many digits' => ['12345678', null],
        ];
    }

    #[DataProvider('amounts')]
    public function testMoneyParse(string $text, ?int $cents): void
    {
        self::assertSame($cents, Money::parse($text));
    }

    public function testMoneyPlainRoundTrips(): void
    {
        foreach ([0, 5, 100, 8050, 123456, 999999999] as $cents) {
            self::assertSame($cents, Money::parse(Money::plain($cents)));
        }
        self::assertSame('80,50', Money::plain(8050));
        self::assertSame('0,05', Money::plain(5));
    }
}
