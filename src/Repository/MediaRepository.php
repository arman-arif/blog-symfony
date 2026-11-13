<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Media> */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    public function findOneByStorageKey(string $storageKey): ?Media
    {
        return $this->findOneBy(['storageKey' => $storageKey]);
    }

    /** @return list<array{id: int, storageKey: string}> */
    public function pendingFileCleanup(int $afterId, int $limit = 100): array
    {
        // DBAL deliberately includes soft-deleted tombstones without disabling ORM filters.
        $rows = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('id', 'storage_key')->from('media')
            ->where('deleted_at IS NOT NULL')->andWhere('files_deleted_at IS NULL')->andWhere('id > :after')
            ->setParameter('after', $afterId)->orderBy('id')->setMaxResults($limit)
            ->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'storageKey' => (string) $row['storage_key']], $rows);
    }

    public function markFilesDeleted(int $id): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE media SET files_deleted_at = :now WHERE id = :id AND deleted_at IS NOT NULL',
            ['now' => new \DateTimeImmutable(), 'id' => $id],
            ['now' => Types::DATETIME_IMMUTABLE],
        );
    }

    /**
     * @param array{q?: string, type?: string, date?: string} $filters
     *
     * @return array{items: list<Media>, total: int}
     */
    public function findForAdmin(array $filters, int $page, int $perPage): array
    {
        $builder = $this->createQueryBuilder('media')->orderBy('media.createdAt', 'DESC');

        if ('' !== ($filters['q'] ?? '')) {
            $builder->andWhere('media.originalName LIKE :query OR media.alt LIKE :query OR media.caption LIKE :query')
                ->setParameter('query', '%'.$filters['q'].'%');
        }
        if ('image' === ($filters['type'] ?? '')) {
            $builder->andWhere('media.mimeType LIKE :image')->setParameter('image', 'image/%');
        } elseif ('document' === ($filters['type'] ?? '')) {
            $builder->andWhere('media.mimeType NOT LIKE :image')->setParameter('image', 'image/%');
        }
        if (1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['date'] ?? ''))) {
            $start = new \DateTimeImmutable($filters['date'].' 00:00:00');
            $builder->andWhere('media.createdAt >= :start AND media.createdAt < :end')
                ->setParameter('start', $start)
                ->setParameter('end', $start->modify('+1 day'));
        }

        $query = $builder->getQuery()
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);
        $paginator = new Paginator($query);
        /** @var list<Media> $items */
        $items = iterator_to_array($paginator->getIterator());

        return ['items' => $items, 'total' => count($paginator)];
    }

    /** @return list<Media> */
    public function findForPicker(string $query, int $limit = 40): array
    {
        $builder = $this->createQueryBuilder('media')
            ->andWhere('media.mimeType LIKE :image')
            ->setParameter('image', 'image/%')
            ->orderBy('media.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ('' !== $query) {
            $builder->andWhere('media.originalName LIKE :query OR media.alt LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        return $builder->getQuery()->getResult();
    }
}
