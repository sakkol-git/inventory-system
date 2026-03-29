<?php

declare(strict_types=1);

namespace App\Enums;

enum EquipmentCondition: string
{
    case GOOD = 'good';
    case NORMAL = 'normal';
    case BROKEN = 'broken';
}
