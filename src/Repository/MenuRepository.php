<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use App\Enum\MenuLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Menu> */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    public function findOneByLocation(MenuLocation $location): ?Menu
    {
        return $this->findOneBy(['location' => $location]);
    }
}
