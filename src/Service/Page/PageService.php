<?php

declare(strict_types=1);

namespace App\Service\Page;

use App\Entity\Page;
use App\Entity\User;
use App\Enum\PageStatus;
use App\Repository\PageRepository;
use App\Service\Article\ContentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class PageService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageRepository $pages,
        private ContentProcessor $processor,
        private SluggerInterface $slugger,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $publicCache,
    ) {
    }

    public function save(Page $page, Page $details, User $actor): void
    {
        $candidate = clone $details;
        $source = '' === trim($candidate->getSlug()) ? $candidate->getTitle() : $candidate->getSlug();
        $candidate->setSlug($this->uniqueSlug($source, $page->getId()));
        $candidate->setContent($this->processor->sanitize($candidate->getContent()));
        if (PageStatus::Published === $candidate->getStatus() && null === $candidate->getPublishedAt()) {
            $candidate->setPublishedAt(new \DateTimeImmutable());
        }

        $this->entityManager->wrapInTransaction(function () use ($page, $candidate, $actor): void {
            $page->setTitle($candidate->getTitle())
                ->setSlug($candidate->getSlug())
                ->setContent($candidate->getContent())
                ->setStatus($candidate->getStatus())
                ->setPublishedAt($candidate->getPublishedAt())
                ->setSeo(clone $candidate->getSeo());
            if (null === $page->getAuthor()) {
                $page->setAuthor($actor);
            }
            $this->entityManager->persist($page);
        });
        $this->publicCache->invalidateTags(['sitemap', 'pages']);
    }

    public function delete(Page $page): void
    {
        $this->entityManager->wrapInTransaction(function () use ($page): void {
            $this->entityManager->remove($page);
        });
        $this->publicCache->invalidateTags(['sitemap', 'pages']);
    }

    private function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = substr(strtolower((string) $this->slugger->slug($source)), 0, 280);
        $base = '' === $base ? 'page' : rtrim($base, '-');
        $slug = $base;
        $suffix = 2;
        while ($this->pages->slugExists($slug, $ignoreId)) {
            $tail = '-'.$suffix++;
            $slug = rtrim(substr($base, 0, 280 - strlen($tail)), '-').$tail;
        }

        return $slug;
    }
}
