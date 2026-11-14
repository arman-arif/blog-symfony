<?php

declare(strict_types=1);

namespace App\Service\Menu;

use App\Dto\MenuReorderItem;
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

    public function save(Menu $menu, ?Menu $details = null): void
    {
        $details ??= $menu;
        $existing = $this->menus->findOneByLocation($details->getLocation());
        if (null !== $existing && $existing->getId() !== $menu->getId()) {
            throw new \DomainException('A menu already exists at that location.');
        }
        $this->entityManager->wrapInTransaction(function () use ($menu, $details): void {
            $menu->setName($details->getName())->setLocation($details->getLocation());
            $this->entityManager->persist($menu);
        });
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
        $this->assertValidParent($menu, $item, $item->getParent());
        $item->setMenu($menu);
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    public function saveItem(MenuItem $item, MenuItem $details): void
    {
        $this->assertValidParent($item->getMenu(), $item, $details->getParent());
        $this->entityManager->wrapInTransaction(static function () use ($item, $details): void {
            $item->setLabel($details->getLabel())->setType($details->getType())->setTarget($details->getTarget())
                ->setParent($details->getParent())->setPosition($details->getPosition());
        });
        $this->cache->invalidateTags(['menus']);
    }

    public function removeItem(MenuItem $item): void
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->cache->invalidateTags(['menus']);
    }

    /**
     * @param list<MenuReorderItem> $items
     */
    public function reorder(Menu $menu, array $items): void
    {
        $byId = [];
        $parents = [];
        foreach ($menu->getItems() as $item) {
            $byId[(int) $item->getId()] = $item;
            $parents[(int) $item->getId()] = $item->getParent()?->getId();
        }
        $seen = [];
        foreach ($items as $row) {
            if (!isset($byId[$row->id]) || isset($seen[$row->id]) || $row->position < 0
                || (null !== $row->parentId && !isset($byId[$row->parentId]))) {
                throw new \DomainException('Invalid, duplicate or cross-menu item.');
            }
            $seen[$row->id] = true;
            $parents[$row->id] = $row->parentId;
        }
        // Validate the complete proposed graph (including unchanged edges) before
        // touching any managed entity, so a later flush cannot persist rejection.
        foreach (array_keys($parents) as $id) {
            $path = [];
            for ($current = $id; null !== $current; $current = $parents[$current]) {
                if (!array_key_exists($current, $parents) || isset($path[$current])) {
                    throw new \DomainException('Menu items cannot form cycles or cross menus.');
                }
                $path[$current] = true;
            }
        }
        $this->entityManager->wrapInTransaction(static function () use ($items, $byId): void {
            foreach ($items as $row) {
                $byId[$row->id]->setParent(null === $row->parentId ? null : $byId[$row->parentId])->setPosition($row->position);
            }
        });
        $this->cache->invalidateTags(['menus']);
    }

    private function assertValidParent(Menu $menu, MenuItem $item, ?MenuItem $parent): void
    {
        $seen = [];
        while (null !== $parent) {
            $identity = spl_object_id($parent);
            if ($parent->getMenu()->getId() !== $menu->getId() || $parent === $item
                || (null !== $item->getId() && $parent->getId() === $item->getId()) || isset($seen[$identity])) {
                throw new \DomainException('Menu items cannot form cycles or cross menus.');
            }
            $seen[$identity] = true;
            $parent = $parent->getParent();
        }
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
