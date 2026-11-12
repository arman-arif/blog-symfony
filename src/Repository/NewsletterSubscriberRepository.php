<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Enum\NewsletterSubscriberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterSubscriber> */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findOneByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByToken(string $token): ?NewsletterSubscriber
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function countByStatus(NewsletterSubscriberStatus $status): int
    {
        return (int) $this->createQueryBuilder('subscriber')
            ->select('COUNT(subscriber.id)')
            ->andWhere('subscriber.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
