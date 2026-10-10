<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class TmonierSyliusCommandPaletteExtension extends Extension
{
    /** Configuration key => services of the built-in providers. */
    public const PROVIDER_SERVICES = [
        'orders' => ['tmonier_command_palette.provider.orders'],
        'customers' => ['tmonier_command_palette.provider.customers'],
        'products' => ['tmonier_command_palette.provider.products'],
        'promotions' => ['tmonier_command_palette.provider.promotions', 'tmonier_command_palette.provider.promotion_coupons'],
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{limit_per_group: int, icons: list<string>, providers: array<string, bool>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('tmonier_command_palette.limit_per_group', $config['limit_per_group']);
        $container->setParameter('tmonier_command_palette.icons', array_values(array_unique($config['icons'])));
        $container->setParameter('tmonier_command_palette.pages_enabled', $config['providers']['pages']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        foreach (self::PROVIDER_SERVICES as $provider => $serviceIds) {
            if ($config['providers'][$provider]) {
                continue;
            }
            foreach ($serviceIds as $serviceId) {
                $container->removeDefinition($serviceId);
            }
        }
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_command_palette';
    }
}
