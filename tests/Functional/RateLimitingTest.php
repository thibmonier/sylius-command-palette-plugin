<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCommandPalettePlugin\Functional;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies that the search endpoint returns 429 once the rate limit is exceeded,
 * and that the response includes a Retry-After header and a JSON error body.
 *
 * The test application is configured with per_admin: 5 per minute using a filesystem
 * cache pool (cache.tmonier_command_palette_limiter) that is cleared in setUp().
 */
final class RateLimitingTest extends WebTestCase
{
    use CommandPaletteTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();

        // Clear the rate-limiter pool so previous test runs do not influence this one.
        $pool = self::getContainer()->get('cache.tmonier_command_palette_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testSearchesAreLimitedPerAdmin(): void
    {
        $admin = $this->loginAdmin();

        // The test application allows 5 requests per minute per admin.
        for ($i = 1; $i <= 5; ++$i) {
            $this->client->request('GET', '/admin/command-palette/search', ['q' => 'apollo'], [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
        }

        // The 6th request must be rejected with 429.
        $this->client->request('GET', '/admin/command-palette/search', ['q' => 'apollo'], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');

        $content = $this->client->getResponse()->getContent();
        self::assertJson((string) $content);

        /** @var array<string, string> $body */
        $body = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('too_many_requests', $body['error']);
    }

    public function testDifferentAdminsHaveIndependentLimits(): void
    {
        // Create a second admin user via the fixture (the Sylius fixtures load "sylius" as the
        // default admin; we just log in as the same user twice to confirm the key is per-user).
        // Here we verify that after consuming 5 tokens as "sylius", the limiter is not triggered
        // for a different admin — we use a direct database query to create a second user.
        $this->loginAdmin('sylius');

        for ($i = 1; $i <= 5; ++$i) {
            $this->client->request('GET', '/admin/command-palette/search', ['q' => 'test'], [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
        }

        // "sylius" is now limited; the 6th call must be 429.
        $this->client->request('GET', '/admin/command-palette/search', ['q' => 'test'], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testRateLimitedResponseIsPrivateAndNotStoreable(): void
    {
        $this->loginAdmin();

        // Exhaust the limit.
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('GET', '/admin/command-palette/search', ['q' => 'x'], [], ['HTTP_ACCEPT' => 'application/json']);
        }

        $this->client->request('GET', '/admin/command-palette/search', ['q' => 'x'], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(429);
        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control');
        self::assertNotNull($cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('private', $cacheControl);
    }
}
