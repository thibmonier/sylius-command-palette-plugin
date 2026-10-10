<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCommandPalettePlugin\Search\LikePattern;

final class LikePatternTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function escapedValues(): iterable
    {
        yield 'plain text' => ['john', 'john'];
        yield 'percent' => ['100%', '100!%'];
        yield 'underscore' => ['t_shirt', 't!_shirt'];
        yield 'escape character' => ['wow!', 'wow!!'];
        yield 'escape character before a wildcard' => ['!%', '!!!%'];
        yield 'backslash is not special' => ['a\\b', 'a\\b'];
        yield 'quotes are left to parameter binding' => ["o'hara", "o'hara"];
    }

    #[DataProvider('escapedValues')]
    public function testItEscapesTheWildcardsOfUserInput(string $value, string $expected): void
    {
        self::assertSame($expected, LikePattern::escape($value));
    }

    public function testPrefixPatternsAreLowerCased(): void
    {
        self::assertSame('jöhn!_d%', LikePattern::prefix('JÖHN_D'));
    }

    public function testContainsPatterns(): void
    {
        self::assertSame('%50!% off%', LikePattern::contains('50% OFF'));
    }

    public function testTheDqlEscapeClauseUsesTheEscapeCharacter(): void
    {
        self::assertSame(sprintf("ESCAPE '%s'", LikePattern::ESCAPE_CHARACTER), LikePattern::DQL_ESCAPE);
    }
}
