<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search\Provider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\OrderNumber;

final class OrderNumberTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function queries(): iterable
    {
        yield 'short number' => ['22', ['22', '000000022']];
        yield 'with hash' => ['#22', ['22', '000000022']];
        yield 'padded number' => ['000000022', ['000000022', '22']];
        yield 'partially padded' => ['#0022', ['0022', '22', '000000022']];
        yield 'custom format' => ['ORD-2026-1', ['ORD-2026-1']];
        yield 'only zeros' => ['000', ['000']];
        yield 'empty' => ['#', []];
        yield 'with spaces' => ['12 34', []];
        yield 'without digits' => ['john', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('queries')]
    public function testCandidates(string $query, array $expected): void
    {
        self::assertSame($expected, OrderNumber::candidates($query));
    }

    public function testThePaddingCanBeChanged(): void
    {
        self::assertSame(['7', '00007'], OrderNumber::candidates('7', 5));
    }
}
