<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\GuestCounts;
use App\Domain\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuestCountsTest extends TestCase
{
    public function testValidCounts(): void
    {
        $guests = GuestCounts::from(2, 1, 1);
        self::assertSame(3, $guests->people());
        self::assertSame(1, $guests->pets);
    }

    public function testNumericStringsFromFormsAreAccepted(): void
    {
        $guests = GuestCounts::from('2', '0', '1');
        self::assertSame([2, 0, 1], [$guests->adults, $guests->children, $guests->pets]);
    }

    public function testChildrenAndPetsDefaultToZero(): void
    {
        $guests = GuestCounts::from(1);
        self::assertSame([1, 0, 0], [$guests->adults, $guests->children, $guests->pets]);
    }

    /** @return array<string, array{mixed, mixed, mixed, string}> */
    public static function invalid(): array
    {
        return [
            'no adults' => [0, 0, 0, 'adults'],
            'negative adults' => [-1, 0, 0, 'adults'],
            'adults not numeric' => ['abc', 0, 0, 'adults'],
            'adults missing' => [null, 0, 0, 'adults'],
            'adults decimal' => [2.5, 0, 0, 'adults'],
            'adults decimal string' => ['2.5', 0, 0, 'adults'],
            'too many adults' => [21, 0, 0, 'adults'],
            'negative children' => [2, -1, 0, 'children'],
            'children not numeric' => [2, 'x', 0, 'children'],
            'too many children' => [2, 21, 0, 'children'],
            'negative pets' => [2, 0, -1, 'pets'],
            'too many pets' => [2, 0, 11, 'pets'],
        ];
    }

    #[DataProvider('invalid')]
    public function testInvalidCountsAreRejected(mixed $adults, mixed $children, mixed $pets, string $field): void
    {
        try {
            GuestCounts::from($adults, $children, $pets);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey($field, $e->errors());
        }
    }

    public function testCapacityIsNotCheckedWhenNotConfigured(): void
    {
        self::assertFalse(GuestCounts::from(10, 5)->exceedsCapacity(null));
    }

    public function testCapacityCountsAdultsAndChildrenButNotPets(): void
    {
        $guests = GuestCounts::from(2, 2, 3);
        self::assertFalse($guests->exceedsCapacity(4));
        self::assertTrue($guests->exceedsCapacity(3));
    }
}
