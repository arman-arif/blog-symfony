<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Enum\NewsletterSubscriberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    public function findForUpdate(int $id): ?NewsletterSubscriber
    {
        return $this->createQueryBuilder('subscriber')->andWhere('subscriber.id = :id')->setParameter('id', $id)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
    }

    public function highestId(): int
    {
        return (int) $this->createQueryBuilder('subscriber')->select('MAX(subscriber.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return list<int> */
    public function confirmedIdsAfter(int $afterId, int $upToId, int $limit = 50): array
    {
        $rows = $this->createQueryBuilder('subscriber')->select('subscriber.id AS id')
            ->andWhere('subscriber.id > :after AND subscriber.id <= :upper')->setParameter('after', $afterId)->setParameter('upper', $upToId)
            ->andWhere('subscriber.status = :confirmed')->setParameter('confirmed', NewsletterSubscriberStatus::Confirmed)
            ->orderBy('subscriber.id', 'ASC')->setMaxResults($limit)->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
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

    /**
     * @param array{status?: string, q?: string} $filters
     *
     * @return array{items: list<NewsletterSubscriber>, total: int, pages: int}
     */
    public function findForAdmin(array $filters, int $page, int $perPage = 25): array
    {
        $builder = $this->createQueryBuilder('subscriber')->orderBy('subscriber.createdAt', 'DESC');

        if (isset($filters['status']) && '' !== $filters['status']) {
            $builder->andWhere('subscriber.status = :status')->setParameter('status', NewsletterSubscriberStatus::from($filters['status']));
        }
        if (isset($filters['q']) && '' !== trim($filters['q'])) {
            $builder->andWhere('subscriber.email LIKE :query')->setParameter('query', '%'.trim($filters['q']).'%');
        }

        $query = $builder->getQuery()->setFirstResult(max(0, ($page - 1) * $perPage))->setMaxResults($perPage);
        $paginator = new Paginator($query);

        return ['items' => iterator_to_array($paginator->getIterator()), 'total' => count($paginator), 'pages' => max(1, (int) ceil(count($paginator) / $perPage))];
    }

    /** @return iterable<array{id: int, email: string, status: NewsletterSubscriberStatus, createdAt: \DateTimeImmutable, confirmedAt: ?\DateTimeImmutable, unsubscribedAt: ?\DateTimeImmutable}> */
    public function iterateForExport(): iterable
    {
        $after = 0;
        $upper = $this->highestId();
        do {
            $rows = $this->createQueryBuilder('subscriber')
                ->select('subscriber.id', 'subscriber.email', 'subscriber.status', 'subscriber.createdAt', 'subscriber.confirmedAt', 'subscriber.unsubscribedAt')
                ->andWhere('subscriber.id > :after AND subscriber.id <= :upper')->setParameter('after', $after)->setParameter('upper', $upper)
                ->orderBy('subscriber.id', 'ASC')->setMaxResults(500)->getQuery()->getArrayResult();
            foreach ($rows as $row) {
                $after = $row['id'];
                yield $row;
            }
        } while ([] !== $rows);
    }
}
