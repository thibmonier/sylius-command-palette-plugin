<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderRegistry;

final class SearchProviderRegistryTest extends TestCase
{
    public function testItKeepsTheOrderOfTheProviders(): void
    {
        $registry = new SearchProviderRegistry([new StubProvider('orders'), new StubProvider('customers')]);

        self::assertSame(['orders', 'customers'], $this->keys($registry));
        self::assertTrue($registry->has('customers'));
        self::assertFalse($registry->has('products'));
    }

    public function testTaggedProvidersAreSortedByPriority(): void
    {
        $container = new ContainerBuilder();
        foreach (['low' => -10, 'high' => 500, 'default' => null, 'middle' => 100] as $key => $priority) {
            $definition = new Definition(StubProvider::class, [$key]);
            $definition->addTag('tmonier_command_palette.provider', null === $priority ? [] : ['priority' => $priority]);
            $container->setDefinition('provider.' . $key, $definition);
        }
        $container->register('registry', SearchProviderRegistry::class)
            ->addArgument(new TaggedIteratorArgument('tmonier_command_palette.provider'))
            ->setPublic(true)
        ;
        $container->compile();

        $registry = $container->get('registry');
        self::assertInstanceOf(SearchProviderRegistry::class, $registry);
        self::assertSame(['high', 'middle', 'default', 'low'], $this->keys($registry));
    }

    public function testKeysMustBeUnique(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Two command palette providers use the key "orders"');

        new SearchProviderRegistry([new StubProvider('orders'), new StubProvider('orders')]);
    }

    public function testKeysMustBeSnakeCase(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SearchProviderRegistry([new StubProvider('Gift cards')]);
    }

    /** @return list<string> */
    private function keys(SearchProviderRegistry $registry): array
    {
        return array_map(static fn (SearchProviderInterface $provider): string => $provider->getKey(), $registry->all());
    }
}
