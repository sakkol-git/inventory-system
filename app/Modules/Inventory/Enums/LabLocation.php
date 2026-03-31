<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum LabLocation: string
{
    case LAB_A = 'lab_a';
    case LAB_B = 'lab_b';
    case LAB_C = 'lab_c';
}
