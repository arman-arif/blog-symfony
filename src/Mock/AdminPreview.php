<?php

declare(strict_types=1);

namespace App\Mock;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Temporary HTTP gate; replaced by granular Security authorization in M4. */
final readonly class AdminPreview
{
    public function __construct(
        #[Autowire(env: 'bool:APP_UI_PREVIEW')] private bool $enabled,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public function requireEnabled(): void
    {
        if (!$this->enabled || !in_array($this->environment, ['dev', 'test'], true)) {
            throw new NotFoundHttpException();
        }
    }
}
