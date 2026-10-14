<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Controller\Admin;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tmonier\SyliusCommandPalettePlugin\Search\CommandPaletteSearch;

/** GET /admin/command-palette/search?q= */
final readonly class SearchController
{
    public function __construct(
        private CommandPaletteSearch $search,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
        private ?RateLimiterFactory $rateLimiterFactory = null,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // The admin firewall already requires it; checked again in case the route is moved.
        if (!$this->authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')) {
            throw new AccessDeniedException();
        }

        if ($this->rateLimiterFactory instanceof RateLimiterFactory) {
            $key = $this->resolveRateLimitKey($request);
            $limit = $this->rateLimiterFactory->create($key)->consume();

            if (!$limit->isAccepted()) {
                $seconds = max(0, $limit->getRetryAfter()->getTimestamp() - time());

                return new JsonResponse(
                    ['error' => 'too_many_requests'],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    [
                        'Retry-After' => (string) $seconds,
                        'Cache-Control' => 'private, no-store',
                    ],
                );
            }
        }

        $query = $request->query->all()['q'] ?? ''; // never a 400 for "?q[]="
        $query = CommandPaletteSearch::normalize(is_string($query) ? $query : '');

        $response = new JsonResponse([
            'query' => $query,
            'groups' => $this->search->search($query),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /** Returns a rate-limit key scoped to the authenticated admin user, falling back to the client IP. */
    private function resolveRateLimitKey(Request $request): string
    {
        $user = $this->security->getUser();
        if ($user !== null) {
            return 'admin_' . $user->getUserIdentifier();
        }

        return 'ip_' . ($request->getClientIp() ?? 'unknown');
    }
}
