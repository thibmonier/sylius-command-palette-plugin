<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminLayoutTest extends WebTestCase
{
    use CommandPaletteTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheDashboardContainsTheTriggerTheDialogAndTheAssets(): void
    {
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#navbar-menu [data-tmonier-command-palette-trigger]');
        self::assertSelectorTextContains('[data-tmonier-command-palette-trigger]', 'Search…');
        // The product search of Sylius is still there.
        self::assertSelectorExists('#navbar-menu form[action="/admin/products/"]');
        self::assertSelectorExists('head link[rel="stylesheet"][href^="/bundles/tmoniersyliuscommandpaletteplugin/command-palette.css"]');
        self::assertSelectorExists('script[src^="/bundles/tmoniersyliuscommandpaletteplugin/command-palette.js"]');

        $palette = $crawler->filter('[data-tmonier-command-palette]');
        self::assertCount(1, $palette);
        self::assertSame('/admin/command-palette/search', $palette->attr('data-search-url'));
        self::assertSelectorExists('[data-tmonier-command-palette] [role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('[data-tmonier-command-palette] input[role="combobox"][aria-controls="tmonier-cp-listbox"]');
        self::assertSelectorExists('#tmonier-cp-listbox[role="listbox"]');

        $config = json_decode((string) $palette->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame(5, $config['limit']);
        self::assertSame(2, $config['minLength']);
        self::assertIsArray($config['pages']);
        $pages = array_column($config['pages'], 'url', 'title');
        self::assertSame('/admin/', $pages['Dashboard'] ?? null);
        self::assertSame('/admin/orders/', $pages['Orders'] ?? null);
        self::assertSame('/admin/products/', $pages['Products'] ?? null);
        self::assertIsArray($config['actions']);
        self::assertContains('/admin/products/new/simple', array_column($config['actions'], 'url'));

        $messages = json_decode((string) $palette->attr('data-messages'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($messages);
        self::assertSame('Pages', $messages['pages']);
        self::assertIsString($messages['noResults']);
        self::assertStringContainsString('%query%', $messages['noResults']);

        // Every icon used by the pages is rendered once as a template, from the Sylius icon set.
        $icons = $crawler->filter('[data-tmonier-cp-icons] template')->each(static fn ($node): string => (string) $node->attr('data-icon'));
        self::assertContains('tabler:shopping-cart', $icons);
        foreach (array_filter(array_column($config['pages'], 'icon')) as $icon) {
            self::assertContains($icon, $icons);
        }
        self::assertSame(count($icons), count(array_unique($icons)));

        self::assertStringNotContainsString('tmonier_command_palette.', $crawler->filter('body')->text());
    }

    public function testThePaletteIsOnOtherAdminPagesToo(): void
    {
        $this->loginAdmin();

        $this->client->request('GET', '/admin/orders/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-tmonier-command-palette-trigger]');
        self::assertSelectorExists('[data-tmonier-command-palette]');
    }

    public function testTheLoginPageDoesNotLoadThePalette(): void
    {
        $this->client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-tmonier-command-palette]');
        self::assertSelectorNotExists('script[src*="tmoniersyliuscommandpaletteplugin"]');
        self::assertSelectorNotExists('link[href*="tmoniersyliuscommandpaletteplugin"]');
    }

    public function testTheShopDoesNotLoadThePalette(): void
    {
        $crawler = $this->client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-tmonier-command-palette]');
        self::assertSelectorNotExists('[data-tmonier-command-palette-trigger]');
        self::assertStringNotContainsString('tmoniersyliuscommandpaletteplugin', $crawler->html());
    }
}
