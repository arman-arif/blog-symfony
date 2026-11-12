<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\Article\EngagementService;
use App\Service\Article\PublicCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EngagementController extends AbstractController
{
    #[Route('/blog/{slug}/like', name: 'front_article_like', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function like(string $slug, Request $request, PublicCatalog $catalog, EngagementService $engagement): JsonResponse
    {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();
        $user = $this->currentUser();
        if (null === $user) {
            return $this->loginRequired();
        }
        if (!$this->isCsrfTokenValid('engagement', (string) ($request->headers->get('X-CSRF-Token') ?? $request->request->get('_token')))) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($engagement->toggleLike($article, $user));
    }

    #[Route('/blog/{slug}/bookmark', name: 'front_article_bookmark', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function bookmark(string $slug, Request $request, PublicCatalog $catalog, EngagementService $engagement): JsonResponse
    {
        $article = $catalog->article($slug) ?? throw $this->createNotFoundException();
        $user = $this->currentUser();
        if (null === $user) {
            return $this->loginRequired();
        }
        if (!$this->isCsrfTokenValid('engagement', (string) ($request->headers->get('X-CSRF-Token') ?? $request->request->get('_token')))) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($engagement->toggleBookmark($article, $user));
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function loginRequired(): JsonResponse
    {
        return new JsonResponse(['error' => 'Sign in to save or like stories.', 'loginUrl' => $this->generateUrl('app_login')], Response::HTTP_UNAUTHORIZED);
    }
}
