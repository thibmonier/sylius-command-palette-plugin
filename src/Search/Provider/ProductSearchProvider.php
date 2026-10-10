<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\LikePattern;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/**
 * Products whose name (in the administrator's locale) contains the query or whose code starts
 * with it, then variants whose code starts with the query. Two scalar queries at most.
 */
final class ProductSearchProvider implements SearchProviderInterface
{
    /**
     * @param class-string $productClass
     * @param class-string $productVariantClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly LocaleContextInterface $localeContext,
        private readonly string $productClass,
        private readonly string $productVariantClass,
        private readonly string $defaultLocale,
    ) {
    }

    public function getKey(): string
    {
        return 'products';
    }

    public function search(string $query, int $limit): array
    {
        $escape = LikePattern::DQL_ESCAPE;
        $locale = $this->getLocaleCode();

        $products = Row::list($this->entityManager->createQueryBuilder()
            ->select('p.id AS id', 'p.code AS code', 'p.enabled AS enabled', 't.name AS name')
            ->from($this->productClass, 'p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->andWhere("LOWER(t.name) LIKE :contains $escape OR LOWER(p.code) LIKE :prefix $escape")
            ->setParameter('locale', $locale)
            ->setParameter('contains', LikePattern::contains($query))
            ->setParameter('prefix', LikePattern::prefix($query))
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult());

        $results = array_map($this->createProductResult(...), $products);
        if (count($results) >= $limit) {
            return $results;
        }

        $variantQueryBuilder = $this->entityManager->createQueryBuilder()
            ->select('v.id AS id', 'v.code AS code', 'vt.name AS name', 'p.id AS productId', 't.name AS productName')
            ->from($this->productVariantClass, 'v')
            ->innerJoin('v.product', 'p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->leftJoin('v.translations', 'vt', 'WITH', 'vt.locale = :locale')
            ->andWhere("LOWER(v.code) LIKE :prefix $escape")
            ->setParameter('locale', $locale)
            ->setParameter('prefix', LikePattern::prefix($query))
            ->orderBy('v.code', 'ASC')
            ->setMaxResults($limit - count($results))
        ;
        if ([] !== $products) {
            // The variant of a simple product usually has the product's code: show the product only.
            $variantQueryBuilder
                ->andWhere('p.id NOT IN (:products)')
                ->setParameter('products', array_map(static fn (array $row): int => Row::int($row, 'id'), $products))
            ;
        }

        return array_merge($results, array_map(
            $this->createVariantResult(...),
            Row::list($variantQueryBuilder->getQuery()->getArrayResult()),
        ));
    }

    private function getLocaleCode(): string
    {
        try {
            return $this->localeContext->getLocaleCode();
        } catch (LocaleNotFoundException) {
            return $this->defaultLocale;
        }
    }

    /** @param array<mixed> $row */
    private function createProductResult(array $row): SearchResult
    {
        $code = Row::string($row, 'code');

        return new SearchResult(
            title: Row::string($row, 'name') ?? $code ?? '#' . Row::int($row, 'id'),
            subtitle: $code,
            url: $this->urlGenerator->generate('sylius_admin_product_update', ['id' => Row::int($row, 'id')]),
            icon: 'tabler:cube',
            badge: Row::bool($row, 'enabled') ? null : $this->translator->trans('sylius.ui.disabled'),
        );
    }

    /** @param array<mixed> $row */
    private function createVariantResult(array $row): SearchResult
    {
        $code = Row::string($row, 'code');

        return new SearchResult(
            title: $code ?? '#' . Row::int($row, 'id'),
            subtitle: implode(' · ', array_filter([Row::string($row, 'productName'), Row::string($row, 'name')])),
            url: $this->urlGenerator->generate('sylius_admin_product_variant_update', [
                'productId' => Row::int($row, 'productId'),
                'id' => Row::int($row, 'id'),
            ]),
            icon: 'tabler:list-details',
            badge: $this->translator->trans('tmonier_command_palette.badge.variant'),
        );
    }
}
