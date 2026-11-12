<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final readonly class VisitorHash
{
    public function __construct(#[Autowire('%kernel.secret%')] private string $secret)
    {
    }

    public function fromRequest(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->getClientIp() ?? '',
            (string) $request->headers->get('User-Agent'),
            $this->secret,
        ]));
    }
}
