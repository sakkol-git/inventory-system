<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum MaintenanceType: string
{
    case PREVENTIVE = 'preventive';
    case CORRECTIVE = 'corrective';
    case CALIBRATION = 'calibration';
    case INSPECTION = 'inspection';
}
