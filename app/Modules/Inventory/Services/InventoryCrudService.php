<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Enums\TransactionAction;
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
    ) {}

    /**
     * Create a model inside a transaction and log the action.
     *
     * @param  class-string<Model>  $modelClass  Eloquent model FQCN
     * @param  array<string, mixed>  $data  Validated request data
     * @param  User  $user  Authenticated user
     * @param  string|null  $note  Custom note (defaults to "{ModelName} created")
     * @param  Model|null  $logTarget  If the audit log should point to a *different* model
     *                                 (e.g. ChemicalBatch logs against its parent Chemical)
     * @return Model The freshly created instance
     */
    public function create(
        string $modelClass,
        array $data,
        User $user,
        ?string $note = null,
        ?Model $logTarget = null,
    ): Model {
        return DB::transaction(function () use ($modelClass, $data, $user, $note, $logTarget): Model {
            /** @var Model $instance */
            $instance = $modelClass::create($data);

            $target = $logTarget ?? $instance;

            $this->transactionService->log(
                item: $target,
                user: $user,
                action: TransactionAction::ADDED,
                quantity: $this->extractQuantity($instance),
                note: $note ?? class_basename($modelClass).' created',
            );

            return $instance;
        });
    }

    /**
     * Update a model inside a transaction and log the action.
     *
     * @param  Model  $instance  The model being updated
     * @param  array<string, mixed>  $data  Validated request data
     * @param  User  $user  Authenticated user
     * @param  string|null  $note  Custom note (defaults to "{ModelName} updated")
     * @param  Model|null  $logTarget  Override audit-log target
     * @return Model The refreshed instance
     */
    public function update(
        Model $instance,
        array $data,
        User $user,
        ?string $note = null,
        ?Model $logTarget = null,
    ): Model {
        DB::transaction(function () use ($instance, $data, $user, $note, $logTarget): void {
            $instance->update($data);

            $target = $logTarget ?? $instance;

            $this->transactionService->log(
                item: $target,
                user: $user,
                action: TransactionAction::UPDATED,
                note: $note ?? class_basename($instance).' updated',
            );
        });

        return $instance->refresh();
    }

    /**
     * Soft-delete a model inside a transaction, logging DISPOSED first.
     *
     * @param  Model  $instance  The model being deleted
     * @param  User  $user  Authenticated user
     * @param  string|null  $note  Custom note (defaults to "{ModelName} deleted")
     * @param  Model|null  $logTarget  Override audit-log target
     */
    public function delete(
        Model $instance,
        User $user,
        ?string $note = null,
        ?Model $logTarget = null,
    ): void {
        DB::transaction(function () use ($instance, $user, $note, $logTarget): void {
            $target = $logTarget ?? $instance;

            $this->transactionService->log(
                item: $target,
                user: $user,
                action: TransactionAction::DISPOSED,
                quantity: $this->extractQuantity($instance),
                note: $note ?? class_basename($instance).' deleted',
            );

            $instance->delete();
        });
    }

    /**
     * If the model has a numeric `quantity` attribute, return it as a float.
     * Otherwise return null (e.g. Equipment, PlantSpecies, PlantVariety).
     */
    private function extractQuantity(Model $instance): ?float
    {
        if (isset($instance->quantity) && is_numeric($instance->quantity)) {
            return (float) $instance->quantity;
        }

        return null;
    }
}
