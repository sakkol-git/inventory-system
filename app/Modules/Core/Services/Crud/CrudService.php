<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\Crud;

use App\Modules\Core\Contracts\ICrudService;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\Crud\CrudListService;
use App\Modules\Core\Services\Crud\CrudMutationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CrudService implements ICrudService
{
    public function __construct(
        private readonly CrudListService $listService,
        private readonly CrudMutationService $mutationService,
    ) {
    }

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
        return $this->listService->listItems(
            modelClass: $modelClass,
            request: $request,
            perPage: $perPage,
            with: $with,
            filterMap: $filterMap,
            valueScopeMap: $valueScopeMap,
            booleanScopeMap: $booleanScopeMap,
            defaultSortBy: $defaultSortBy,
            defaultSortDir: $defaultSortDir,
        );
    }

    public function create(
        string $modelClass,
        array $data,
        User $user,
        ?Model $logTarget = null,
    ): Model {
        return $this->mutationService->create(
            modelClass: $modelClass,
            data: $data,
            user: $user,
            logTarget: $logTarget,
        );
    }

    public function update(
        Model $instance,
        array $data,
        User $user,
        ?Model $logTarget = null,
    ): Model {
        return $this->mutationService->update(
            instance: $instance,
            data: $data,
            user: $user,
            logTarget: $logTarget,
        );
    }

    public function delete(
        Model $instance,
        User $user,
        ?Model $logTarget = null,
    ): void {
        $this->mutationService->delete(
            instance: $instance,
            user: $user,
            logTarget: $logTarget,
        );
    }
}
