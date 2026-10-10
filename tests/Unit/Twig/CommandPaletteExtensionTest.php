<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCommandPalettePlugin\Page\AdminPageCatalogInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;
use Tmonier\SyliusCommandPalettePlugin\Twig\CommandPaletteExtension;

final class CommandPaletteExtensionTest extends TestCase
{
    public function testItExposesThePagesAndTheLimits(): void
    {
        $data = (new CommandPaletteExtension($this->createCatalog(), true, 7))->getData();

        self::assertCount(2, $data['pages']);
        self::assertCount(1, $data['actions']);
        self::assertSame(7, $data['limit']);
        self::assertSame(2, $data['minLength']);
        self::assertSame(100, $data['maxLength']);
    }

    public function testPagesCanBeDisabled(): void
    {
        $extension = new CommandPaletteExtension($this->createCatalog(), false, 5);

        self::assertSame([], $extension->getData()['pages']);
        self::assertSame([], $extension->getData()['actions']);
        self::assertSame(CommandPaletteExtension::BUILT_IN_ICONS, $extension->getIcons());
    }

    public function testIconsAreTheBuiltInOnesPlusTheConfiguredAndPageOnesWithoutDuplicates(): void
    {
        $icons = (new CommandPaletteExtension($this->createCatalog(), true, 5, ['tabler:truck', 'tabler:cube']))->getIcons();

        self::assertSame([...CommandPaletteExtension::BUILT_IN_ICONS, 'tabler:truck', 'tabler:dashboard', 'tabler:plus'], $icons);
    }

    public function testItRegistersTheTwigFunctions(): void
    {
        $functions = (new CommandPaletteExtension($this->createCatalog(), true, 5))->getFunctions();

        self::assertSame(
            ['tmonier_command_palette_data', 'tmonier_command_palette_icons'],
            array_map(static fn ($function): string => $function->getName(), $functions),
        );
    }

    private function createCatalog(): AdminPageCatalogInterface
    {
        $catalog = $this->createMock(AdminPageCatalogInterface::class);
        $catalog->method('getPages')->willReturn([
            new SearchResult('Dashboard', null, '/admin/', 'tabler:dashboard'),
            new SearchResult('Products', 'Catalog', '/admin/products/', 'tabler:cube'),
        ]);
        $catalog->method('getActions')->willReturn([
            new SearchResult('New customer', 'Actions', '/admin/customers/new', 'tabler:plus'),
        ]);

        return $catalog;
    }
}
