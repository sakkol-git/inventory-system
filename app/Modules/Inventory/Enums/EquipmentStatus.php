<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum EquipmentStatus: string
{
    case AVAILABLE = 'available';
    case BORROWED = 'borrowed';
    case IN_USE = 'in_use';
    case UNDER_MAINTENANCE = 'under_maintenance';
}
