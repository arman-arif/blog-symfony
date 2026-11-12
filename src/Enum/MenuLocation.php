<?php

declare(strict_types=1);

namespace App\Enum;

enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';
}
