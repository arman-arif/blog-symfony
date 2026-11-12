<?php

declare(strict_types=1);

namespace App\Enum;

enum AdvertisementPlacement: string
{
    case Header = 'header';
    case InArticle = 'in_article';
    case Sidebar = 'sidebar';
    case Footer = 'footer';
}
