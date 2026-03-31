<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum DangerLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
