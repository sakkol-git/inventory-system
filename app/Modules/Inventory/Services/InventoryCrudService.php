<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;
use App\Modules\Inventory\Enums\TransactionAction;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Generic CRUD + transaction-log service used by every inventory controller.
 *
 * Eliminates the DB::transaction + TransactionService::log duplication
 * that was copy-pasted across 7+ controllers (B-05).
 *
 * Controllers call this instead of inlining the same 10-line block.
 */
class InventoryCrudService
{
   public function __construct(
    private readonly TransactionService $transactionService,
   )
   {}

   public function extractQuantity(Model $instance): ?float
   {
    if(isset($instance->quantity) && is_numeric($instance->quantity)) {
        return (float) $instance->quantity;
    }
    return null;
   }

   // Create a new inventory item, log the transaction, and return the instance.
   public function create(
    string $modelClass,
    array $data,
    User $user,
    ?Model $logTarget = null,
   ): Model
   {
    return DB::transaction(function () use ($modelClass, $data, $user, $logTarget): Model {
        $instance = $modelClass::create($data);
        $target = $logTarget ?? $instance;

        $this->transactionService->log(
            item: $target,
            user: $user,
            action: TransactionAction::ADDED,
            quantity: $this->extractQuantity($instance),
        );

        return $instance;
    });
   }

   // Update an inventory item, log the transaction, and return the instance.

   public function update(
    Model $instance,
    array $data,
    User $user,
    TransactionAction $action,
    ?Model $logTarget = null,
   ): Model
   {
    return DB::transaction(function() use ($instance, $data, $user, $action, $logTarget): Model {
        $instance->update($data);
        $target = $logTarget ?? $instance;

        $this->transactionService->log(
            item: $target,
            user: $user,
            action: TransactionAction::UPDATED,
            quantity: $this->extractQuantity($instance),
        );

        return $instance->refresh();
    });
   }
}
