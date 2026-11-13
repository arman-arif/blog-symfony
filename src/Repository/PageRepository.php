<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Page;
use App\Enum\PageStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Page> */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        return $this->publicBuilder()
            ->andWhere('page.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $builder = $this->createQueryBuilder('page')
            ->select('COUNT(page.id)')
            ->andWhere('page.slug = :slug')->setParameter('slug', $slug);
        if (null !== $ignoreId) {
            $builder->andWhere('page.id != :id')->setParameter('id', $ignoreId);
        }

        // Retained trash still owns its unique database slug. Preserve the filter's parameters.
        $filters = $this->getEntityManager()->getFilters();
        $enabled = $filters->isEnabled('softdeleteable');
        if ($enabled) {
            $filters->suspend('softdeleteable');
        }
        try {
            return (int) $builder->getQuery()->getSingleScalarResult() > 0;
        } finally {
            if ($enabled) {
                $filters->restore('softdeleteable');
            }
        }
    }

    /** @return list<Page> */
    public function findForSitemap(): array
    {
        return $this->publicBuilder()
            ->andWhere('page.seo.noindex = false')
            ->orderBy('page.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function publicBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('page')
            ->andWhere('page.status = :published')
            ->andWhere('page.publishedAt IS NOT NULL AND page.publishedAt <= :now')
            ->andWhere('page.deletedAt IS NULL')
            ->setParameter('published', PageStatus::Published)
            ->setParameter('now', new \DateTimeImmutable());
    }
}
