<?php

declare(strict_types=1);

namespace App\Service\Article;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/** Read-only public content queries; every method applies published/visibility rules. */
final readonly class PublicCatalog
{
    public function __construct(
        private ArticleRepository $articles,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private UserRepository $users,
        private ArticleViewRepository $views,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    /** @return array{categories: list<Category>, tags: list<Tag>, authors: list<User>} */
    public function layout(): array
    {
        $categories = $this->categories->findTree();
        $categoryCounts = $this->articles->publishedCountsByCategory();
        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $category->setArticleCount(null !== $categoryId ? ($categoryCounts[$categoryId] ?? 0) : 0);
        }

        $tags = $this->tags->findBy([], ['name' => 'ASC']);
        $tagCounts = $this->articles->publishedCountsByTag();
        foreach ($tags as $tag) {
            $tagId = $tag->getId();
            $tag->setArticleCount(null !== $tagId ? ($tagCounts[$tagId] ?? 0) : 0);
        }

        $authors = $this->users->findBy(['isActive' => true], ['displayName' => 'ASC']);
        $authorCounts = $this->articles->publishedCountsByAuthor();
        foreach ($authors as $author) {
            $authorId = $author->getId();
            $author->setArticleCount(null !== $authorId ? ($authorCounts[$authorId] ?? 0) : 0);
        }

        return ['categories' => $categories, 'tags' => $tags, 'authors' => $authors];
    }

    /** @param array{q?: string, category?: string, tag?: string, author?: string, sort?: string} $filters
     * @return array{items: list<Article>, total: int, pages: int, page: int}
     */
    public function articles(array $filters = [], int $page = 1, int $perPage = 9): array
    {
        $result = $this->articles->paginatePublished($filters, 1, $perPage);
        $page = max(1, min($result['pages'], $page));
        if ($page > 1) {
            $result = $this->articles->paginatePublished($filters, $page, $perPage);
        }

        return $result + ['page' => $page];
    }

    public function article(string $slug): ?Article
    {
        return $this->articles->findOnePublishedBySlug($slug);
    }

    /** @return list<Article> */
    public function related(Article $article, int $limit = 3): array
    {
        return $this->articles->findRelated($article, $limit);
    }

    public function previous(Article $article): ?Article
    {
        return $this->articles->findPreviousPublished($article);
    }

    public function next(Article $article): ?Article
    {
        return $this->articles->findNextPublished($article);
    }

    /** @return array{articles: list<Article>, popular: list<Article>, trending: list<Article>, recommended: list<Article>} */
    public function homepage(): array
    {
        $ids = $this->cache->get('public.homepage.v1', function (ItemInterface $item): array {
            $item->expiresAfter(300);
            $item->tag(['articles', 'homepage']);

            $latest = $this->articles->paginatePublished([], 1, 18)['items'];
            $popularIds = $this->views->topArticleIdsSince(new \DateTimeImmutable('-30 days'), 3);
            if ([] === $popularIds) {
                $popularIds = array_map(static fn (Article $article): ?int => $article->getId(), $this->articles->mostViewedPublished(3));
            }
            $trendingIds = $this->views->topArticleIdsSince(new \DateTimeImmutable('-7 days'), 3);
            if ([] === $trendingIds) {
                $trendingIds = array_map(static fn (Article $article): ?int => $article->getId(), array_slice($latest, 0, 3));
            }
            $recommended = array_map(static fn (Article $article): ?int => $article->getId(), array_slice($latest, 4, 3));

            return [
                'latest' => array_map(static fn (Article $article): int => (int) $article->getId(), $latest),
                'popular' => $popularIds,
                'trending' => $trendingIds,
                'recommended' => $recommended,
            ];
        });

        return [
            'articles' => array_slice($this->articles->findPublishedByIds($ids['latest']), 0, 18),
            'popular' => $this->articles->findPublishedByIds(array_map(intval(...), $ids['popular'])),
            'trending' => $this->articles->findPublishedByIds(array_map(intval(...), $ids['trending'])),
            'recommended' => $this->articles->findPublishedByIds(array_map(intval(...), $ids['recommended'])),
        ];
    }

    /** @return Category|Tag|User|null */
    public function collection(string $kind, string $slug): ?object
    {
        return match ($kind) {
            'author' => $this->users->findOneBySlug($slug),
            'category' => $this->categories->findOneBySlug($slug),
            'tag' => $this->tags->findOneBySlug($slug),
            default => null,
        };
    }
}
