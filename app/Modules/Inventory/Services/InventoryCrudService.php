<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;
use App\Modules\Inventory\Enums\TransactionAction;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Modules\Core\Services\ImageUploadService;



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
    private readonly ImageUploadService $imageService,
   )
   {}


   // helper function to extract quantity from any model that has it, for logging purposes.
   public function extractQuantity(Model $instance): ?float
   {
    if(isset($instance->quantity) && is_numeric($instance->quantity)) {
        return (float) $instance->quantity;
    }
    return null;
   }

/**
     * List items for any model, with optional parent filtering, searching, and sorting.
     *
     * @param string $modelClass The fully qualified class name of the model (e.g., User::class)
     * @param Request $request The incoming HTTP request
     * @param int $perPage Pagination count
     * @param string|null $parentForeignKey The column name of the parent (e.g., 'category_id', 'user_id')
     * @return LengthAwarePaginator
     */
    public function listItems(
        string $modelClass,
        Request $request,
        int $perPage = 15,
        array $with = [],
        array $filterMap = [],
        array $valueScopeMap = [],
        array $booleanScopeMap = [],
        ?string $defaultSortBy = 'created_at',
        string $defaultSortDir = 'desc',
    ): LengthAwarePaginator {
        /** @var Model $model */
        $model = new $modelClass();
        $query = $modelClass::query();

        if ($with !== []) {
            $query->with($with);
        }

        // 1. DYNAMIC SEARCH: Check if the model actually has a 'scopeSearch' method before applying it
        if ($request->filled('search') && $query->hasNamedScope('search')) {
            $query->search($request->input('search'));
        }

        // 2. Dynamic request filters: map request key => db column
        foreach ($filterMap as $requestKey => $column) {
            if ($request->filled($requestKey)) {
                $query->where($column, $request->input($requestKey));
            }
        }

        foreach ($valueScopeMap as $requestKey => $scopeMethod) {
            if ($request->filled($requestKey) && $query->hasNamedScope($scopeMethod)) {
                $query->{$scopeMethod}($request->input($requestKey));
            }
        }

        foreach ($booleanScopeMap as $requestKey => $scopeMethod) {
            if ($request->boolean($requestKey) && $query->hasNamedScope($scopeMethod)) {
                $query->{$scopeMethod}();
            }
        }

        $sortBy = $defaultSortBy;
        $sortDir = strtolower($defaultSortDir) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = array_values(array_unique(array_merge(
            $model->getFillable(),
            [$model->getKeyName(), 'created_at', 'updated_at']
        )));

        // 3. SORTING: Apply safe sorting
        if ($request->filled('sort_by')) {
            $requestedSortBy = (string) $request->input('sort_by');
            // Ensure sort direction is only 'asc' or 'desc' to prevent SQL Injection
            $sortDir = strtolower((string) $request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

            if (in_array($requestedSortBy, $allowedSorts, true)) {
                $sortBy = $requestedSortBy;
            }
        }

        if ($sortBy !== null && in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        }

        return $query->paginate($perPage);
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
        $data = $this->imageService->prepareDataForPersistence($data, $modelClass);

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
    ?Model $logTarget = null,
   ): Model
   {
    return DB::transaction(function() use ($instance, $data, $user, $logTarget): Model {
        $data = $this->imageService->prepareDataForPersistence($data, $instance, $instance);

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

  // Delete an inventory item, log the transaction, and return void.
   public function delete(
    Model $instance,
    User $user,
    ?Model $logTarget = null,
   ): void
   {
    DB::transaction(function() use ($instance, $user, $logTarget): void {
        $target = $logTarget ?? $instance;

        $this->transactionService->log(
            item: $target,
            user: $user,
            action: TransactionAction::DISPOSED,
            quantity: $this->extractQuantity($instance),
        );

        $instance->delete();
    });
   }
}
