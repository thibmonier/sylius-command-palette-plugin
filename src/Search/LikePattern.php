<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search;

/**
 * Builds LIKE patterns from user input. The wildcards typed by the user are escaped with
 * "!" (the same escape character on MySQL, MariaDB and PostgreSQL), so DQL must use
 * "... LIKE :pattern ESCAPE '!'" (see self::DQL_ESCAPE).
 */
final class LikePattern
{
    public const ESCAPE_CHARACTER = '!';

    public const DQL_ESCAPE = "ESCAPE '!'";

    public static function escape(string $value): string
    {
        return strtr($value, [
            self::ESCAPE_CHARACTER => self::ESCAPE_CHARACTER . self::ESCAPE_CHARACTER,
            '%' => self::ESCAPE_CHARACTER . '%',
            '_' => self::ESCAPE_CHARACTER . '_',
        ]);
    }

    /** Lower-cased "starts with" pattern, to compare with LOWER(column). */
    public static function prefix(string $value): string
    {
        return self::escape(mb_strtolower($value)) . '%';
    }

    /** Lower-cased "contains" pattern, to compare with LOWER(column). */
    public static function contains(string $value): string
    {
        return '%' . self::escape(mb_strtolower($value)) . '%';
    }
}
