<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum ChemicalCategory: string
{
    case ACID = 'acid';
    case BASE = 'base';
    case SOLVENT = 'solvent';
    case OXIDIZER = 'oxidizer';
    case REDUCER = 'reducer';
    case OTHER = 'other';
}
