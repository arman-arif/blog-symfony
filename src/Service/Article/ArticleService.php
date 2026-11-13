<?php

declare(strict_types=1);

namespace App\Service\Article;

use App\Entity\Article;
use App\Entity\ArticleRevision;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\ArticleRevisionRepository;
use App\Repository\RedirectRepository;
use App\Security\PermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class ArticleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleRepository $articles,
        private ArticleRevisionRepository $revisions,
        private RedirectRepository $redirects,
        private ContentProcessor $processor,
        private SluggerInterface $slugger,
        private PermissionChecker $permissions,
        #[Autowire(service: 'state_machine.article_publishing')]
        private WorkflowInterface $publishingWorkflow,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $publicCache,
    ) {
    }

    public function create(Article $article, User $actor, ArticleStatus $target = ArticleStatus::Draft, ?User $author = null): Article
    {
        $author ??= $actor;
        if (!$this->permissions->can($actor, 'article.create')) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You cannot create articles.');
        }
        $this->assertAuthorAssignable($author, $actor);
        $article->setAuthor($author);
        $article->setStatus(ArticleStatus::Draft);
        $transition = $this->transitionFor($article, $target, $actor);
        $this->prepareContent($article);
        $this->entityManager->wrapInTransaction(function () use ($article, $actor, $target, $transition): void {
            $this->changeStatus($article, $target, $transition);
            $this->entityManager->persist($article);
            $this->recordRevision($article, $actor, false);
        });
        $this->invalidatePublicCache();

        return $article;
    }

    public function update(Article $article, User $editor, ?ArticleStatus $target = null, bool $recordRevision = true, ?string $previousSlug = null, ?Article $details = null): Article
    {
        $details ??= $article;
        $candidate = clone $details;
        $candidate->setStatus($article->getStatus());
        $target ??= $article->getStatus();
        $previousSlug ??= $article->getSlug();
        try {
            $this->assertAuthorAssignable($candidate->getAuthor(), $editor);
            $transition = $this->transitionFor($candidate, $target, $editor);
            $this->prepareContent($candidate);
        } catch (\DomainException|\Symfony\Component\Security\Core\Exception\AccessDeniedException $exception) {
            // Legacy callers may have edited the managed object directly.
            if ($details === $article && $this->entityManager->contains($article)) {
                $this->entityManager->refresh($article);
            }
            throw $exception;
        }
        $this->entityManager->wrapInTransaction(function () use ($article, $candidate, $editor, $target, $transition, $recordRevision, $previousSlug): void {
            $this->copyEditableFields($candidate, $article);
            $this->changeStatus($article, $target, $transition);
            if ($recordRevision) {
                $this->recordRevision($article, $editor, false);
            }
            if ('' !== $previousSlug && $previousSlug !== $article->getSlug()) {
                $this->createSlugRedirect($previousSlug, $article->getSlug());
            }
        });
        $this->invalidatePublicCache();

        return $article;
    }

    public function applyStatus(Article $article, ArticleStatus $target, ?User $actor = null): void
    {
        $transition = $this->transitionFor($article, $target, $actor);
        if (null === $transition) {
            return;
        }
        $this->entityManager->wrapInTransaction(function () use ($article, $target, $transition, $actor): void {
            $this->changeStatus($article, $target, $transition);
            if (null !== $actor) {
                $this->recordRevision($article, $actor, false);
            }
        });
        $this->invalidatePublicCache();
    }

    private function transitionFor(Article $article, ArticleStatus $target, ?User $actor): ?string
    {
        if ($article->getStatus() === $target) {
            if (ArticleStatus::Scheduled === $target && null === $article->getScheduledAt()) {
                throw new \DomainException('A scheduled article needs a publication time.');
            }

            return null;
        }

        $transition = null;
        foreach ($this->publishingWorkflow->getEnabledTransitions($article) as $candidate) {
            if (in_array($target->value, $candidate->getTos(), true)) {
                $transition = $candidate;
                break;
            }
        }

        if (null === $transition) {
            throw new \DomainException(sprintf('Cannot move an article from %s to %s.', $article->getStatus()->value, $target->value));
        }

        $requiredPermission = match ($transition->getName()) {
            'submit' => 'article.submit',
            'return_to_draft' => 'article.review',
            'publish' => 'article.publish',
            'schedule' => 'article.schedule',
            'unpublish' => 'article.publish',
            'revise' => 'article.edit.own',
            default => 'article.edit.own',
        };

        if (null !== $actor && !$this->permissions->can($actor, $requiredPermission)) {
            $action = match ($requiredPermission) {
                'article.publish' => 'publish or unpublish',
                'article.schedule' => 'schedule',
                'article.submit' => 'submit',
                default => 'perform this transition on',
            };
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You cannot '.$action.' articles.');
        }

        if (ArticleStatus::Scheduled === $target && null === $article->getScheduledAt()) {
            throw new \DomainException('A scheduled article needs a publication time.');
        }

        return $transition->getName();
    }

    private function changeStatus(Article $article, ArticleStatus $target, ?string $transition): void
    {
        if (null === $transition) {
            return;
        }
        $this->publishingWorkflow->apply($article, $transition);

        if (ArticleStatus::Published === $target && null === $article->getPublishedAt()) {
            $article->setPublishedAt(new \DateTimeImmutable());
        }
        if (ArticleStatus::Published === $target) {
            $article->setScheduledAt(null);
        }
    }

    public function delete(Article $article): void
    {
        $this->entityManager->remove($article);
        $this->entityManager->flush();
        $this->invalidatePublicCache();
    }

    public function restore(Article $article): void
    {
        $article->setDeletedAt(null);
        $this->entityManager->flush();
        $this->invalidatePublicCache();
    }

    public function duplicate(Article $article, User $author): Article
    {
        $copy = (new Article())
            ->setTitle($article->getTitle().' (copy)')
            ->setSubtitle($article->getSubtitle())
            ->setExcerpt($article->getExcerpt())
            ->setContent($article->getContent())
            ->setStatus(ArticleStatus::Draft)
            ->setVisibility($article->getVisibility())
            ->setAuthor($author)
            ->setFeaturedImage($article->getFeaturedImage())
            ->setAllowComments($article->isAllowComments())
            ->setIsSponsored($article->isSponsored())
            ->setAffiliateLinks($article->hasAffiliateLinks())
            ->setIsPremium($article->isPremium())
            ->setReadingTime($article->getReadingTime());
        foreach ($article->getCategories() as $category) {
            $copy->addCategory($category);
        }
        foreach ($article->getTags() as $tag) {
            $copy->addTag($tag);
        }
        $copy->getSeo()
            ->setTitle($article->getSeo()->getTitle())
            ->setDescription($article->getSeo()->getDescription())
            ->setFocusKeyword($article->getSeo()->getFocusKeyword())
            ->setCanonicalUrl($article->getSeo()->getCanonicalUrl())
            ->setNoindex($article->getSeo()->getNoindex())
            ->setOgTitle($article->getSeo()->getOgTitle())
            ->setOgDescription($article->getSeo()->getOgDescription())
            ->setOgImageUrl($article->getSeo()->getOgImageUrl());
        $copy->setSlug($this->uniqueSlug($copy->getTitle()));

        return $this->create($copy, $author);
    }

    public function restoreRevision(Article $article, ArticleRevision $revision, User $editor): void
    {
        if ($revision->getArticle()->getId() !== $article->getId()) {
            throw new \DomainException('This revision belongs to another article.');
        }
        $details = clone $article;
        $details->setTitle($revision->getTitle())->setContent($revision->getContent());
        $this->update($article, $editor, details: $details);
    }

    public function autosave(Article $article, string $title, string $content, User $editor): ArticleRevision
    {
        $revision = $this->recordRevisionValues($article, $title, $this->processor->sanitize($content, $article->hasAffiliateLinks()), $editor, true);
        $this->entityManager->wrapInTransaction(function () use ($article, $revision): void {
            // Keep nine existing snapshots plus this one, with one atomic flush.
            $this->revisions->pruneAutosaves($article, 9);
            $this->entityManager->persist($revision);
        });

        return $revision;
    }

    public function publishDueArticles(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $published = 0;
        foreach ($this->articles->findScheduledDue($now) as $article) {
            $transition = $this->transitionFor($article, ArticleStatus::Published, null);
            $this->entityManager->wrapInTransaction(function () use ($article, $now, $transition): void {
                $article->setPublishedAt($now);
                $this->changeStatus($article, ArticleStatus::Published, $transition);
            });
            $this->invalidatePublicCache();
            ++$published;
        }

        return $published;
    }

    public function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = strtolower((string) $this->slugger->slug($source));
        $base = '' === $base ? 'story' : $base;
        $slug = $base;
        $suffix = 2;
        while ($this->articles->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function invalidatePublicCache(): void
    {
        $this->publicCache->invalidateTags(['articles', 'homepage', 'sitemap', 'redirects', 'analytics']);
    }

    private function createSlugRedirect(string $oldSlug, string $newSlug): void
    {
        $source = '/blog/'.$oldSlug;
        $target = '/blog/'.$newSlug;
        if ($source === $target) {
            return;
        }

        $redirect = $this->redirects->findOneBySourcePath($source) ?? new \App\Entity\Redirect();
        $redirect->setSourcePath($source)->setTargetUrl($target)->setStatusCode(301);
        $this->entityManager->persist($redirect);
    }

    private function assertAuthorAssignable(User $author, User $actor): void
    {
        if ($author->getId() !== $actor->getId() && !$this->permissions->can($actor, 'article.edit.any')) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You cannot assign this article to another author.');
        }
    }

    private function copyEditableFields(Article $source, Article $target): void
    {
        $target->setTitle($source->getTitle())->setSubtitle($source->getSubtitle())
            ->setSlug($source->getSlug())->setExcerpt($source->getExcerpt())->setContent($source->getContent())
            ->setVisibility($source->getVisibility())->setScheduledAt($source->getScheduledAt())
            ->setAuthor($source->getAuthor())->setFeaturedImage($source->getFeaturedImage())
            ->setAllowComments($source->isAllowComments())->setIsSponsored($source->isSponsored())
            ->setAffiliateLinks($source->hasAffiliateLinks())->setReadingTime($source->getReadingTime())
            ->setSeo(clone $source->getSeo());
        foreach ($target->getCategories()->toArray() as $category) {
            if (!$source->getCategories()->contains($category)) {
                $target->removeCategory($category);
            }
        }
        foreach ($source->getCategories() as $category) {
            $target->addCategory($category);
        }
        foreach ($target->getTags()->toArray() as $tag) {
            if (!$source->getTags()->contains($tag)) {
                $target->removeTag($tag);
            }
        }
        foreach ($source->getTags() as $tag) {
            $target->addTag($tag);
        }
    }

    private function prepareContent(Article $article): void
    {
        $content = $this->processor->sanitize($article->getContent(), $article->hasAffiliateLinks());
        $article->setContent($content);
        $article->setReadingTime($this->processor->readingTime($content));

        if (null === $article->getExcerpt() || '' === trim($article->getExcerpt())) {
            $article->setExcerpt($this->processor->excerpt($content));
        }

        $slug = trim($article->getSlug());
        if ('' === $slug) {
            $slug = $this->uniqueSlug($article->getTitle(), $article->getId());
        } elseif ($this->articles->slugExists($slug, $article->getId())) {
            $slug = $this->uniqueSlug($slug, $article->getId());
        }
        $article->setSlug($slug);
    }

    private function recordRevision(Article $article, ?User $actor, bool $autosave): void
    {
        $revision = $this->recordRevisionValues($article, $article->getTitle(), $article->getContent(), $actor, $autosave);
        $this->entityManager->persist($revision);
    }

    private function recordRevisionValues(Article $article, string $title, string $content, ?User $actor, bool $autosave): ArticleRevision
    {
        return (new ArticleRevision())
            ->setArticle($article)
            ->setTitle($title)
            ->setContent($content)
            ->setCreatedBy($actor)
            ->setIsAutosave($autosave);
    }
}
