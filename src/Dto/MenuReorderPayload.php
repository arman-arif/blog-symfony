<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MenuReorderPayload
{
    /** @param list<MenuReorderItem> $items */
    public function __construct(
        #[Assert\Count(min: 1, max: 1000)] #[Assert\Valid] public array $items,
        public ?string $_token = null,
    ) {
    }
}
