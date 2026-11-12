<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findOneBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** @return list<Category> */
    public function findRoots(): array
    {
        return $this->findBy(['parent' => null], ['lft' => 'ASC']);
    }

    /** @return list<Category> */
    public function findTree(): array
    {
        return $this->findBy([], ['lft' => 'ASC']);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $builder = $this->createQueryBuilder('category')
            ->select('COUNT(category.id)')
            ->andWhere('category.slug = :slug')
            ->setParameter('slug', $slug);
        if (null !== $ignoreId) {
            $builder->andWhere('category.id != :id')->setParameter('id', $ignoreId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult() > 0;
    }

    public function hasChildren(Category $category): bool
    {
        return (int) $this->createQueryBuilder('category')
            ->select('COUNT(category.id)')
            ->andWhere('category.parent = :parent')
            ->setParameter('parent', $category)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
