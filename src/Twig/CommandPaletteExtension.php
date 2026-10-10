<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Twig;

use Tmonier\SyliusCommandPalettePlugin\Page\AdminPageCatalogInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\CommandPaletteSearch;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CommandPaletteExtension extends AbstractExtension
{
    /** Icons of the built-in providers and of the palette itself (all shipped by Sylius). */
    public const BUILT_IN_ICONS = [
        'tabler:arrow-right',
        'tabler:search',
        'tabler:shopping-cart',
        'tabler:user',
        'tabler:cube',
        'tabler:list-details',
        'tabler:percentage',
        'tabler:tags',
        'tabler:folder',
    ];

    /** @param list<string> $extraIcons */
    public function __construct(
        private readonly AdminPageCatalogInterface $pageCatalog,
        private readonly bool $pagesEnabled,
        private readonly int $limitPerGroup,
        private readonly array $extraIcons = [],
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_command_palette_data', $this->getData(...)),
            new TwigFunction('tmonier_command_palette_icons', $this->getIcons(...)),
        ];
    }

    /** @return array{pages: list<SearchResult>, actions: list<SearchResult>, limit: int, minLength: int, maxLength: int} */
    public function getData(): array
    {
        return [
            'pages' => $this->pagesEnabled ? $this->pageCatalog->getPages() : [],
            'actions' => $this->pagesEnabled ? $this->pageCatalog->getActions() : [],
            'limit' => $this->limitPerGroup,
            'minLength' => CommandPaletteSearch::MIN_LENGTH,
            'maxLength' => CommandPaletteSearch::MAX_LENGTH,
        ];
    }

    /**
     * Icons rendered once as <template> elements, cloned by the script (no HTML built from data).
     *
     * @return list<string>
     */
    public function getIcons(): array
    {
        $icons = array_merge(self::BUILT_IN_ICONS, $this->extraIcons);
        if ($this->pagesEnabled) {
            foreach ([...$this->pageCatalog->getPages(), ...$this->pageCatalog->getActions()] as $page) {
                if (null !== $page->icon) {
                    $icons[] = $page->icon;
                }
            }
        }

        return array_values(array_unique($icons));
    }
}
