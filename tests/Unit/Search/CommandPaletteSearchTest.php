<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Unit\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Tmonier\SyliusCommandPalettePlugin\Search\CommandPaletteSearch;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchProviderRegistry;
use Tmonier\SyliusCommandPalettePlugin\Search\SearchResult;

final class CommandPaletteSearchTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function queries(): iterable
    {
        yield 'trimmed' => ['  john  ', 'john'];
        yield 'whitespace collapsed' => ["john \t\n doe", 'john doe'];
        yield 'cut to 100 characters' => [str_repeat('é', 150), str_repeat('é', 100)];
        yield 'invalid UTF-8' => ["\xB1\x31", ''];
    }

    #[DataProvider('queries')]
    public function testItNormalizesTheQuery(string $query, string $expected): void
    {
        self::assertSame($expected, CommandPaletteSearch::normalize($query));
    }

    public function testQueriesShorterThanTwoCharactersDoNotReachTheProviders(): void
    {
        $provider = new StubProvider('orders', [$this->createResult('#1')]);

        self::assertSame([], $this->createSearch([$provider])->search(' a '));
        self::assertSame([], $provider->calls);
    }

    public function testItReturnsTheNonEmptyGroupsInTheProvidersOrder(): void
    {
        $orders = new StubProvider('orders', [$this->createResult('#1'), $this->createResult('#2')]);
        $customers = new StubProvider('customers');
        $giftCards = new StubProvider('gift_cards', [$this->createResult('GIFT-1')]);

        $groups = $this->createSearch([$orders, $customers, $giftCards])->search('  jo ');

        self::assertSame([['jo', 3], ['jo', 3], ['jo', 3]], [...$orders->calls, ...$customers->calls, ...$giftCards->calls]);
        self::assertCount(2, $groups);
        self::assertSame('orders', $groups[0]['key']);
        self::assertSame('Orders', $groups[0]['label']);
        self::assertCount(2, $groups[0]['results']);
        // No translation: the key is humanized.
        self::assertSame('gift_cards', $groups[1]['key']);
        self::assertSame('Gift cards', $groups[1]['label']);
    }

    public function testProvidersReturningTooManyResultsAreCut(): void
    {
        $provider = new StubProvider('orders', array_map($this->createResult(...), ['#1', '#2', '#3', '#4']));

        self::assertCount(3, $this->createSearch([$provider])->search('order')[0]['results']);
    }

    public function testAFailingProviderIsLoggedAndSkipped(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('Command palette provider'),
            self::callback(static fn (array $context): bool => 'broken' === $context['provider'] && 'Boom' === $context['message']),
        );

        $groups = $this->createSearch([
            new StubProvider('broken', [], new \RuntimeException('Boom')),
            new StubProvider('orders', [$this->createResult('#1')]),
        ], $logger)->search('boom');

        self::assertSame(['orders'], array_column($groups, 'key'));
    }

    /** @param list<StubProvider> $providers */
    private function createSearch(array $providers, ?LoggerInterface $logger = null): CommandPaletteSearch
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['tmonier_command_palette.group.orders' => 'Orders'], 'en');

        return new CommandPaletteSearch(new SearchProviderRegistry($providers), $translator, $logger ?? new NullLogger(), 3);
    }

    private function createResult(string $title): SearchResult
    {
        return new SearchResult($title, null, '/admin/' . $title);
    }
}
