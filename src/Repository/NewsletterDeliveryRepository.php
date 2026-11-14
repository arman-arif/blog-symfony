<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterDelivery;
use App\Enum\NewsletterDeliveryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterDelivery> */
class NewsletterDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterDelivery::class);
    }

    public function findForUpdate(int $campaignId, int $recipientId): ?NewsletterDelivery
    {
        return $this->createQueryBuilder('delivery')
            ->andWhere('delivery.campaign = :campaign')->setParameter('campaign', $campaignId)
            ->andWhere('delivery.recipientId = :recipient')->setParameter('recipient', $recipientId)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /** @return list<int> */
    public function pendingRecipientIds(int $campaignId, int $afterId, \DateTimeImmutable $staleBefore, int $limit = 50): array
    {
        $rows = $this->createQueryBuilder('delivery')->select('delivery.recipientId AS id')
            ->andWhere('delivery.campaign = :campaign')->setParameter('campaign', $campaignId)
            ->andWhere('delivery.recipientId > :after')->setParameter('after', $afterId)
            ->andWhere('delivery.status = :pending OR (delivery.status = :sending AND (delivery.attemptedAt IS NULL OR delivery.attemptedAt <= :stale))')
            ->setParameter('pending', NewsletterDeliveryStatus::Pending)->setParameter('sending', NewsletterDeliveryStatus::Sending)
            ->setParameter('stale', $staleBefore)
            ->orderBy('delivery.recipientId', 'ASC')->setMaxResults($limit)->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @return array{sent: int, failed: int, skipped: int, uncertain: int, pending: int, sending: int, total: int} */
    public function statistics(int $campaignId): array
    {
        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'uncertain' => 0, 'pending' => 0, 'sending' => 0, 'total' => 0];
        $rows = $this->createQueryBuilder('delivery')->select('delivery.status AS status', 'COUNT(delivery.id) AS total')
            ->andWhere('delivery.campaign = :campaign')->setParameter('campaign', $campaignId)
            ->groupBy('delivery.status')->getQuery()->getScalarResult();
        foreach ($rows as $row) {
            $status = $row['status'] instanceof NewsletterDeliveryStatus ? $row['status']->value : (string) $row['status'];
            $counts[$status] = (int) $row['total'];
            $counts['total'] += (int) $row['total'];
        }

        return $counts;
    }
}
