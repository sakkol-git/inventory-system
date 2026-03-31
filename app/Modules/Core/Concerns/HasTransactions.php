<?php

namespace App\Modules\Core\Concerns;

use App\Modules\Inventory\Models\Transaction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTransactions
{
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }
}
