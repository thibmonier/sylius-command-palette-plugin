<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

/**
 * Order numbers the query may refer to: "#22", "22" and "000000022" all find the order
 * "000000022" (the default Sylius number is the sequence padded to 9 digits).
 */
final class OrderNumber
{
    public const DEFAULT_PADDING = 9;

    /** @return list<string> */
    public static function candidates(string $query, int $padding = self::DEFAULT_PADDING): array
    {
        $number = ltrim(trim($query), '#');
        // Order numbers contain digits ("000000022", "ORD-2026-22"), never spaces.
        if ('' === $number || 1 === preg_match('/\s/', $number) || 0 === preg_match('/\d/', $number)) {
            return [];
        }

        $candidates = [$number];
        if (ctype_digit($number)) {
            $withoutZeros = ltrim($number, '0');
            if ('' !== $withoutZeros) {
                $candidates[] = $withoutZeros;
                $candidates[] = str_pad($withoutZeros, $padding, '0', \STR_PAD_LEFT);
            }
        }

        return array_values(array_unique($candidates));
    }
}
