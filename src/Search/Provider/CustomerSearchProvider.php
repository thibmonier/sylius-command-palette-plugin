<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\LikePattern;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/**
 * Customers whose e-mail, first name, last name or phone number starts with the query;
 * "John Do" also matches first name "John" + last name "Do…". One scalar query.
 */
final class CustomerSearchProvider implements SearchProviderInterface
{
    /** @param class-string $customerClass */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $customerClass,
    ) {
    }

    public function getKey(): string
    {
        return 'customers';
    }

    public function search(string $query, int $limit): array
    {
        $escape = LikePattern::DQL_ESCAPE;
        $conditions = [
            "c.emailCanonical LIKE :prefix $escape",
            "LOWER(c.firstName) LIKE :prefix $escape",
            "LOWER(c.lastName) LIKE :prefix $escape",
        ];
        $parameters = ['prefix' => LikePattern::prefix($query)];

        if (1 === preg_match('/^\+?[\d\s().-]+$/', $query)) {
            $conditions[] = "c.phoneNumber LIKE :phone $escape";
            $parameters['phone'] = LikePattern::prefix($query);
        }

        $words = preg_split('/\s+/', $query, 2);
        if (is_array($words) && 2 === count($words)) {
            $conditions[] = "(LOWER(c.firstName) LIKE :firstName $escape AND LOWER(c.lastName) LIKE :lastName $escape)";
            $parameters['firstName'] = LikePattern::prefix($words[0]);
            $parameters['lastName'] = LikePattern::prefix($words[1]);
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('c.id AS id', 'c.email AS email', 'c.firstName AS firstName', 'c.lastName AS lastName', 'g.name AS groupName')
            ->from($this->customerClass, 'c')
            ->leftJoin('c.group', 'g')
            ->andWhere(implode(' OR ', $conditions))
            ->orderBy('c.id', 'DESC')
            ->setMaxResults($limit)
        ;
        foreach ($parameters as $name => $value) {
            $queryBuilder->setParameter($name, $value);
        }

        return array_map(
            $this->createResult(...),
            Row::list($queryBuilder->getQuery()->getArrayResult()),
        );
    }

    /** @param array<mixed> $row */
    private function createResult(array $row): SearchResult
    {
        $email = Row::string($row, 'email');
        $name = trim(sprintf('%s %s', Row::string($row, 'firstName') ?? '', Row::string($row, 'lastName') ?? ''));

        return new SearchResult(
            title: '' !== $name ? $name : ($email ?? '#' . Row::int($row, 'id')),
            subtitle: implode(' · ', array_filter(['' !== $name ? $email : null, Row::string($row, 'groupName')])),
            url: $this->urlGenerator->generate('sylius_admin_customer_show', ['id' => Row::int($row, 'id')]),
            icon: 'tabler:user',
        );
    }
}
