<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ArticleRevision> */
class ArticleRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleRevision::class);
    }

    /** @return list<ArticleRevision> */
    public function findForArticle(Article $article): array
    {
        return $this->findBy(['article' => $article], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function findOneForArticle(Article $article, int $id): ?ArticleRevision
    {
        return $this->findOneBy(['article' => $article, 'id' => $id]);
    }

    public function pruneAutosaves(Article $article, int $keep = 10): void
    {
        $old = $this->createQueryBuilder('revision')
            ->andWhere('revision.article = :article')
            ->andWhere('revision.isAutosave = true')
            ->setParameter('article', $article)
            ->orderBy('revision.createdAt', 'DESC')
            ->addOrderBy('revision.id', 'DESC')
            ->setFirstResult($keep)
            ->getQuery()
            ->getResult();

        foreach ($old as $revision) {
            $this->getEntityManager()->remove($revision);
        }
    }
}
