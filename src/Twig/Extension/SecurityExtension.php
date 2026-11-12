<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SecurityExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('csp_nonce', fn (): string => (string) $this->requestStack->getCurrentRequest()?->attributes->get('_csp_nonce', ''))];
    }
}
