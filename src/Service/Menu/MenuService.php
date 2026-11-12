<?php

declare(strict_types=1);

namespace App\Service\Menu;

use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Enum\MenuLocation;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class MenuService
{
    public function __construct(
        private MenuRepository $menus,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urls,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    /** @return list<array{label: string, url: string, type: string, children: list<mixed>}> */
    public function tree(MenuLocation $location): array
    {
        return $this->cache->get('menus.tree.'.$location->value, function (ItemInterface $item) use ($location): array {
            $item->expiresAfter(3600);
            $item->tag(['menus']);
            $menu = $this->menus->findOneByLocation($location);
            if (null === $menu) {
                return [];
            }

            return $this->branch($menu, null);
        });
    }

    public function save(Menu $menu): void
    {
        $this->entityManager->persist($menu);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    public function delete(Menu $menu): void
    {
        $this->entityManager->remove($menu);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    public function addItem(Menu $menu, MenuItem $item): void
    {
        $item->setMenu($menu);
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    public function removeItem(MenuItem $item): void
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    /**
     * @param list<array{id: int, position: int, parentId?: int|null}> $items
     */
    public function reorder(Menu $menu, array $items): void
    {
        $byId = [];
        foreach ($menu->getItems() as $item) {
            $byId[(int) $item->getId()] = $item;
        }
        foreach ($items as $row) {
            $item = $byId[(int) $row['id']] ?? null;
            if (null === $item) {
                continue;
            }
            $parent = null !== ($row['parentId'] ?? null) ? ($byId[(int) $row['parentId']] ?? null) : null;
            $item->setParent($parent === $item ? null : $parent)->setPosition((int) $row['position']);
        }
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    /** @return list<array{label: string, url: string, type: string, children: list<mixed>}> */
    private function branch(Menu $menu, ?MenuItem $parent): array
    {
        $items = $menu->getItems()->filter(static fn (MenuItem $item): bool => $item->getParent() === $parent)->toArray();
        usort($items, static fn (MenuItem $a, MenuItem $b): int => [$a->getPosition(), $a->getId()] <=> [$b->getPosition(), $b->getId()]);

        return array_map(fn (MenuItem $item): array => [
            'label' => $item->getLabel(),
            'url' => $this->resolveUrl($item),
            'type' => $item->getType()->value,
            'children' => $this->branch($menu, $item),
        ], $items);
    }

    private function resolveUrl(MenuItem $item): string
    {
        $target = $item->getTarget();
        if (str_starts_with($target, '/') || str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }

        return match ($item->getType()->value) {
            'category' => $this->urls->generate('front_category', ['slug' => $target]),
            'article' => $this->urls->generate('front_article', ['slug' => $target]),
            default => $this->urls->generate('front_page', ['slug' => $target]),
        };
    }
}
