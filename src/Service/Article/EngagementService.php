<?php

declare(strict_types=1);

namespace App\Service\Article;

use App\Entity\Article;
use App\Entity\ArticleBookmark;
use App\Entity\ArticleLike;
use App\Entity\User;
use App\Repository\ArticleBookmarkRepository;
use App\Repository\ArticleLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class EngagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleLikeRepository $likes,
        private ArticleBookmarkRepository $bookmarks,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    /** @return array{active: bool, count: int} */
    public function toggleLike(Article $article, User $user): array
    {
        $like = $this->likes->findOneFor((int) $article->getId(), (int) $user->getId());
        if (null !== $like) {
            $this->entityManager->remove($like);
            $article->setLikeCount(max(0, $article->getLikeCount() - 1));
            $active = false;
        } else {
            $this->entityManager->persist((new ArticleLike())->setArticle($article)->setUser($user));
            $article->setLikeCount($article->getLikeCount() + 1);
            $active = true;
        }
        $this->entityManager->flush();
        $this->cache->invalidateTags(['analytics']);

        return ['active' => $active, 'count' => $article->getLikeCount()];
    }

    /** @return array{active: bool} */
    public function toggleBookmark(Article $article, User $user): array
    {
        $bookmark = $this->bookmarks->findOneFor((int) $article->getId(), (int) $user->getId());
        if (null !== $bookmark) {
            $this->entityManager->remove($bookmark);
            $active = false;
        } else {
            $this->entityManager->persist((new ArticleBookmark())->setArticle($article)->setUser($user));
            $active = true;
        }
        $this->entityManager->flush();
        $this->cache->invalidateTags(['analytics']);

        return ['active' => $active];
    }

    public function isLiked(Article $article, User $user): bool
    {
        return null !== $this->likes->findOneFor((int) $article->getId(), (int) $user->getId());
    }

    public function isBookmarked(Article $article, User $user): bool
    {
        return null !== $this->bookmarks->findOneFor((int) $article->getId(), (int) $user->getId());
    }
}
