<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\Enum\ArticleStatus;
use App\Enum\NewsletterSubscriberStatus;
use App\Repository\ActivityLogRepository;
use App\Repository\ArticleBookmarkRepository;
use App\Repository\ArticleDailyStatRepository;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class AnalyticsService
{
    public function __construct(
        private ArticleDailyStatRepository $stats,
        private ArticleRepository $articles,
        private CommentRepository $comments,
        private ArticleBookmarkRepository $bookmarks,
        private NewsletterSubscriberRepository $subscribers,
        private UserRepository $users,
        private ActivityLogRepository $activity,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(?int $authorId = null): array
    {
        $today = new \DateTimeImmutable('today');
        $from = $today->modify('-29 days');
        $weeklyFrom = $today->modify('-6 days');
        $daily = $this->stats->dailyTotals($weeklyFrom, $today, $authorId);
        $weekly = [];
        for ($day = $weeklyFrom; $day <= $today; $day = $day->modify('+1 day')) {
            $weekly[$day->format('Y-m-d')] = $daily[$day->format('Y-m-d')] ?? 0;
        }

        $categories = $this->stats->popularCategories($from, $today, 6, $authorId);
        $categoryTotal = max(1, array_sum(array_column($categories, 'views')));
        $totalViews = $this->stats->sumViews($from, $today, $authorId);
        $published = $this->articles->countByStatus(ArticleStatus::Published, $authorId);
        $commentTotal = $this->comments->countForAuthor($authorId);

        return [
            'stats' => [
                ['label' => 'Article views', 'value' => $totalViews, 'icon' => 'chart', 'note' => 'Last 30 days from daily rollups'],
                ['label' => 'Published stories', 'value' => $published, 'icon' => 'edit', 'note' => 'Live on the public site'],
                ['label' => 'Editorial team', 'value' => null === $authorId ? $this->users->count(['isActive' => true]) : 1, 'icon' => 'user', 'note' => 'Active accounts'],
                ['label' => 'Reader conversations', 'value' => $commentTotal, 'icon' => 'mail', 'note' => $this->comments->countByStatus(\App\Enum\CommentStatus::Pending, $authorId).' awaiting review'],
            ],
            'statuses' => [
                'draft' => $this->articles->countByStatus(ArticleStatus::Draft, $authorId),
                'pending_review' => $this->articles->countByStatus(ArticleStatus::PendingReview, $authorId),
                'scheduled' => $this->articles->countByStatus(ArticleStatus::Scheduled, $authorId),
                'published' => $published,
            ],
            'recentArticles' => $this->articles->findRecentForDashboard(5, $authorId),
            'comments' => $this->comments->findRecent(5, $authorId),
            'activity' => null === $authorId ? $this->activity->findRecent(5) : [],
            'views' => ['labels' => array_keys($weekly), 'values' => array_values($weekly)],
            'categories' => [
                'labels' => array_map(static fn (array $category): string => $category['name'], $categories),
                'values' => array_map(static fn (array $category): float => round($category['views'] / $categoryTotal * 100, 1), $categories),
            ],
            'subscribers' => null === $authorId ? $this->subscribers->countByStatus(NewsletterSubscriberStatus::Confirmed) : 0,
        ];
    }

    /** @return array<string, mixed> */
    public function report(\DateTimeImmutable $from, \DateTimeImmutable $to, ?int $authorId = null): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return $this->cache->get('analytics.report.'.($authorId ?? 'all').'.'.$from->format('Ymd').'.'.$to->format('Ymd'), function (ItemInterface $item) use ($from, $to, $authorId): array {
            $item->expiresAfter(600);
            $item->tag(['analytics']);

            $dailyTotals = $this->stats->dailyTotals($from, $to, $authorId);
            $labels = [];
            $values = [];
            for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
                $labels[] = $day->format('Y-m-d');
                $values[] = $dailyTotals[$day->format('Y-m-d')] ?? 0;
            }

            $monthly = [];
            foreach ($labels as $index => $label) {
                $month = substr($label, 0, 7);
                $monthly[$month] = ($monthly[$month] ?? 0) + $values[$index];
            }

            $topRows = $this->stats->topArticles($from, $to, 10, $authorId);
            $ids = array_column($topRows, 'articleId');
            $articles = [];
            foreach ($this->articles->findByIdsForAnalytics($ids) as $article) {
                $articleId = $article->getId();
                if (null !== $articleId) {
                    $articles[$articleId] = $article;
                }
            }
            $commentCounts = $this->comments->countsApprovedByArticleIds($ids);
            $bookmarkCounts = $this->bookmarks->countsByArticleIds($ids);
            $topArticles = [];
            foreach ($topRows as $row) {
                $article = $articles[$row['articleId']] ?? null;
                if (null === $article) {
                    continue;
                }
                $comments = $commentCounts[$row['articleId']] ?? 0;
                $bookmarkCount = $bookmarkCounts[$row['articleId']] ?? 0;
                $engagement = $article->getLikeCount() + $comments + $bookmarkCount;
                $topArticles[] = [
                    'id' => $article->getId(), 'title' => $article->getTitle(), 'slug' => $article->getSlug(),
                    'views' => $row['views'], 'likes' => $article->getLikeCount(), 'comments' => $comments,
                    'bookmarks' => $bookmarkCount, 'engagement' => $engagement,
                    'engagement_rate' => $row['views'] > 0 ? round($engagement / $row['views'] * 100, 1) : 0.0,
                ];
            }

            $categoryRows = $this->stats->popularCategories($from, $to, 6, $authorId);
            $categoryTotal = max(1, array_sum(array_column($categoryRows, 'views')));
            $tagRows = $this->stats->popularTags($from, $to, 6, $authorId);
            $tagTotal = max(1, array_sum(array_column($tagRows, 'views')));
            foreach ($categoryRows as &$category) {
                $category['share'] = round($category['views'] / $categoryTotal * 100, 1);
            }
            unset($category);
            foreach ($tagRows as &$tag) {
                $tag['share'] = round($tag['views'] / $tagTotal * 100, 1);
            }
            unset($tag);

            $totalViews = array_sum($values);
            $engagement = $this->articles->sumLikeCount($authorId) + $this->comments->countByStatus(\App\Enum\CommentStatus::Approved, $authorId) + $this->bookmarks->countForAuthor($authorId);

            return [
                'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'days' => count($labels),
                'daily' => ['labels' => $labels, 'values' => $values],
                'monthly' => ['labels' => array_keys($monthly), 'values' => array_values($monthly)],
                'articles' => $topArticles, 'categories' => $categoryRows, 'tags' => $tagRows,
                'authors' => $this->stats->authorPerformance($from, $to, 6, $authorId),
                'total_views' => $totalViews,
                'engagement' => $engagement,
                'engagement_rate' => $totalViews > 0 ? round($engagement / $totalViews * 100, 2) : 0.0,
                'published' => $this->articles->countByStatus(ArticleStatus::Published, $authorId),
                'subscribers' => null === $authorId ? $this->subscribers->countByStatus(NewsletterSubscriberStatus::Confirmed) : 0,
            ];
        });
    }
}
