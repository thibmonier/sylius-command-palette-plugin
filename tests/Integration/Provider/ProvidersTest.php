<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Integration\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\PromotionCouponInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderRegistry;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/** The built-in providers against the Sylius fixtures, in a transaction rolled back after each test. */
final class ProvidersTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager()->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager()->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        parent::tearDown();
    }

    public function testProvidersAreRegisteredByPriority(): void
    {
        $registry = self::getContainer()->get(SearchProviderRegistry::class);
        self::assertInstanceOf(SearchProviderRegistry::class, $registry);

        self::assertSame(
            ['orders', 'customers', 'products', 'promotions', 'promotion_coupons'],
            array_map(static fn (SearchProviderInterface $provider): string => $provider->getKey(), $registry->all()),
        );
    }

    public function testOrdersShowTheCustomerTheTotalAndTheStates(): void
    {
        $order = $this->findOrder();

        $results = $this->provider('orders')->search((string) $order->getNumber(), 5);

        self::assertSame('#' . $order->getNumber(), $results[0]->title);
        $customer = $order->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        self::assertSame(
            sprintf('%s (%s) · $%s · New', $customer->getFullName(), $customer->getEmail(), number_format($order->getTotal() / 100, 2)),
            $results[0]->subtitle,
        );
        self::assertSame(ucfirst(str_replace('_', ' ', (string) $order->getPaymentState())), $results[0]->badge);
    }

    public function testTheExactNumberComesBeforeTheOrdersOfMatchingCustomers(): void
    {
        $order = $this->findOrder();
        $customer = $order->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        // A customer whose e-mail starts with the number of the order.
        $customer->setEmail(ltrim((string) $order->getNumber(), '0') . 'x@example.com');
        $this->entityManager()->flush();

        $results = $this->provider('orders')->search(ltrim((string) $order->getNumber(), '0'), 5);

        self::assertSame('#' . $order->getNumber(), $results[0]->title);
        self::assertSame(count($results), count(array_unique(array_map(static fn (SearchResult $result): string => $result->url, $results))));
    }

    public function testOrdersAreFoundByCustomerLastNameAndNeverIncludeCarts(): void
    {
        $order = $this->findOrder();
        $customer = $order->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        $customer->setLastName('Zyxwvutsrq');

        $orderFactory = self::getContainer()->get('sylius.factory.order');
        self::assertInstanceOf(FactoryInterface::class, $orderFactory);
        $cart = $orderFactory->createNew();
        self::assertInstanceOf(OrderInterface::class, $cart);
        $channel = $this->entityManager()->getRepository(ChannelInterface::class)->findOneBy([]);
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $cart->setChannel($channel);
        $cart->setCurrencyCode('USD');
        $cart->setLocaleCode('en_US');
        $cart->setCustomer($customer);
        $cart->setNumber('424242424');
        $this->entityManager()->persist($cart);
        $this->entityManager()->flush();
        self::assertSame(OrderInterface::STATE_CART, $cart->getState());

        $results = $this->provider('orders')->search('zyxwv', 50);

        self::assertNotEmpty($results);
        self::assertNotContains('#424242424', array_map(static fn (SearchResult $result): string => $result->title, $results));
        self::assertSame([], $this->provider('orders')->search('424242424', 5));
    }

    public function testCustomersAreFoundByNameFullNamePhoneAndCaseInsensitiveEmail(): void
    {
        // The fixture phone number is random.
        $john = $this->entityManager()->getRepository(CustomerInterface::class)->findOneBy(['email' => 'shop@example.com']);
        self::assertInstanceOf(CustomerInterface::class, $john);
        $john->setPhoneNumber('+1-325-555-0100');
        $this->entityManager()->flush();

        $customers = $this->provider('customers');

        foreach (['SHOP@EXAMPLE.COM', 'john doe', 'Jo D', '+1-325'] as $query) {
            $titles = array_map(static fn (SearchResult $result): string => $result->title, $customers->search($query, 50));
            self::assertContains('John Doe', $titles, $query);
        }

        $john = $customers->search('shop@example.com', 5)[0];
        self::assertSame('shop@example.com · Retail', $john->subtitle);
        self::assertSame('tabler:user', $john->icon);
    }

    public function testEveryProviderRespectsTheLimit(): void
    {
        // The fixtures spread orders randomly: give three placed orders to the same customer.
        /** @var list<OrderInterface> $orders */
        $orders = $this->entityManager()->createQueryBuilder()
            ->select('o')
            ->from(OrderInterface::class, 'o')
            ->andWhere('o.state != :cart')
            ->setParameter('cart', OrderInterface::STATE_CART)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult()
        ;
        self::assertCount(3, $orders, 'The fixtures contain placed orders.');
        $customer = $orders[0]->getCustomer();
        self::assertInstanceOf(CustomerInterface::class, $customer);
        foreach ($orders as $order) {
            $order->setCustomer($customer);
        }
        $this->entityManager()->flush();
        $email = (string) $customer->getEmail();

        foreach (['orders' => $email, 'customers' => 'a', 'products' => 'a', 'promotions' => 'a'] as $key => $query) {
            self::assertCount(2, $this->provider($key)->search($query, 2), $key);
        }
    }

    public function testVariantsAreFoundByCodeAndLinkToTheirEditPage(): void
    {
        $variant = $this->entityManager()->getRepository(ProductVariantInterface::class)->findOneBy(['code' => 'Apollo_T_Shirt-variant-1']);
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $product = $variant->getProduct();
        self::assertInstanceOf(ProductInterface::class, $product);

        $results = $this->provider('products')->search('apollo_t_shirt-variant-1', 5);

        self::assertCount(1, $results);
        self::assertSame('Apollo_T_Shirt-variant-1', $results[0]->title);
        self::assertSame(sprintf('/admin/products/%d/variants/%d/edit', $this->idOf($product), $this->idOf($variant)), $results[0]->url);
        self::assertSame('Variant', $results[0]->badge);
        self::assertStringStartsWith('Apollo T-Shirt', (string) $results[0]->subtitle);
    }

    public function testVariantsOfAFoundProductAreNotListed(): void
    {
        $results = $this->provider('products')->search('Apollo_T_Shirt', 10);

        self::assertSame(['Apollo T-Shirt'], array_map(static fn (SearchResult $result): string => $result->title, $results));
    }

    public function testDisabledProductsHaveABadge(): void
    {
        $product = $this->entityManager()->getRepository(ProductInterface::class)->findOneBy(['code' => 'Apollo_T_Shirt']);
        self::assertInstanceOf(ProductInterface::class, $product);
        $product->disable();
        $this->entityManager()->flush();

        self::assertSame('Disabled', $this->provider('products')->search('Apollo_T_Shirt', 5)[0]->badge);
    }

    public function testPromotionsAndCouponsAreFoundByNameOrCode(): void
    {
        $promotion = $this->provider('promotions')->search('christ', 5);
        self::assertSame('Christmas', $promotion[0]->title);
        self::assertSame('christmas', $promotion[0]->subtitle);
        self::assertMatchesRegularExpression('#^/admin/promotions/\d+/edit$#', $promotion[0]->url);

        $coupon = $this->entityManager()->getRepository(PromotionCouponInterface::class)->findOneBy(['code' => 'CHRISTMAS_SALE']);
        self::assertInstanceOf(PromotionCouponInterface::class, $coupon);
        $promotion = $coupon->getPromotion();
        self::assertNotNull($promotion);
        $coupons = $this->provider('promotion_coupons')->search('christmas_s', 5);
        self::assertSame('CHRISTMAS_SALE', $coupons[0]->title);
        self::assertSame(sprintf('/admin/promotions/%d/coupons/', $this->idOf($promotion)), $coupons[0]->url);
        self::assertSame('tabler:tags', $coupons[0]->icon);
    }

    public function testLikeWildcardsAreEscapedInEveryProvider(): void
    {
        foreach (['orders', 'customers', 'products', 'promotions', 'promotion_coupons'] as $key) {
            self::assertSame([], $this->provider($key)->search('%', 5), $key);
            self::assertSame([], $this->provider($key)->search('_%', 5), $key);
        }
        // "_" is a literal underscore: "christmas_sale" matches, "christmas%ale" does not.
        self::assertSame([], $this->provider('promotion_coupons')->search('christmas%ale', 5));
        self::assertSame([], $this->provider('promotion_coupons')->search('christmasxsale', 5));
    }

    private function provider(string $key): SearchProviderInterface
    {
        $provider = self::getContainer()->get('tmonier_command_palette.provider.' . $key);
        self::assertInstanceOf(SearchProviderInterface::class, $provider);

        return $provider;
    }

    private function findOrder(): OrderInterface
    {
        /** @var OrderInterface|null $order */
        $order = $this->entityManager()->createQueryBuilder()
            ->select('o')
            ->from(OrderInterface::class, 'o')
            ->innerJoin('o.customer', 'c')
            ->andWhere('o.state = :state')
            ->andWhere('o.currencyCode = :currency')
            ->setParameter('state', OrderInterface::STATE_NEW)
            ->setParameter('currency', 'USD')
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        self::assertNotNull($order);

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
}
