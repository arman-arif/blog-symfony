<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Monetization\AdvertisementRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AdvertisementExtension extends AbstractExtension
{
    public function __construct(private readonly AdvertisementRenderer $renderer)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('render_ad', $this->renderer->render(...), ['is_safe' => ['html']])];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('article_ads', $this->renderer->injectInArticle(...), ['is_safe' => ['html']])];
    }
}
