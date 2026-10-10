<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const PROVIDERS = ['orders', 'customers', 'products', 'promotions', 'pages'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_command_palette');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $providers = $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('limit_per_group')
                    ->info('Maximum number of results per group.')
                    ->defaultValue(5)
                    ->min(1)
                    ->max(50)
                ->end()
                ->arrayNode('icons')
                    ->info('Extra UX Icons names used by your own providers (e.g. "tabler:truck"), rendered once in the admin layout.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('providers')
                    ->info('Built-in sources. "promotions" covers promotions and promotion coupons, "pages" the admin menu and the "create" actions.')
                    ->addDefaultsIfNotSet()
                    ->children()
        ;
        foreach (self::PROVIDERS as $provider) {
            $providers->booleanNode($provider)->defaultTrue()->end();
        }

        return $treeBuilder;
    }
}
