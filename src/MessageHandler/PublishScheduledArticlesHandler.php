<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PublishScheduledArticlesMessage;
use App\Service\Article\ArticleService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PublishScheduledArticlesHandler
{
    public function __construct(private ArticleService $articles)
    {
    }

    public function __invoke(PublishScheduledArticlesMessage $message): void
    {
        $this->articles->publishDueArticles();
    }
}
