<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\LikePattern;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/** Promotion coupons whose code starts with the query, linked to their promotion's coupons page. */
final class PromotionCouponSearchProvider implements SearchProviderInterface
{
    /** @param class-string $promotionCouponClass */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $promotionCouponClass,
    ) {
    }

    public function getKey(): string
    {
        return 'promotion_coupons';
    }

    public function search(string $query, int $limit): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('c.id AS id', 'c.code AS code', 'p.id AS promotionId', 'p.name AS promotionName')
            ->from($this->promotionCouponClass, 'c')
            ->innerJoin('c.promotion', 'p')
            ->andWhere(sprintf('LOWER(c.code) LIKE :prefix %s', LikePattern::DQL_ESCAPE))
            ->setParameter('prefix', LikePattern::prefix($query))
            ->orderBy('c.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(fn (array $row): SearchResult => new SearchResult(
            title: Row::string($row, 'code') ?? '#' . Row::int($row, 'id'),
            subtitle: Row::string($row, 'promotionName'),
            url: $this->urlGenerator->generate('sylius_admin_promotion_coupon_index', ['promotionId' => Row::int($row, 'promotionId')]),
            icon: 'tabler:tags',
        ), Row::list($rows));
    }
}
