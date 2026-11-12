<?php

declare(strict_types=1);

namespace App\Service\Comment;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\User;
use App\Enum\CommentStatus;
use App\Repository\CommentRepository;
use App\Security\PermissionChecker;
use App\Service\Mail\TransactionalMailer;
use App\Service\Settings\SettingsServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class CommentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentRepository $comments,
        private SettingsServiceInterface $settings,
        private PermissionChecker $permissions,
        private SpamCheckerInterface $spamChecker,
        private TransactionalMailer $mailer,
        private LoggerInterface $logger,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function submit(Article $article, CommentSubmission $submission, ?User $user, string $ip, ?string $userAgent): Comment
    {
        if (!$this->settings->getBool('comments.enabled') || !$article->isAllowComments()) {
            throw new \DomainException('Comments are closed on this story.');
        }
        if (null === $user && !$this->settings->getBool('comments.anonymous')) {
            throw new \DomainException('Sign in to join the conversation.');
        }
        if ('' !== trim((string) $submission->website)) {
            throw new \DomainException('The comment could not be submitted.');
        }

        $minimum = $this->settings->getInt('comments.min_seconds');
        $elapsed = time() - $submission->startedAt;
        if ($submission->startedAt <= 0 || $elapsed < $minimum || $elapsed > 3600) {
            throw new \DomainException('Please take a moment to read the story before commenting.');
        }

        $content = trim($submission->content);
        $maxLinks = $this->settings->getInt('comments.max_links');
        if (preg_match_all('/<a\s|https?:\/\//i', $content) > $maxLinks) {
            throw new \DomainException('Please keep links in comments to a minimum.');
        }
        foreach ($this->settings->getArray('comments.blocked_words') as $word) {
            if (is_string($word) && '' !== $word && false !== mb_stripos($content, $word)) {
                throw new \DomainException('This comment contains language that is not allowed.');
            }
        }
        if (null !== ($reason = $this->spamChecker->check($content, $ip))) {
            throw new \DomainException($reason);
        }

        if (null !== $submission->parentId) {
            $parent = $this->comments->find($submission->parentId);
            if (null === $parent || $parent->getArticle()->getId() !== $article->getId() || CommentStatus::Approved !== $parent->getStatus()) {
                throw new \DomainException('That reply target is no longer available.');
            }
            if ($this->depth($parent) >= $this->settings->getInt('comments.max_depth')) {
                throw new \DomainException('This conversation has reached its reply limit.');
            }
        }

        $trusted = null !== $user && (
            $this->permissions->can($user, 'comment.moderate')
            || $article->getAuthor()->getId() === $user->getId()
        );

        $comment = (new Comment())
            ->setArticle($article)
            ->setParent(isset($parent) ? $parent : null)
            ->setContent($content)
            ->setStatus($this->settings->getBool('comments.auto_approve_trusted') && $trusted ? CommentStatus::Approved : CommentStatus::Pending)
            ->setIpHash(hash('sha256', $ip))
            ->setUserAgent($userAgent);

        if (null !== $user) {
            $comment->setUser($user);
        } else {
            $comment->setGuestName(trim((string) $submission->guestName));
            $comment->setGuestEmail(trim((string) $submission->guestEmail));
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['analytics']);

        if (CommentStatus::Pending === $comment->getStatus()) {
            $this->notifyAuthor($article, $comment);
        }

        return $comment;
    }

    public function moderate(Comment $comment, CommentStatus $status): void
    {
        $comment->setStatus($status);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['analytics']);
    }

    public function report(Comment $comment): void
    {
        $comment->setReportCount($comment->getReportCount() + 1);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['analytics']);
    }

    public function delete(Comment $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['analytics']);
    }

    public function approvedCount(Article $article): int
    {
        return $this->comments->countApprovedForArticle($article);
    }

    /** @return list<array{comment: Comment, replies: list<mixed>}> */
    public function threads(Article $article): array
    {
        $byParent = [];
        foreach ($this->comments->findApprovedFlat($article) as $comment) {
            $byParent[$comment->getParent()?->getId() ?? 0][] = $comment;
        }

        $build = function (Comment $comment) use (&$build, $byParent): array {
            return [
                'comment' => $comment,
                'replies' => array_map($build, $byParent[$comment->getId()] ?? []),
            ];
        };

        return array_map($build, $byParent[0] ?? []);
    }

    private function depth(Comment $comment): int
    {
        $depth = 0;
        while (null !== $comment->getParent()) {
            ++$depth;
            $comment = $comment->getParent();
        }

        return $depth;
    }

    private function notifyAuthor(Article $article, Comment $comment): void
    {
        $author = $article->getAuthor();
        if ($author->getEmail() === $comment->getUser()?->getEmail()) {
            return;
        }

        try {
            $this->mailer->send($author->getEmail(), 'A comment awaits moderation on “'.$article->getTitle().'”', 'emails/new_comment.html.twig', [
                'article' => $article,
                'comment' => $comment,
                'author' => $author,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Comment notification could not be sent.', ['exception' => $exception, 'comment' => $comment->getId()]);
        }
    }
}
