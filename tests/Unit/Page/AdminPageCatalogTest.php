<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Page;

use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use Knp\Menu\Provider\MenuProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Tmonier\SyliusCommandPalettePlugin\Page\AdminPageCatalog;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

final class AdminPageCatalogTest extends TestCase
{
    public function testPagesComeFromTheAdminMenuWithTheirSectionAsSubtitle(): void
    {
        $pages = $this->createCatalog()->getPages();

        self::assertSame([
            ['Dashboard', null, '/admin/', 'tabler:dashboard'],
            ['Products', 'Catalog', '/admin/products/', 'tabler:cube'],
            ['Taxons', 'Catalog', '/admin/taxons/new', 'tabler:list-details'],
            ['Avis clients', 'Catalog', '/admin/reviews/', 'tabler:list-details'],
        ], array_map(static fn (SearchResult $page): array => [$page->title, $page->subtitle, $page->url, $page->icon], $pages));
    }

    public function testActionsAreLimitedToExistingRoutes(): void
    {
        $actions = $this->createCatalog()->getActions();

        self::assertCount(2, $actions);
        self::assertSame('New simple product', $actions[0]->title);
        self::assertSame('Actions', $actions[0]->subtitle);
        self::assertSame('/admin/products/new/simple', $actions[0]->url);
        self::assertSame('tabler:cube', $actions[0]->icon);
        self::assertSame('tmonier_command_palette.action.new_customer', $actions[1]->title);
    }

    public function testAMissingMenuGivesNoPages(): void
    {
        $catalog = new AdminPageCatalog($this->createMenuProvider(null), $this->createUrlGenerator(), new Translator('en'));

        self::assertSame([], $catalog->getPages());
    }

    private function createCatalog(): AdminPageCatalog
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'sylius.ui.dashboard' => 'Dashboard',
            'sylius.menu.admin.main.catalog.header' => 'Catalog',
            'sylius.menu.admin.main.catalog.products' => 'Products',
            'sylius.menu.admin.main.catalog.taxons' => 'Taxons',
            'tmonier_command_palette.action.new_simple_product' => 'New simple product',
            'tmonier_command_palette.group.actions' => 'Actions',
        ], 'en');
        $translator->addResource('array', ['app.reviews' => 'Avis clients'], 'en', 'app');

        $factory = new MenuFactory();
        $menu = $factory->createItem('root');
        $menu->addChild('dashboard', ['uri' => '/admin/'])->setLabel('sylius.ui.dashboard')->setLabelAttribute('icon', 'tabler:dashboard');
        $catalog = $menu->addChild('catalog')->setLabel('sylius.menu.admin.main.catalog.header')->setLabelAttribute('icon', 'tabler:list-details');
        $catalog->addChild('products', ['uri' => '/admin/products/'])->setLabel('sylius.menu.admin.main.catalog.products')->setLabelAttribute('icon', 'tabler:cube');
        $catalog->addChild('taxons', ['uri' => '/admin/taxons/new'])->setLabel('sylius.menu.admin.main.catalog.taxons');
        $catalog->addChild('duplicate', ['uri' => '/admin/products/'])->setLabel('Duplicate');
        $catalog->addChild('hidden', ['uri' => '/admin/hidden'])->setDisplay(false);
        $catalog->addChild('reviews', ['uri' => '/admin/reviews/', 'extras' => ['translation_domain' => 'app']])->setLabel('app.reviews');
        $support = $menu->addChild('support')->setLabel('Support');
        $support->addChild('docs', ['uri' => 'https://docs.sylius.com'])->setLabel('Documentation');
        $support->addChild('slack', ['uri' => '//sylius.com/slack'])->setLabel('Slack');
        $support->addChild('blank', ['uri' => '/admin/external', 'linkAttributes' => ['target' => '_blank']])->setLabel('Blank');

        return new AdminPageCatalog(
            $this->createMenuProvider($menu),
            $this->createUrlGenerator(),
            $translator,
            'sylius_admin.main',
            [
                'sylius_admin_product_create_simple' => ['tmonier_command_palette.action.new_simple_product', 'tabler:cube'],
                'sylius_admin_route_requiring_an_id' => ['tmonier_command_palette.action.new_taxon', 'tabler:folder'],
                'sylius_admin_unknown_route' => ['tmonier_command_palette.action.new_promotion', 'tabler:percentage'],
                'sylius_admin_customer_create' => ['tmonier_command_palette.action.new_customer', 'tabler:user'],
            ],
        );
    }

    private function createMenuProvider(?ItemInterface $menu): MenuProviderInterface
    {
        return new class($menu) implements MenuProviderInterface {
            public function __construct(private readonly ?ItemInterface $menu)
            {
            }

            public function get(string $name, array $options = []): ItemInterface
            {
                return $this->menu ?? throw new \InvalidArgumentException($name);
            }

            public function has(string $name, array $options = []): bool
            {
                return $this->menu instanceof \Knp\Menu\ItemInterface && 'sylius_admin.main' === $name;
            }
        };
    }

    private function createUrlGenerator(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('sylius_admin_product_create_simple', new Route('/admin/products/new/simple'));
        $routes->add('sylius_admin_route_requiring_an_id', new Route('/admin/taxons/{id}/new'));
        $routes->add('sylius_admin_customer_create', new Route('/admin/customers/new'));

        return new UrlGenerator($routes, new RequestContext());
    }
}
