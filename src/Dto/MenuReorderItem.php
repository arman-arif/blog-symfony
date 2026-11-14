<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MenuReorderItem
{
    public function __construct(
        #[Assert\Positive] public int $id,
        #[Assert\PositiveOrZero] public int $position,
        #[Assert\Positive] public ?int $parentId = null,
    ) {
    }
}
