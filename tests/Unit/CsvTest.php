<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public static function cells(): array
    {
        return [
            'plain text' => ['Mario Rossi', 'Mario Rossi'],
            'null' => [null, ''],
            'integer' => [42, '42'],
            'empty string' => ['', ''],
            'delimiter is quoted' => ['a;b', '"a;b"'],
            'quote is doubled' => ['dice "ciao"', '"dice ""ciao"""'],
            'newline is quoted' => ["riga1\nriga2", "\"riga1\nriga2\""],
            'comma needs no quoting (delimiter is ;)' => ['a,b', 'a,b'],
            'date' => ['2027-06-10', '2027-06-10'],
            'equals sign' => ['=1+1', "'=1+1"],
            'plus sign' => ['+39 333 1234567', "'+39 333 1234567"],
            'minus sign' => ['-5', "'-5"],
            'at sign' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab' => ["\t=1", "'\t=1"],
            'carriage return' => ["\r=1", "\"'\r=1\""],
            'formula with quotes and delimiter' => ['=HYPERLINK("x";"y")', '"\'=HYPERLINK(""x"";""y"")"'],
            'sign in the middle is data' => ['a=b+c', 'a=b+c'],
            'zero' => [0, '0'],
        ];
    }

    #[DataProvider('cells')]
    public function testCell(mixed $input, string $expected): void
    {
        self::assertSame($expected, Csv::cell($input));
    }

    public function testRowUsesSemicolonsAndCrlf(): void
    {
        self::assertSame("a;b c;\"d;e\";\r\n", Csv::row(['a', 'b c', 'd;e', null]));
    }

    public function testNoDataCellEverStartsWithAFormulaCharacter(): void
    {
        foreach (['=', '+', '-', '@', "\t", "\r"] as $lead) {
            $cell = Csv::cell($lead . 'payload');
            self::assertStringNotContainsString($lead, $cell[0] === '"' ? substr($cell, 1, 1) : $cell[0], json_encode($lead) . ' must be defused');
        }
    }
}
