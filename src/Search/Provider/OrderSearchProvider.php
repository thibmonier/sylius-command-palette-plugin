<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\LikePattern;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/**
 * Placed orders (never carts): exact number first, then the latest orders of customers whose
 * e-mail or last name starts with the query. Two scalar queries at most.
 */
final readonly class OrderSearchProvider implements SearchProviderInterface
{
    /** @param class-string $orderClass */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private MoneyFormatterInterface $moneyFormatter,
        private string $orderClass,
    ) {
    }

    public function getKey(): string
    {
        return 'orders';
    }

    public function search(string $query, int $limit): array
    {
        $rows = [];

        $numbers = OrderNumber::candidates($query);
        if ([] !== $numbers) {
            $rows = $this->fetch(
                $this->createQueryBuilder()
                    ->andWhere('o.number IN (:numbers)')
                    ->setParameter('numbers', $numbers)
                    ->setMaxResults($limit),
            );
        }

        if (count($rows) < $limit) {
            $queryBuilder = $this->createQueryBuilder()
                ->andWhere(sprintf('c.emailCanonical LIKE :prefix %1$s OR LOWER(c.lastName) LIKE :prefix %1$s', LikePattern::DQL_ESCAPE))
                ->setParameter('prefix', LikePattern::prefix($query))
                ->addOrderBy('o.checkoutCompletedAt', 'DESC')
                ->addOrderBy('o.id', 'DESC')
                ->setMaxResults($limit - count($rows))
            ;
            if ([] !== $rows) {
                $queryBuilder
                    ->andWhere('o.id NOT IN (:found)')
                    ->setParameter('found', array_map(static fn (array $row): int => Row::int($row, 'id'), $rows))
                ;
            }

            $rows = array_merge($rows, $this->fetch($queryBuilder));
        }

        return array_map($this->createResult(...), $rows);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select(
                'o.id AS id',
                'o.number AS number',
                'o.total AS total',
                'o.currencyCode AS currencyCode',
                'o.localeCode AS localeCode',
                'o.state AS state',
                'o.paymentState AS paymentState',
                'c.email AS email',
                'c.firstName AS firstName',
                'c.lastName AS lastName',
            )
            ->from($this->orderClass, 'o')
            ->leftJoin('o.customer', 'c')
            ->andWhere('o.state != :cart')
            ->setParameter('cart', OrderInterface::STATE_CART)
        ;
    }

    /** @return list<array<mixed>> */
    private function fetch(QueryBuilder $queryBuilder): array
    {
        return Row::list($queryBuilder->getQuery()->getArrayResult());
    }

    /** @param array<mixed> $row */
    private function createResult(array $row): SearchResult
    {
        $customer = trim(sprintf('%s %s', Row::string($row, 'firstName') ?? '', Row::string($row, 'lastName') ?? ''));
        $email = Row::string($row, 'email');
        if (null !== $email) {
            $customer = '' === $customer ? $email : sprintf('%s (%s)', $customer, $email);
        }

        $currencyCode = Row::string($row, 'currencyCode');
        $state = Row::string($row, 'state');
        $paymentState = Row::string($row, 'paymentState');

        $subtitle = array_filter([
            '' === $customer ? null : $customer,
            null === $currencyCode ? null : $this->moneyFormatter->format(Row::int($row, 'total'), $currencyCode, $this->translator->getLocale()),
            null === $state ? null : $this->translator->trans('sylius.ui.' . $state),
        ]);

        return new SearchResult(
            title: '#' . (Row::string($row, 'number') ?? (string) Row::int($row, 'id')),
            subtitle: implode(' · ', $subtitle),
            url: $this->urlGenerator->generate('sylius_admin_order_show', ['id' => Row::int($row, 'id')]),
            icon: 'tabler:shopping-cart',
            badge: null === $paymentState ? null : $this->translator->trans('sylius.ui.' . $paymentState),
        );
    }
}
