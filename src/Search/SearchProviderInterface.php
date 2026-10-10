<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search;

/**
 * A source of results of the command palette, registered with the
 * "tmonier_command_palette.provider" tag (higher priority = group shown first).
 *
 * The group label is the translation of "tmonier_command_palette.group.<key>".
 */
interface SearchProviderInterface
{
    /** Unique key of the group, e.g. "orders" (letters, digits and underscores). */
    public function getKey(): string;

    /**
     * Called with a trimmed query of at least 2 characters.
     *
     * @param positive-int $limit
     *
     * @return list<SearchResult>
     */
    public function search(string $query, int $limit): array;
}
