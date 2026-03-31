<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum StockStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case OUT_OF_STOCK = 'out_of_stock';
}
