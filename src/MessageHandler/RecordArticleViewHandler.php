<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ArticleView;
use App\Message\RecordArticleViewMessage;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordArticleViewHandler
{
    public function __construct(
        private ArticleRepository $articles,
        private ArticleViewRepository $views,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RecordArticleViewMessage $message): void
    {
        if (null !== $message->userAgent && 1 === preg_match('/bot|crawl|spider|slurp|bingpreview|headless|curl|wget|python-requests/i', $message->userAgent)) {
            return;
        }

        // Eligibility is checked again at processing time, not trusted from the HTTP request.
        $article = $this->articles->findOnePublicById($message->articleId);
        if (null === $article) {
            return;
        }

        $since = new \DateTimeImmutable('-24 hours');
        if ($this->views->hasRecentView($message->articleId, $message->visitorHash, $since)) {
            return;
        }

        $view = (new ArticleView())
            ->setArticle($article)
            ->setVisitorHash($message->visitorHash)
            ->setReferrer($message->referrer);
        $article->incrementViewCount();
        $this->entityManager->persist($view);
        $this->entityManager->flush();
    }
}
