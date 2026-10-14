<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Functional;

use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class AdminSearchEndpointTest extends WebTestCase
{
    use CommandPaletteTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();

        // Clear any leftover rate-limiter state so the low per_admin limit in the test
        // application config does not bleed across tests.
        $pool = self::getContainer()->get('cache.tmonier_command_palette_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheEndpointRequiresAnAdministrator(): void
    {
        $this->client->request('GET', '/admin/command-palette/search', ['q' => 'shop@example.com']);

        self::assertResponseRedirects('/admin/login');
    }

    public function testShopCustomersCannotUseTheEndpoint(): void
    {
        $customer = $this->entityManager()->getRepository(CustomerInterface::class)->findOneBy(['email' => 'shop@example.com']);
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $shopUser = $customer->getUser();
        self::assertNotNull($shopUser);
        $this->client->loginUser($shopUser, 'shop');

        $this->client->request('GET', '/admin/command-palette/search', ['q' => 'shop@example.com']);

        self::assertResponseRedirects('/admin/login');
    }

    public function testItFindsAnOrderByItsNumberWithOrWithoutLeadingZeros(): void
    {
        $this->loginAdmin();
        $order = $this->findPlacedOrder();
        $number = (string) $order->getNumber();
        $expectedUrl = sprintf('/admin/orders/%d', $this->idOf($order));

        foreach ([$number, '#' . ltrim($number, '0'), ltrim($number, '0')] as $query) {
            $orders = $this->resultsOf($this->search($query), 'orders');
            self::assertNotEmpty($orders, $query);
            self::assertSame('#' . $number, $orders[0]['title'], $query);
            self::assertSame($expectedUrl, $orders[0]['url'], $query);
            self::assertSame('tabler:shopping-cart', $orders[0]['icon']);
            self::assertNotNull($orders[0]['subtitle']);
            self::assertStringContainsString((string) $order->getCustomer()?->getEmail(), $orders[0]['subtitle']);
        }
    }

    public function testItFindsACustomerByEmail(): void
    {
        $this->loginAdmin();
        $customer = $this->entityManager()->getRepository(CustomerInterface::class)->findOneBy(['email' => 'shop@example.com']);
        self::assertInstanceOf(CustomerInterface::class, $customer);

        $data = $this->search('shop@example');

        $customers = $this->resultsOf($data, 'customers');
        self::assertCount(1, $customers);
        self::assertSame('John Doe', $customers[0]['title']);
        self::assertSame(sprintf('/admin/customers/%d', $this->idOf($customer)), $customers[0]['url']);
        self::assertStringStartsWith('shop@example.com', (string) $customers[0]['subtitle']);
        // Orders of that customer are found by e-mail too.
        foreach ($this->resultsOf($data, 'orders') as $order) {
            self::assertStringContainsString('shop@example.com', (string) $order['subtitle']);
        }
    }

    public function testItFindsAProductByNameAndByCode(): void
    {
        $this->loginAdmin();
        $product = $this->entityManager()->getRepository(ProductInterface::class)->findOneBy(['code' => 'Apollo_T_Shirt']);
        self::assertInstanceOf(ProductInterface::class, $product);
        $expectedUrl = sprintf('/admin/products/%d/edit', $this->idOf($product));

        $byName = $this->resultsOf($this->search('apollo t-sh'), 'products');
        self::assertSame('Apollo T-Shirt', $byName[0]['title']);
        self::assertSame('Apollo_T_Shirt', $byName[0]['subtitle']);
        self::assertSame($expectedUrl, $byName[0]['url']);

        $byCode = $this->resultsOf($this->search('APOLLO_T'), 'products');
        self::assertSame($expectedUrl, $byCode[0]['url']);
    }

    public function testItReturnsGroupsWithTheDocumentedShape(): void
    {
        $this->loginAdmin();

        $data = $this->search('  Apollo  ');

        self::assertSame('Apollo', $data['query']);
        self::assertNotEmpty($data['groups']);
        foreach ($data['groups'] as $group) {
            self::assertSame(['key', 'label', 'results'], array_keys($group));
            self::assertNotSame('', $group['label']);
            self::assertStringNotContainsString('tmonier_command_palette.', $group['label']);
            self::assertNotEmpty($group['results']);
            self::assertLessThanOrEqual(5, count($group['results']));
            foreach ($group['results'] as $result) {
                self::assertSame(['title', 'subtitle', 'url', 'icon', 'badge'], array_keys($result));
                self::assertStringStartsWith('/admin/', $result['url']);
            }
        }
        self::assertSame('Products', $data['groups'][0]['label']);
    }

    public function testTooShortQueriesReturnNoGroups(): void
    {
        $this->loginAdmin();

        self::assertSame(['query' => 'a', 'groups' => []], $this->search(' a '));
        self::assertSame(['query' => '', 'groups' => []], $this->search(''));
    }

    public function testLongQueriesAreCut(): void
    {
        $this->loginAdmin();

        self::assertSame(str_repeat('x', 100), $this->search(str_repeat('x', 300))['query']);
    }

    public function testWildcardsTypedByTheAdministratorAreLiteral(): void
    {
        $this->loginAdmin();

        self::assertSame([], $this->search('%%')['groups']);
        self::assertSame([], $this->search('__')['groups']);
        self::assertSame([], $this->resultsOf($this->search('apollo%shirt'), 'products'));
    }

    public function testTheNumberOfQueriesDoesNotDependOnTheNumberOfResults(): void
    {
        $this->loginAdmin();
        $this->client->enableProfiler();

        // A broad query filling every group, so that any per-result query would show up.
        $this->search('an');
        $profile = $this->client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        // Admin user + channel, then at most 2 queries for orders, 1 for customers, 2 for
        // products, 1 for promotions and 1 for coupons, whatever the number of results.
        self::assertLessThanOrEqual(9, $collector->getQueryCount());
    }
}
