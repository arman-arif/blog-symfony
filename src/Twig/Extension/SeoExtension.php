<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Seo\SeoHeadFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SeoExtension extends AbstractExtension
{
    public function __construct(
        private readonly SeoHeadFactory $factory,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('seo_metadata', $this->seoMetadata(...))];
    }

    /** @return array<string, mixed> */
    public function seoMetadata(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        return null === $request ? $this->factory->forHome() : $this->factory->forRequest($request);
    }
}
