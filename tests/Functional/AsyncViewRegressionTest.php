<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Enum\ArticleVisibility;
use App\Message\RecordArticleViewMessage;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Tests\Factory\ArticleFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class AsyncViewRegressionTest extends FrontTestCase
{
    public function testHttpViewQueuesUntilTheRealWorkerConsumesIt(): void
    {
        $client = self::createClient();
        $article = $this->story();
        $id = $article->getId();
        $client->request('GET', '/blog/'.$article->getSlug(), server: ['HTTP_USER_AGENT' => 'Mozilla/5.0']);
        self::assertResponseIsSuccessful();

        $transport = $this->transport();
        self::assertCount(1, $transport->getSent());
        $envelope = $transport->getSent()[0];
        self::assertInstanceOf(RecordArticleViewMessage::class, $envelope->getMessage());
        self::assertNull($envelope->last(HandledStamp::class));
        self::assertSame(0, $this->viewCount());
        self::assertSame(0, self::getContainer()->get(ArticleRepository::class)->find($id)?->getViewCount());

        $this->consume();
        self::assertCount(1, $transport->getAcknowledged());
        self::assertSame(1, $this->viewCount());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(1, self::getContainer()->get(ArticleRepository::class)->find($id)?->getViewCount());
    }

    /** @return iterable<string, array{string}> */
    public static function hiddenKinds(): iterable
    {
        foreach (['private', 'premium', 'draft', 'future', 'deleted'] as $kind) {
            yield $kind => [$kind];
        }
    }

    #[DataProvider('hiddenKinds')]
    public function testWorkerRechecksEligibilityAfterTheMessageWasQueued(string $kind): void
    {
        self::createClient();
        $article = $this->story();
        $id = $article->getId();
        $this->dispatch(new RecordArticleViewMessage($id, 'queued-visitor', userAgent: 'Mozilla/5.0'));
        match ($kind) {
            'private' => $article->setVisibility(ArticleVisibility::Private),
            'premium' => $article->setIsPremium(true),
            'draft' => $article->setStatus(ArticleStatus::Draft),
            'future' => $article->setPublishedAt(new \DateTimeImmutable('+1 day')),
            'deleted' => $article->setDeletedAt(new \DateTimeImmutable()),
            default => throw new \InvalidArgumentException('Unknown hidden article case: '.$kind),
        };
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $em->clear();

        $transport = $this->transport();
        $this->consume();
        self::assertCount(1, $transport->getAcknowledged());
        self::assertSame(0, $this->viewCount());
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT view_count FROM article WHERE id = ?', [$id]));
    }

    public function testQueuedDuplicateAndBotMessagesAreAcknowledgedWithoutDoubleCounting(): void
    {
        self::createClient();
        $article = $this->story();
        $message = new RecordArticleViewMessage($article->getId(), 'same-visitor', userAgent: 'Mozilla/5.0');
        $this->dispatch($message);
        $this->dispatch($message);
        $this->dispatch(new RecordArticleViewMessage($article->getId(), 'bot-visitor', userAgent: 'Googlebot/2.1'));
        self::assertSame(0, $this->viewCount());
        $transport = $this->transport();
        $this->consume(3);
        self::assertCount(3, $transport->getAcknowledged());
        self::assertSame(1, $this->viewCount());
    }

    private function dispatch(RecordArticleViewMessage $message): Envelope
    {
        return self::getContainer()->get(MessageBusInterface::class)->dispatch($message);
    }

    private function consume(int $limit = 1): void
    {
        // Fetch the registered command without booting the already-active HTTP kernel again.
        $command = self::getContainer()->get('console.command.messenger_consume_messages');
        self::assertInstanceOf(Command::class, $command);
        $tester = new CommandTester($command);
        // Resetting the in-memory test transport erases its queue/receipt history after each message.
        // Keep that history; production routing, middleware and handlers are unchanged.
        self::assertSame(0, $tester->execute(['receivers' => ['async'], '--limit' => $limit, '--time-limit' => 5, '--no-reset' => null], ['interactive' => false]));
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function viewCount(): int
    {
        return self::getContainer()->get(ArticleViewRepository::class)->count([]);
    }

    private function story(): Article
    {
        return ArticleFactory::createOne(['status' => ArticleStatus::Published, 'publishedAt' => new \DateTimeImmutable('-1 day')]);
    }
}
