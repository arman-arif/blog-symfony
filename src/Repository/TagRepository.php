<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Tag> */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findOneBySlug(string $slug): ?Tag
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $builder = $this->createQueryBuilder('tag')
            ->select('COUNT(tag.id)')
            ->andWhere('tag.slug = :slug')
            ->setParameter('slug', $slug);
        if (null !== $ignoreId) {
            $builder->andWhere('tag.id != :id')->setParameter('id', $ignoreId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<Tag> */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('tag')
            ->andWhere('tag.name LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('tag.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
