<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\Crud;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\ImageUpload\ImageUploadService;
use App\Modules\Inventory\Enums\TransactionAction;
use App\Modules\Inventory\Services\TransactionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CrudMutationService
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly ImageUploadService $imageService,
        private readonly CrudQuantityService $quantityService,
    ) {
    }

    public function create(
        string $modelClass,
        array $data,
        User $user,
        ?Model $logTarget = null,
    ): Model {
        return DB::transaction(function () use ($modelClass, $data, $user, $logTarget): Model {
            $payload = $this->imageService->prepareDataForPersistence($data, $modelClass);

            /** @var Model $instance */
            $instance = $modelClass::create($payload);

            $this->logMutation($instance, $user, TransactionAction::ADDED, $logTarget);

            return $instance;
        });
    }

    public function update(
        Model $instance,
        array $data,
        User $user,
        ?Model $logTarget = null,
    ): Model {
        return DB::transaction(function () use ($instance, $data, $user, $logTarget): Model {
            $payload = $this->imageService->prepareDataForPersistence($data, $instance, $instance);

            $instance->update($payload);
            $this->logMutation($instance, $user, TransactionAction::UPDATED, $logTarget);

            return $instance->refresh();
        });
    }

    public function delete(
        Model $instance,
        User $user,
        ?Model $logTarget = null,
    ): void {
        DB::transaction(function () use ($instance, $user, $logTarget): void {
            $this->logMutation($instance, $user, TransactionAction::DISPOSED, $logTarget);
            $instance->delete();
        });
    }

    private function logMutation(
        Model $instance,
        User $user,
        TransactionAction $action,
        ?Model $logTarget = null,
    ): void {
        $target = $logTarget ?? $instance;

        $this->transactionService->log(
            item: $target,
            user: $user,
            action: $action,
            quantity: $this->quantityService->extractQuantity($instance),
        );
    }
}
