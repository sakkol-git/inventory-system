<?php

declare(strict_types=1);

namespace App\Enums;

enum PlantGrowthType: string
{
    case ANNUAL = 'annual';
    case PERENNIAL = 'perennial';
    case BIENNIAL = 'biennial';
}
