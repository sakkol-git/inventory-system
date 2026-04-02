<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Inventory\Models\Transaction;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('transactions.view', 'api');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('transactions.view', 'api');
    }
}
