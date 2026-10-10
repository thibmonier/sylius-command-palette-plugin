<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

/**
 * Typed access to the scalar rows returned by the providers' array-hydrated queries.
 *
 * @internal
 */
final class Row
{
    /** @param array<mixed> $row */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new \UnexpectedValueException(sprintf('Column "%s" is not an integer.', $key));
    }

    /** @param array<mixed> $row */
    public static function string(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if (null === $value || is_bool($value)) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" is not a string.', $key));
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /** @param array<mixed> $row */
    public static function bool(array $row, string $key): bool
    {
        return (bool) ($row[$key] ?? false);
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<array<mixed>>
     */
    public static function list(array $rows): array
    {
        return array_values(array_filter($rows, is_array(...)));
    }
}
