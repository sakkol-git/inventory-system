<?php
declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum BorrowStatus: string
{
    case PENDING = 'pending';
    case BORROWED = 'borrowed';
    case RETURNED = 'returned';
    case OVERDUE = 'overdue';
    case REJECTED = 'rejected';
}
