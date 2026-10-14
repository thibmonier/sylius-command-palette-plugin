<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\IdentityTranslator;
use Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search\StubProvider;
use Tmonier\SyliusCommandPalettePlugin\Controller\Admin\SearchController;
use Tmonier\SyliusCommandPalettePlugin\Search\CommandPaletteSearch;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderRegistry;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

final class SearchControllerTest extends TestCase
{
    public function testItReturnsTheGroupsAsJson(): void
    {
        $provider = new StubProvider('orders', [new SearchResult('#000000022', 'John Doe', '/admin/orders/22', 'tabler:shopping-cart')]);

        $response = $this->createController($provider, true)(new Request(['q' => "  #22 \n"]));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertSame([['#22', 5]], $provider->calls);
        self::assertSame([
            'query' => '#22',
            'groups' => [[
                'key' => 'orders',
                'label' => 'Orders',
                'results' => [[
                    'title' => '#000000022',
                    'subtitle' => 'John Doe',
                    'url' => '/admin/orders/22',
                    'icon' => 'tabler:shopping-cart',
                    'badge' => null,
                ]],
            ]],
        ], json_decode((string) $response->getContent(), true));
    }

    public function testTheQueryIsLimitedTo100Characters(): void
    {
        $provider = new StubProvider('orders');

        $this->createController($provider, true)(new Request(['q' => str_repeat('a', 500)]));

        self::assertSame([[str_repeat('a', 100), 5]], $provider->calls);
    }

    public function testNonStringQueriesAreIgnored(): void
    {
        $provider = new StubProvider('orders');

        $response = $this->createController($provider, true)(new Request(['q' => ['a' => 'b']]));

        self::assertSame('{"query":"","groups":[]}', $response->getContent());
        self::assertSame([], $provider->calls);
    }

    public function testItRequiresTheAdministrationAccess(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(new StubProvider('orders'), false)(new Request(['q' => 'john']));
    }

    private function createController(StubProvider $provider, bool $granted): SearchController
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with('ROLE_ADMINISTRATION_ACCESS')->willReturn($granted);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        return new SearchController(
            new CommandPaletteSearch(new SearchProviderRegistry([$provider]), new IdentityTranslator(), new NullLogger(), 5),
            $authorizationChecker,
            $security,
        );
    }
}
