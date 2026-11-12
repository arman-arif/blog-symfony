<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Repository\ArticleBookmarkRepository;
use App\Service\Article\PublicCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SavedController extends AbstractController
{
    #[Route('/saved', name: 'front_saved', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(ArticleBookmarkRepository $bookmarks, PublicCatalog $catalog): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $articles = array_values(array_filter(
            array_map(static fn ($bookmark) => $bookmark->getArticle(), $bookmarks->findForUser((int) $user->getId())),
            static fn ($article): bool => ArticleStatus::Published === $article->getStatus() && null !== $article->getPublishedAt() && $article->getPublishedAt() <= new \DateTimeImmutable(),
        ));

        return $this->render('front/saved/index.html.twig', array_merge($catalog->layout(), ['articles' => $articles]));
    }
}
