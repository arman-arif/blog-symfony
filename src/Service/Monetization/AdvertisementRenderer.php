<?php

declare(strict_types=1);

namespace App\Service\Monetization;

use App\Entity\Advertisement;
use App\Enum\AdvertisementPlacement;
use App\Repository\AdvertisementRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;
use Twig\Markup;

final readonly class AdvertisementRenderer
{
    public function __construct(
        private AdvertisementRepository $ads,
        private Environment $twig,
        #[Autowire(service: 'cache.content')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function forPlacement(string|AdvertisementPlacement $placement, ?\DateTimeImmutable $now = null): ?Advertisement
    {
        $placement = is_string($placement) ? AdvertisementPlacement::from($placement) : $placement;
        $ids = $this->cache->get('ads.placement.'.$placement->value, function (ItemInterface $item) use ($placement, $now): array {
            $item->expiresAfter(300);
            $item->tag(['ads']);

            return array_map(static fn (Advertisement $ad): ?int => $ad->getId(), $this->ads->findActiveForPlacement($placement, $now));
        });

        foreach ($ids as $id) {
            $ad = null === $id ? null : $this->ads->find($id);
            if (null !== $ad) {
                return $ad;
            }
        }

        return null;
    }

    public function render(string|AdvertisementPlacement $placement): Markup
    {
        $ad = $this->forPlacement($placement);
        if (null === $ad) {
            return new Markup('', 'UTF-8');
        }

        return new Markup($this->twig->render('front/partials/ad.html.twig', ['ad' => $ad]), 'UTF-8');
    }

    public function injectInArticle(string $html): string
    {
        $ad = $this->forPlacement(AdvertisementPlacement::InArticle);
        if (null === $ad) {
            return $html;
        }

        $after = max(1, $ad->getAfterParagraph() ?? 1);
        $adHtml = $this->twig->render('front/partials/ad.html.twig', ['ad' => $ad]);
        $count = 0;
        $inserted = false;
        $result = preg_replace_callback('#</p>#i', static function (array $match) use (&$count, &$inserted, $after, $adHtml): string {
            ++$count;
            if (!$inserted && $count === $after) {
                $inserted = true;

                return $match[0].$adHtml;
            }

            return $match[0];
        }, $html);

        $result ??= $html;

        return $inserted ? $result : $result.$adHtml;
    }

    public function invalidate(): void
    {
        $this->cache->invalidateTags(['ads']);
    }
}
