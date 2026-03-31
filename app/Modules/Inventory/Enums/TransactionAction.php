<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum TransactionAction: string
{
    case ADDED = 'added';
    case UPDATED = 'updated';
    case CONSUMED = 'consumed';
    case BORROWED = 'borrowed';
    case RETURNED = 'returned';
    case HARVESTED = 'harvested';
    case DISPOSED = 'disposed';
}
