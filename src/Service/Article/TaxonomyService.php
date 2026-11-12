<?php

declare(strict_types=1);

namespace App\Service\Article;

use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class TaxonomyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private SluggerInterface $slugger,
    ) {
    }

    public function saveCategory(Category $category): Category
    {
        $category->setSlug($this->uniqueCategorySlug($category->getName(), $category->getId()));
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    public function deleteCategory(Category $category): void
    {
        if ($this->categories->hasChildren($category)) {
            throw new \DomainException('Move or delete child categories before deleting this category.');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    public function saveTag(Tag $tag): Tag
    {
        $tag->setSlug($this->uniqueTagSlug($tag->getName(), $tag->getId()));
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    public function deleteTag(Tag $tag): void
    {
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
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
