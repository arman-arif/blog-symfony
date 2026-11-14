<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterCampaign;
use App\Enum\NewsletterCampaignStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterCampaign> */
class NewsletterCampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterCampaign::class);
    }

    public function findForUpdate(int $id): ?NewsletterCampaign
    {
        return $this->createQueryBuilder('campaign')->andWhere('campaign.id = :id')->setParameter('id', $id)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }

    /** @return list<int> */
    public function dueOrUnfinishedIds(\DateTimeImmutable $now, int $afterId = 0, int $limit = 100): array
    {
        $rows = $this->createQueryBuilder('campaign')->select('campaign.id AS id')
            ->andWhere('campaign.id > :after')->setParameter('after', $afterId)
            ->andWhere('(campaign.status = :scheduled AND campaign.scheduledAt <= :now) OR campaign.status IN (:unfinished)')
            ->setParameter('scheduled', NewsletterCampaignStatus::Scheduled)->setParameter('now', $now)
            ->setParameter('unfinished', [NewsletterCampaignStatus::Queued, NewsletterCampaignStatus::Sending])
            ->orderBy('campaign.id', 'ASC')->setMaxResults($limit)->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
