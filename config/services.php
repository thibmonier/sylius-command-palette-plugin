<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusCommandPalettePlugin\Controller\Admin\SearchController;
use Tmonier\SyliusCommandPalettePlugin\Page\AdminPageCatalog;
use Tmonier\SyliusCommandPalettePlugin\Page\AdminPageCatalogInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\CommandPaletteSearch;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\CustomerSearchProvider;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\OrderSearchProvider;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\ProductSearchProvider;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\PromotionCouponSearchProvider;
use Tmonier\SyliusCommandPalettePlugin\Search\Provider\PromotionSearchProvider;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderRegistry;
use Tmonier\SyliusCommandPalettePlugin\Twig\CommandPaletteExtension;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Built-in providers (removed by the extension when disabled in the configuration)
    $services
        ->set('tmonier_command_palette.provider.orders', OrderSearchProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('translator'),
            service('sylius.formatter.money'),
            param('sylius.model.order.class'),
        ])
        ->tag('tmonier_command_palette.provider', ['priority' => 500])
    ;

    $services
        ->set('tmonier_command_palette.provider.customers', CustomerSearchProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('router'),
            param('sylius.model.customer.class'),
        ])
        ->tag('tmonier_command_palette.provider', ['priority' => 400])
    ;

    $services
        ->set('tmonier_command_palette.provider.products', ProductSearchProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('translator'),
            service('sylius.context.locale'),
            param('sylius.model.product.class'),
            param('sylius.model.product_variant.class'),
            param('locale'),
        ])
        ->tag('tmonier_command_palette.provider', ['priority' => 300])
    ;

    $services
        ->set('tmonier_command_palette.provider.promotions', PromotionSearchProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('router'),
            param('sylius.model.promotion.class'),
        ])
        ->tag('tmonier_command_palette.provider', ['priority' => 200])
    ;

    $services
        ->set('tmonier_command_palette.provider.promotion_coupons', PromotionCouponSearchProvider::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('router'),
            param('sylius.model.promotion_coupon.class'),
        ])
        ->tag('tmonier_command_palette.provider', ['priority' => 100])
    ;

    // Search
    $services
        ->set('tmonier_command_palette.provider_registry', SearchProviderRegistry::class)
        ->args([tagged_iterator('tmonier_command_palette.provider')])
    ;
    $services->alias(SearchProviderRegistry::class, 'tmonier_command_palette.provider_registry');

    $services
        ->set('tmonier_command_palette.search', CommandPaletteSearch::class)
        ->args([
            service('tmonier_command_palette.provider_registry'),
            service('translator'),
            service('logger'),
            param('tmonier_command_palette.limit_per_group'),
        ])
        ->tag('monolog.logger', ['channel' => 'tmonier_command_palette'])
    ;
    $services->alias(CommandPaletteSearch::class, 'tmonier_command_palette.search');

    $services
        ->set('tmonier_command_palette.controller.admin.search', SearchController::class)
        ->args([
            service('tmonier_command_palette.search'),
            service('security.authorization_checker'),
            service('security.helper'),
            service('limiter.tmonier_command_palette_admin')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments')
        ->public()
    ;

    // Admin layout
    $services
        ->set('tmonier_command_palette.page_catalog', AdminPageCatalog::class)
        ->args([
            service('knp_menu.menu_provider'),
            service('router'),
            service('translator'),
        ])
    ;
    $services->alias(AdminPageCatalogInterface::class, 'tmonier_command_palette.page_catalog');

    $services
        ->set('tmonier_command_palette.twig.extension', CommandPaletteExtension::class)
        ->args([
            service('tmonier_command_palette.page_catalog'),
            param('tmonier_command_palette.pages_enabled'),
            param('tmonier_command_palette.limit_per_group'),
            param('tmonier_command_palette.icons'),
        ])
        ->tag('twig.extension')
    ;
};
