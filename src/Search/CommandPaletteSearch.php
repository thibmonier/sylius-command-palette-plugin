<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Runs the query through every provider and returns the non-empty groups. */
final class CommandPaletteSearch
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    /** @param positive-int $limitPerGroup */
    public function __construct(
        private readonly SearchProviderRegistry $registry,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly int $limitPerGroup,
    ) {
    }

    /** Collapses whitespace, trims and cuts the query to MAX_LENGTH characters. */
    public static function normalize(string $query): string
    {
        // preg_replace() returns null on invalid UTF-8: such a query becomes empty.
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));

        return trim(mb_substr($query, 0, self::MAX_LENGTH));
    }

    /** @return list<array{key: string, label: string, results: list<SearchResult>}> */
    public function search(string $query): array
    {
        $query = self::normalize($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [];
        foreach ($this->registry->all() as $provider) {
            try {
                $results = $provider->search($query, $this->limitPerGroup);
            } catch (\Throwable $exception) {
                // A broken provider (e.g. a third-party one) must not break the whole palette.
                $this->logger->error('Command palette provider "{provider}" failed: {message}', [
                    'provider' => $provider->getKey(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);

                continue;
            }

            if ([] === $results) {
                continue;
            }

            $groups[] = [
                'key' => $provider->getKey(),
                'label' => $this->getLabel($provider->getKey()),
                'results' => array_slice($results, 0, $this->limitPerGroup),
            ];
        }

        return $groups;
    }

    private function getLabel(string $key): string
    {
        $id = 'tmonier_command_palette.group.' . $key;
        $label = $this->translator->trans($id);

        return $label !== $id ? $label : ucfirst(str_replace('_', ' ', $key));
    }
}
