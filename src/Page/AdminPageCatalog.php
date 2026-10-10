<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Page;

use Knp\Menu\ItemInterface;
use Knp\Menu\Provider\MenuProviderInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

/**
 * Admin pages (from the admin main menu) and "create" actions, rendered once in the layout and
 * filtered in the browser.
 */
final class AdminPageCatalog implements AdminPageCatalogInterface
{
    /** route => [translation key, icon] */
    public const DEFAULT_ACTIONS = [
        'sylius_admin_product_create_simple' => ['tmonier_command_palette.action.new_simple_product', 'tabler:cube'],
        'sylius_admin_product_create' => ['tmonier_command_palette.action.new_configurable_product', 'tabler:cube'],
        'sylius_admin_taxon_create' => ['tmonier_command_palette.action.new_taxon', 'tabler:folder'],
        'sylius_admin_customer_create' => ['tmonier_command_palette.action.new_customer', 'tabler:user'],
        'sylius_admin_promotion_create' => ['tmonier_command_palette.action.new_promotion', 'tabler:percentage'],
        'sylius_admin_catalog_promotion_create' => ['tmonier_command_palette.action.new_catalog_promotion', 'tabler:percentage'],
    ];

    /** @var list<SearchResult>|null */
    private ?array $pages = null;

    /** @var list<SearchResult>|null */
    private ?array $actions = null;

    /** @param array<string, array{0: string, 1: string}> $actionDefinitions route => [translation key, icon] */
    public function __construct(
        private readonly MenuProviderInterface $menuProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly string $menuName = 'sylius_admin.main',
        private readonly array $actionDefinitions = self::DEFAULT_ACTIONS,
    ) {
    }

    public function getPages(): array
    {
        if (null !== $this->pages) {
            return $this->pages;
        }

        $pages = [];
        if ($this->menuProvider->has($this->menuName)) {
            $this->collect($this->menuProvider->get($this->menuName), null, null, $pages);
        }

        return $this->pages = array_values($pages);
    }

    public function getActions(): array
    {
        if (null !== $this->actions) {
            return $this->actions;
        }

        $actions = [];
        $subtitle = $this->translator->trans('tmonier_command_palette.group.actions');
        foreach ($this->actionDefinitions as $route => [$label, $icon]) {
            try {
                $url = $this->urlGenerator->generate($route);
            } catch (RoutingException) {
                continue; // route removed or requiring parameters
            }

            $actions[] = new SearchResult($this->translator->trans($label), $subtitle, $url, $icon);
        }

        return $this->actions = $actions;
    }

    /** @param array<string, SearchResult> $pages */
    private function collect(ItemInterface $item, ?string $parentLabel, ?string $parentIcon, array &$pages): void
    {
        foreach ($item->getChildren() as $child) {
            if (!$child->isDisplayed()) {
                continue;
            }

            $label = $this->translateLabel($child);
            $icon = $this->getIcon($child) ?? $parentIcon;
            $uri = $child->getUri();

            if (null !== $uri && $this->isInternal($child, $uri) && !isset($pages[$uri])) {
                $pages[$uri] = new SearchResult($label, $parentLabel, $uri, $icon);
            }

            if ($child->hasChildren()) {
                $this->collect($child, $label, $icon, $pages);
            }
        }
    }

    private function translateLabel(ItemInterface $item): string
    {
        $domain = $item->getExtra('translation_domain', 'messages');
        $parameters = $item->getExtra('translation_params', []);

        return $this->translator->trans(
            $item->getLabel(),
            is_array($parameters) ? $parameters : [],
            is_string($domain) ? $domain : null,
        );
    }

    private function getIcon(ItemInterface $item): ?string
    {
        $icon = $item->getLabelAttribute('icon');

        return is_string($icon) && '' !== $icon ? $icon : null;
    }

    /** Relative URLs of this application only: external links (documentation, support) are skipped. */
    private function isInternal(ItemInterface $item, string $uri): bool
    {
        return str_starts_with($uri, '/') && !str_starts_with($uri, '//') && '_blank' !== $item->getLinkAttribute('target');
    }
}
