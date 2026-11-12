<?php

declare(strict_types=1);

namespace App\Enum;

enum MenuItemType: string
{
    case Custom = 'custom';
    case Page = 'page';
    case Category = 'category';
    case Article = 'article';
}
