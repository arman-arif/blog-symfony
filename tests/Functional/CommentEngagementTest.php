<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Enum\CommentStatus;
use App\Message\RecordArticleViewMessage;
use App\MessageHandler\RecordArticleViewHandler;
use App\Repository\ArticleRepository;
use App\Repository\ArticleViewRepository;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use App\Tests\Support\InMemorySettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class CommentEngagementTest extends FrontTestCase
{
    private const string SLUG = 'technology-that-gives-us-our-time-back';

    protected function setUp(): void
    {
        parent::setUp();
        InMemorySettingsService::resetOverride();
    }

    protected function tearDown(): void
    {
        InMemorySettingsService::resetOverride();
        parent::tearDown();
    }

    public function testGuestCommentIsPendingAndNotifiesTheAuthor(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', '10.10.'.random_int(0, 250).'.'.random_int(1, 250));

        $this->submitComment($client, 'A thoughtful guest perspective.', 'Guest Reader', 'guest@example.test');

        self::assertResponseStatusCodeSame(302);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertStringContainsString('awaits moderation', (string) $email->getSubject());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'awaits moderation');

        $comment = self::getContainer()->get(CommentRepository::class)->findOneBy(['guestName' => 'Guest Reader'], ['id' => 'DESC']);
        self::assertNotNull($comment);
        self::assertSame(CommentStatus::Pending, $comment->getStatus());
        self::assertSame(self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => self::SLUG])?->getId(), $comment->getArticle()->getId());
    }

    public function testHoneypotMinimumTimeLinksAndBlockedWordsAreRejected(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', '10.10.'.random_int(0, 250).'.'.random_int(1, 250));
        $repository = self::getContainer()->get(CommentRepository::class);
        $before = (int) $repository->createQueryBuilder('c')->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $this->submitComment($client, 'A normal-looking comment.', 'Bot', 'bot@example.test', ['comment_form[website]' => 'https://spam.example']);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'could not be submitted');

        $this->submitComment($client, 'A normal-looking comment.', 'Fast', 'fast@example.test', ['comment_form[startedAt]' => (string) time()]);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'take a moment');

        $this->submitComment($client, 'Read this https://a.example and https://b.example and https://c.example', 'Linky', 'linky@example.test');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'keep links');

        InMemorySettingsService::setOverride('comments.blocked_words', ['forbidden']);
        $this->submitComment($client, 'This contains a forbidden phrase.', 'Blocked', 'blocked@example.test');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'not allowed');

        self::assertSame($before, (int) $repository->createQueryBuilder('c')->select('COUNT(c.id)')->getQuery()->getSingleScalarResult());
    }

    public function testCommentRateLimitRejectsBursts(): void
    {
        $client = self::createClient();
        $this->seed();
        $ip = '10.10.'.random_int(0, 250).'.'.random_int(1, 250);
        $client->setServerParameter('REMOTE_ADDR', $ip);

        $limiter = self::getContainer()->get('limiter.comment_submit');
        self::assertInstanceOf(RateLimiterFactory::class, $limiter);
        for ($i = 0; $i < 5; ++$i) {
            self::assertTrue($limiter->create($ip)->consume()->isAccepted());
        }

        $this->submitComment($client, 'One comment too many.', 'Burst', 'burst@example.test');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Too many comments');
    }

    public function testLikesAndBookmarksRequireAuthenticationAndToggleIdempotently(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => self::SLUG]);
        self::assertNotNull($article);

        $client->request('POST', '/blog/'.self::SLUG.'/like');
        self::assertResponseStatusCodeSame(401);

        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($user);
        $client->loginUser($user);
        $crawler = $client->request('GET', '/blog/'.self::SLUG);
        $token = (string) $crawler->filter('[data-engagement="like"]')->attr('data-engagement-token');
        $originalCount = $article->getLikeCount();

        $client->request('POST', '/blog/'.self::SLUG.'/like', [], [], ['HTTP_X-CSRF-Token' => $token]);
        self::assertResponseIsSuccessful();
        self::assertTrue((bool) json_decode((string) $client->getResponse()->getContent(), true)['active']);

        $client->request('POST', '/blog/'.self::SLUG.'/like', [], [], ['HTTP_X-CSRF-Token' => $token]);
        self::assertFalse((bool) json_decode((string) $client->getResponse()->getContent(), true)['active']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame($originalCount, self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => self::SLUG])?->getLikeCount());

        $crawler = $client->request('GET', '/blog/'.self::SLUG);
        $bookmarkToken = (string) $crawler->filter('[data-engagement="bookmark"]')->attr('data-engagement-token');
        $client->request('POST', '/blog/'.self::SLUG.'/bookmark', [], [], ['HTTP_X-CSRF-Token' => $bookmarkToken]);
        self::assertTrue((bool) json_decode((string) $client->getResponse()->getContent(), true)['active']);

        $client->request('GET', '/saved');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Technology that gives us our time back');

        $client->request('POST', '/blog/'.self::SLUG.'/bookmark', [], [], ['HTTP_X-CSRF-Token' => $bookmarkToken]);
        $client->request('GET', '/saved');
        self::assertSelectorTextContains('main', 'Nothing saved yet');
    }

    public function testViewTrackingDeduplicatesAndIgnoresBots(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => self::SLUG]);
        self::assertNotNull($article);
        $before = $article->getViewCount();
        $handler = self::getContainer()->get(RecordArticleViewHandler::class);

        $handler(new RecordArticleViewMessage((int) $article->getId(), 'visitor-hash', null, 'Mozilla/5.0'));
        $handler(new RecordArticleViewMessage((int) $article->getId(), 'visitor-hash', null, 'Mozilla/5.0'));
        $handler(new RecordArticleViewMessage((int) $article->getId(), 'other-visitor', null, 'Googlebot/2.1'));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame($before + 1, self::getContainer()->get(ArticleRepository::class)->find($article->getId())?->getViewCount());
        self::assertSame(1, (int) self::getContainer()->get(ArticleViewRepository::class)->createQueryBuilder('v')->select('COUNT(v.id)')->getQuery()->getSingleScalarResult());
    }

    public function testReportingAnApprovedCommentIncrementsTheFlag(): void
    {
        $client = self::createClient();
        $this->seed();
        $client->setServerParameter('REMOTE_ADDR', '10.10.'.random_int(0, 250).'.'.random_int(1, 250));
        $comment = self::getContainer()->get(CommentRepository::class)->findOneBy(['article' => self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => self::SLUG]), 'status' => CommentStatus::Approved]);
        self::assertNotNull($comment);

        $crawler = $client->request('GET', '/blog/'.self::SLUG);
        $form = $crawler->filter('form[action$="/comments/'.$comment->getId().'/report"]')->form();
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame(1, self::getContainer()->get(CommentRepository::class)->find($comment->getId())?->getReportCount());
    }

    public function testModerationApprovesPendingCommentsAndShowsThemPublicly(): void
    {
        $client = self::createClient();
        $this->seed();
        $article = self::getContainer()->get(ArticleRepository::class)->findOneBy(['slug' => self::SLUG]);
        self::assertNotNull($article);
        $comment = (new Comment())
            ->setArticle($article)
            ->setGuestName('Waiting Reader')
            ->setGuestEmail('waiting@example.test')
            ->setContent('A comment waiting for a moderator.')
            ->setStatus(CommentStatus::Pending);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($comment);
        $em->flush();

        $admin = self::getContainer()->get(UserRepository::class)->findOneByEmail('writer1@example.test');
        self::assertNotNull($admin);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/comments?status=pending');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'A comment waiting for a moderator.');
        $form = $crawler->filter('form[action$="/admin/comments/'.$comment->getId().'/approved"]')->form();
        $client->submit($form);
        self::assertResponseStatusCodeSame(302);

        $em->clear();
        self::assertSame(CommentStatus::Approved, self::getContainer()->get(CommentRepository::class)->find($comment->getId())?->getStatus());
        $client->request('GET', '/blog/'.self::SLUG);
        self::assertSelectorTextContains('main', 'A comment waiting for a moderator.');
    }

    public function testGlobalCommentSettingDisablesTheForm(): void
    {
        $client = self::createClient();
        $this->seed();
        InMemorySettingsService::setOverride('comments.enabled', false);

        $client->request('GET', '/blog/'.self::SLUG);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-comment-form]');
        self::assertSelectorTextContains('main', 'Comments are closed');
    }

    public function testSavedPageRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request('GET', '/saved');
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    /** @param array<string, string> $overrides */
    private function submitComment(object $client, string $content, string $name, string $email, array $overrides = []): void
    {
        $crawler = $client->request('GET', '/blog/'.self::SLUG);
        $form = $crawler->selectButton('Post comment')->form();
        $form['comment_form[content]'] = $content;
        $form['comment_form[guestName]'] = $name;
        $form['comment_form[guestEmail]'] = $email;
        $form['comment_form[startedAt]'] = (string) (time() - 5);
        $form['comment_form[website]'] = '';
        foreach ($overrides as $field => $value) {
            $form[$field] = $value;
        }
        $client->submit($form);
    }
}
