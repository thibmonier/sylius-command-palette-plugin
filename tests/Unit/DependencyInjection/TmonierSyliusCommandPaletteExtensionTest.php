<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusCommandPalettePlugin\DependencyInjection\TmonierSyliusCommandPaletteExtension;

final class TmonierSyliusCommandPaletteExtensionTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $container = $this->load([]);

        self::assertSame(5, $container->getParameter('tmonier_command_palette.limit_per_group'));
        self::assertTrue($container->getParameter('tmonier_command_palette.pages_enabled'));
        self::assertSame([], $container->getParameter('tmonier_command_palette.icons'));
        foreach (TmonierSyliusCommandPaletteExtension::PROVIDER_SERVICES as $serviceIds) {
            foreach ($serviceIds as $serviceId) {
                self::assertTrue($container->hasDefinition($serviceId), $serviceId);
                self::assertTrue($container->getDefinition($serviceId)->hasTag('tmonier_command_palette.provider'));
            }
        }
    }

    public function testProvidersCanBeDisabled(): void
    {
        $container = $this->load([
            'limit_per_group' => 8,
            'icons' => ['tabler:truck', 'tabler:truck'],
            'providers' => ['promotions' => false, 'customers' => false, 'pages' => false],
        ]);

        self::assertSame(8, $container->getParameter('tmonier_command_palette.limit_per_group'));
        self::assertSame(['tabler:truck'], $container->getParameter('tmonier_command_palette.icons'));
        self::assertFalse($container->getParameter('tmonier_command_palette.pages_enabled'));
        self::assertTrue($container->hasDefinition('tmonier_command_palette.provider.orders'));
        self::assertTrue($container->hasDefinition('tmonier_command_palette.provider.products'));
        self::assertFalse($container->hasDefinition('tmonier_command_palette.provider.customers'));
        self::assertFalse($container->hasDefinition('tmonier_command_palette.provider.promotions'));
        self::assertFalse($container->hasDefinition('tmonier_command_palette.provider.promotion_coupons'));
    }

    public function testTheLimitMustBePositive(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['limit_per_group' => 0]);
    }

    public function testTheAliasIsTheConfigurationKey(): void
    {
        self::assertSame('tmonier_sylius_command_palette', (new TmonierSyliusCommandPaletteExtension())->getAlias());
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusCommandPaletteExtension())->load([$config], $container);

        return $container;
    }
}
