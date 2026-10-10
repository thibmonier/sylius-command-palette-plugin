<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search;

use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

final class StubProvider implements SearchProviderInterface
{
    /** @var list<array{string, int}> */
    public array $calls = [];

    /** @param list<SearchResult> $results */
    public function __construct(
        private readonly string $key,
        private readonly array $results = [],
        private readonly ?\Throwable $exception = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function search(string $query, int $limit): array
    {
        $this->calls[] = [$query, $limit];
        if (null !== $this->exception) {
            throw $this->exception;
        }

        return $this->results;
    }
}
