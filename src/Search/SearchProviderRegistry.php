<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search;

/** The enabled providers, in the order of their tag priority (highest first). */
final class SearchProviderRegistry
{
    /** @var array<string, SearchProviderInterface> */
    private array $providers = [];

    /** @param iterable<SearchProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $key = $provider->getKey();
            if (1 !== preg_match('/^[a-z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException(sprintf('The key "%s" of the command palette provider "%s" must only contain lowercase letters, digits and underscores.', $key, $provider::class));
            }
            if (isset($this->providers[$key])) {
                throw new \InvalidArgumentException(sprintf('Two command palette providers use the key "%s": "%s" and "%s".', $key, $this->providers[$key]::class, $provider::class));
            }

            $this->providers[$key] = $provider;
        }
    }

    /** @return list<SearchProviderInterface> */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }
}
