<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tmonier\SyliusCommandPalettePlugin\Search\CommandPaletteSearch;

/** GET /admin/command-palette/search?q= */
final class SearchController
{
    public function __construct(
        private readonly CommandPaletteSearch $search,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // The admin firewall already requires it; checked again in case the route is moved.
        if (!$this->authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')) {
            throw new AccessDeniedException();
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
}
