<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Page;

use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

interface AdminPageCatalogInterface
{
    /** @return list<SearchResult> pages of the admin main menu, with their section as subtitle */
    public function getPages(): array;

    /** @return list<SearchResult> shortcuts to the "create" pages */
    public function getActions(): array;
}
