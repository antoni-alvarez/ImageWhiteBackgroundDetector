<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum BorderSide: string
{
    case TOP = 'top';
    case RIGHT = 'right';
    case BOTTOM = 'bottom';
    case LEFT = 'left';
}
