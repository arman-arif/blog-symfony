<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Enum\MenuLocation;
use App\Service\Menu\MenuService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuExtension extends AbstractExtension
{
    public function __construct(private readonly MenuService $menus)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('public_menu', fn (string $location): array => $this->menus->tree(MenuLocation::from($location)))];
    }
}
