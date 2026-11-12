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

    public function create(Article $article, User $author, ArticleStatus $target = ArticleStatus::Draft): Article
    {
        $article->setAuthor($author);
        $this->prepareContent($article);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        if (ArticleStatus::Draft !== $target) {
            $this->applyStatus($article, $target, $author);
        }
        $this->invalidatePublicCache();

        return $article;
    }

    public function update(Article $article, User $editor, ?ArticleStatus $target = null, bool $recordRevision = true, ?string $previousSlug = null): Article
    {
        $this->prepareContent($article);
        $this->entityManager->flush();

        if (null !== $target) {
            $this->applyStatus($article, $target, $editor);
        }

        if ($recordRevision) {
            $this->recordRevision($article, $editor, false);
            $this->entityManager->flush();
        }
        if (null !== $previousSlug && '' !== $previousSlug && $previousSlug !== $article->getSlug()) {
            $this->createSlugRedirect($previousSlug, $article->getSlug());
        }
        $this->invalidatePublicCache();

        return $article;
    }

    public function applyStatus(Article $article, ArticleStatus $target, ?User $actor = null): void
    {
        if ($article->getStatus() === $target) {
            return;
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
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You cannot perform this publishing transition.');
        }

        if (ArticleStatus::Scheduled === $target && null === $article->getScheduledAt()) {
            throw new \DomainException('A scheduled article needs a publication time.');
        }

        $this->publishingWorkflow->apply($article, $transition->getName());

        if (ArticleStatus::Published === $target && null === $article->getPublishedAt()) {
            $article->setPublishedAt(new \DateTimeImmutable());
        }
        if (ArticleStatus::Published === $target) {
            $article->setScheduledAt(null);
        }

        $this->entityManager->flush();
        $this->invalidatePublicCache();
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

        $this->entityManager->persist($copy);
        $this->entityManager->flush();
        $this->invalidatePublicCache();

        return $copy;
    }

    public function restoreRevision(Article $article, ArticleRevision $revision, User $editor): void
    {
        $article->setTitle($revision->getTitle());
        $article->setContent($revision->getContent());
        $this->prepareContent($article);
        $this->entityManager->flush();
        $this->recordRevision($article, $editor, false);
        $this->entityManager->flush();
        $this->invalidatePublicCache();
    }

    public function autosave(Article $article, string $title, string $content, User $editor): ArticleRevision
    {
        $revision = $this->recordRevisionValues($article, $title, $this->processor->sanitize($content, $article->hasAffiliateLinks()), $editor, true);
        $this->entityManager->persist($revision);
        $this->entityManager->flush();
        $this->revisions->pruneAutosaves($article);

        return $revision;
    }

    public function publishDueArticles(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $published = 0;
        foreach ($this->articles->findScheduledDue($now) as $article) {
            $article->setPublishedAt($now);
            $this->applyStatus($article, ArticleStatus::Published, null);
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
        $this->entityManager->flush();
        $this->publicCache->invalidateTags(['redirects']);
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
