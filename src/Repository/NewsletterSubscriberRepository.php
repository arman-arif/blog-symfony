<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Enum\NewsletterSubscriberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /** @return list<NewsletterSubscriber> */
    public function findAllForExport(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }
}
