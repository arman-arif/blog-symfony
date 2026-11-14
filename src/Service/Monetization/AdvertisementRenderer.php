<?php

declare(strict_types=1);

namespace App\Service\Monetization;

use App\Entity\Advertisement;
use App\Enum\AdvertisementPlacement;
use App\Enum\AdvertisementType;
use App\Repository\AdvertisementRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private RequestStack $requests,
    ) {
    }

    public function forPlacement(string|AdvertisementPlacement $placement, ?\DateTimeImmutable $now = null): ?Advertisement
    {
        $placement = is_string($placement) ? AdvertisementPlacement::from($placement) : $placement;
        $now ??= new \DateTimeImmutable();
        $ids = $this->cache->get('ads.candidates.v2.'.$placement->value, function (ItemInterface $item) use ($placement): array {
            $item->expiresAfter(300);
            $item->tag(['ads']);

            return $this->ads->enabledIdsForPlacement($placement);
        });

        foreach ($ids as $id) {
            $ad = $this->ads->find($id);
            if (null !== $ad && $ad->isActive() && $ad->getPlacement() === $placement
                && (null === $ad->getStartsAt() || $ad->getStartsAt() <= $now)
                && (null === $ad->getEndsAt() || $ad->getEndsAt() > $now)) {
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

        return new Markup($this->renderAd($ad), 'UTF-8');
    }

    public function injectInArticle(string $html): string
    {
        $ad = $this->forPlacement(AdvertisementPlacement::InArticle);
        if (null === $ad) {
            return $html;
        }

        $after = max(1, $ad->getAfterParagraph() ?? 1);
        $adHtml = $this->renderAd($ad);
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

    private function renderAd(Advertisement $ad): string
    {
        $content = $ad->getContent() ?? '';
        if (AdvertisementType::Image !== $ad->getType() && false !== stripos($content, '<script')) {
            // Only trusted ad.manage creatives receive execution authority. Never
            // apply this to reader comments or sanitized article content.
            $request = $this->requests->getMainRequest();
            $nonce = $request?->attributes->get('_csp_nonce', '');
            $nonce = is_string($nonce) ? $nonce : '';
            $document = new \DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$content.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($document->getElementsByTagName('script') as $script) {
                $script->setAttribute('nonce', $nonce);
            }
            $body = $document->getElementsByTagName('body')->item(0);
            $content = '';
            foreach ($body->childNodes ?? [] as $node) {
                $content .= $document->saveHTML($node);
            }
            $request?->attributes->set('_has_script_ad', true);
        }

        return $this->twig->render('front/partials/ad.html.twig', ['ad' => $ad, 'creative' => $content]);
    }
}
