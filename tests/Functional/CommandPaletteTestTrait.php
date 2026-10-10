<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Tests run against the test application loaded with the Sylius fixtures, in a transaction
 * rolled back after each test (the kernel is not rebooted between requests).
 */
trait CommandPaletteTestTrait
{
    private KernelBrowser $client;

    private function setUpClient(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->connection()->beginTransaction();
    }

    private function tearDownClient(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    private function loginAdmin(string $username = 'sylius'): AdminUserInterface
    {
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');

        return $admin;
    }

    /**
     * @return array{query: string, groups: list<array{key: string, label: string, results: list<array{title: string, subtitle: ?string, url: string, icon: ?string, badge: ?string}>}>}
     */
    private function search(string $query): array
    {
        $this->client->request('GET', '/admin/command-palette/search', ['q' => $query], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertJson((string) $this->client->getResponse()->getContent());

        /** @var array{query: string, groups: list<array{key: string, label: string, results: list<array{title: string, subtitle: ?string, url: string, icon: ?string, badge: ?string}>}>} $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @param array{groups: list<array{key: string, label: string, results: list<array{title: string, subtitle: ?string, url: string, icon: ?string, badge: ?string}>}>} $data
     *
     * @return list<array{title: string, subtitle: ?string, url: string, icon: ?string, badge: ?string}>
     */
    private function resultsOf(array $data, string $group): array
    {
        foreach ($data['groups'] as $candidate) {
            if ($group === $candidate['key']) {
                return $candidate['results'];
            }
        }

        return [];
    }

    private function findPlacedOrder(): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->entityManager()
            ->createQueryBuilder()
            ->select('o')
            ->from(OrderInterface::class, 'o')
            ->innerJoin('o.customer', 'c')
            ->andWhere('o.state != :cart')
            ->setParameter('cart', OrderInterface::STATE_CART)
            ->orderBy('o.id', 'DESC') // a number of 2 digits or more
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        self::assertNotNull($order, 'The fixtures should contain a placed order.');

        return $order;
    }

    private function idOf(ResourceInterface $resource): int
    {
        $id = $resource->getId();
        self::assertIsInt($id);

        return $id;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
