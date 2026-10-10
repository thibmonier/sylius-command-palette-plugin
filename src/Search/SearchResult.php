<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Search;

/** One line of the command palette. Every field is rendered as plain text. */
final class SearchResult implements \JsonSerializable
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly string $url,
        /** UX Icons name, e.g. "tabler:shopping-cart" */
        public readonly ?string $icon = null,
        public readonly ?string $badge = null,
    ) {
    }

    /** @return array{title: string, subtitle: ?string, url: string, icon: ?string, badge: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'icon' => $this->icon,
            'badge' => $this->badge,
        ];
    }
}
