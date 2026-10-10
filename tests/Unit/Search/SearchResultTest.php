<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

final class SearchResultTest extends TestCase
{
    public function testItIsSerializedWithEveryField(): void
    {
        $result = new SearchResult('#000000022', 'John Doe · $10.00 · New', '/admin/orders/22', 'tabler:shopping-cart', 'Paid');

        self::assertSame([
            'title' => '#000000022',
            'subtitle' => 'John Doe · $10.00 · New',
            'url' => '/admin/orders/22',
            'icon' => 'tabler:shopping-cart',
            'badge' => 'Paid',
        ], $result->jsonSerialize());
    }

    public function testOptionalFieldsAreNull(): void
    {
        $result = new SearchResult(title: 'Products', subtitle: null, url: '/admin/products/');

        self::assertSame(
            '{"title":"Products","subtitle":null,"url":"\/admin\/products\/","icon":null,"badge":null}',
            json_encode($result),
        );
    }
}
