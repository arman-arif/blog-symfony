<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Page;
use App\Enum\PageStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        return $this->findOneBy(['slug' => $slug, 'status' => PageStatus::Published]);
    }

    /** @return list<Page> */
    public function findForSitemap(): array
    {
        return $this->createQueryBuilder('page')
            ->andWhere('page.status = :published')
            ->andWhere('page.seo.noindex = false')
            ->setParameter('published', PageStatus::Published)
            ->orderBy('page.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
