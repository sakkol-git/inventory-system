<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\TransactionAction;
use App\Modules\Inventory\Models\Transaction;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Central service for logging every inventory action as a polymorphic Transaction.
 * Called from controllers/services — never created directly.
 */
class TransactionService
{
    /**
     * Log a transaction against any inventory model.
     */
    public function log(
        Model $item,
        User $user,
        TransactionAction $action,
        ?float $quantity = null,
    ): Transaction {
        return $item->transactions()->create([
            'user_id' => $user->id,
            'action' => $action,
            'quantity' => $quantity,
        ]);
    }
}
