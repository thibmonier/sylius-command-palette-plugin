<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\LikePattern;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/** Cart promotions whose name contains the query or whose code starts with it. */
final class PromotionSearchProvider implements SearchProviderInterface
{
    /** @param class-string $promotionClass */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $promotionClass,
    ) {
    }

    public function getKey(): string
    {
        return 'promotions';
    }

    public function search(string $query, int $limit): array
    {
        $escape = LikePattern::DQL_ESCAPE;

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id AS id', 'p.code AS code', 'p.name AS name')
            ->from($this->promotionClass, 'p')
            ->andWhere("LOWER(p.name) LIKE :contains $escape OR LOWER(p.code) LIKE :prefix $escape")
            ->setParameter('contains', LikePattern::contains($query))
            ->setParameter('prefix', LikePattern::prefix($query))
            ->orderBy('p.priority', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(fn (array $row): SearchResult => new SearchResult(
            title: Row::string($row, 'name') ?? Row::string($row, 'code') ?? '#' . Row::int($row, 'id'),
            subtitle: Row::string($row, 'code'),
            url: $this->urlGenerator->generate('sylius_admin_promotion_update', ['id' => Row::int($row, 'id')]),
            icon: 'tabler:percentage',
        ), Row::list($rows));
    }
}
