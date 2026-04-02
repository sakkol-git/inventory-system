<?php

declare(strict_types=1);

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface ICrudService
{
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
    ): LengthAwarePaginator;

    public function create(
        string $modelClass,
        array $data,
        User $user,
        ?Model $logTarget = null,
    ): Model;

    public function update(
        Model $instance,
        array $data,
        User $user,
        ?Model $logTarget = null,
    ): Model;

    public function delete(
        Model $instance,
        User $user,
        ?Model $logTarget = null,
    ): void;
}
