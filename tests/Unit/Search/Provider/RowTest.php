<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search\Provider;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\Row;

final class RowTest extends TestCase
{
    public function testItReadsTypedColumns(): void
    {
        $row = ['id' => '12', 'name' => '  Cap ', 'empty' => '', 'enabled' => 1, 'total' => 1500];

        self::assertSame(12, Row::int($row, 'id'));
        self::assertSame(1500, Row::int($row, 'total'));
        self::assertSame('Cap', Row::string($row, 'name'));
        self::assertNull(Row::string($row, 'empty'));
        self::assertNull(Row::string($row, 'missing'));
        self::assertTrue(Row::bool($row, 'enabled'));
        self::assertFalse(Row::bool($row, 'missing'));
    }

    public function testItRejectsNonIntegerIdentifiers(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        Row::int(['id' => 'abc'], 'id');
    }

    public function testItKeepsArrayRowsOnly(): void
    {
        self::assertSame([['id' => 1]], Row::list(['a' => ['id' => 1], 'b' => 'scalar']));
    }
}
