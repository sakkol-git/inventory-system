<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum EquipmentCategory: string
{
    case MICROSCOPE = 'microscope';
    case CENTRIFUGE = 'centrifuge';
    case INCUBATOR = 'incubator';
    case SPECTROPHOTOMETER = 'spectrophotometer';
    case OTHER = 'other';
}
