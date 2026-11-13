<?php

declare(strict_types=1);

namespace App\Service\Article;

use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class TaxonomyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private SluggerInterface $slugger,
        private TagAwareCacheInterface $publicCache,
    ) {
    }

    public function saveCategory(Category $category): Category
    {
        $source = '' === trim($category->getSlug()) ? $category->getName() : $category->getSlug();
        $category->setSlug($this->uniqueCategorySlug($source, $category->getId()));
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->publicCache->invalidateTags(['sitemap']);

        return $category;
    }

    public function deleteCategory(Category $category): void
    {
        if ($this->categories->hasChildren($category)) {
            throw new \DomainException('Move or delete child categories before deleting this category.');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
        $this->publicCache->invalidateTags(['sitemap']);
    }

    public function saveTag(Tag $tag): Tag
    {
        $source = '' === trim($tag->getSlug()) ? $tag->getName() : $tag->getSlug();
        $tag->setSlug($this->uniqueTagSlug($source, $tag->getId()));
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->publicCache->invalidateTags(['sitemap']);

        return $tag;
    }

    public function deleteTag(Tag $tag): void
    {
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
        $this->publicCache->invalidateTags(['sitemap']);
    }

    public function findOrCreateTag(string $name): Tag
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \DomainException('A tag needs a name.');
        }

        $existing = $this->tags->findOneBy(['name' => $name]);
        if (null !== $existing) {
            return $existing;
        }

        return $this->saveTag((new Tag())->setName($name));
    }

    public function uniqueCategorySlug(string $source, ?int $ignoreId = null): string
    {
        return $this->unique($source, $ignoreId, fn (string $slug, ?int $id): bool => $this->categories->slugExists($slug, $id), 'category');
    }

    public function uniqueTagSlug(string $source, ?int $ignoreId = null): string
    {
        return $this->unique($source, $ignoreId, fn (string $slug, ?int $id): bool => $this->tags->slugExists($slug, $id), 'tag');
    }

    /** @param callable(string, ?int): bool $exists */
    private function unique(string $source, ?int $ignoreId, callable $exists, string $fallback): string
    {
        $base = strtolower((string) $this->slugger->slug($source));
        $base = '' === $base ? $fallback : $base;
        $slug = $base;
        $suffix = 2;
        while ($exists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
