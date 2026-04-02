<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\Crud;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CrudListService
{
    /** @var array<class-string<Model>, array<int, string>> */
    private static array $allowedSortColumns = [];

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
        /** @var Builder $query */
        $query = $modelClass::query()->with($with);

        $this->applySearch($query, $request);
        $this->applyFilters($query, $request, $filterMap);
        $this->applyValueScopes($query, $request, $valueScopeMap);
        $this->applyBooleanScopes($query, $request, $booleanScopeMap);
        $this->applySorting($query, $request, $defaultSortBy, $defaultSortDir);

        return $query->paginate($request->integer('per_page', $perPage));
    }

    private function applySearch(Builder $query, Request $request): void
    {
        if (! $request->filled('search') || ! $query->hasNamedScope('search')) {
            return;
        }

        $query->search($request->input('search'));
    }

    /** @param array<string, string> $filterMap */
    private function applyFilters(Builder $query, Request $request, array $filterMap): void
    {
        foreach ($filterMap as $inputKey => $column) {
            if ($request->filled($inputKey)) {
                $query->where($column, $request->input($inputKey));
            }
        }
    }

    /** @param array<string, string> $valueScopeMap */
    private function applyValueScopes(Builder $query, Request $request, array $valueScopeMap): void
    {
        foreach ($valueScopeMap as $inputKey => $scope) {
            if ($request->filled($inputKey) && $query->hasNamedScope($scope)) {
                $query->{$scope}($request->input($inputKey));
            }
        }
    }

    /** @param array<string, string> $booleanScopeMap */
    private function applyBooleanScopes(Builder $query, Request $request, array $booleanScopeMap): void
    {
        foreach ($booleanScopeMap as $inputKey => $scope) {
            if ($request->boolean($inputKey) && $query->hasNamedScope($scope)) {
                $query->{$scope}();
            }
        }
    }

    private function applySorting(Builder $query, Request $request, ?string $defaultSortBy, string $defaultSortDir): void
    {
        $sortBy = (string) $request->input('sort_by', $defaultSortBy);
        $sortDir = strtolower((string) $request->input('sort_dir', $defaultSortDir)) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = $this->getAllowedSorts($query->getModel());

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        }
    }

    /** @return array<int, string> */
    private function getAllowedSorts(Model $model): array
    {
        $modelClass = $model::class;

        if (! isset(self::$allowedSortColumns[$modelClass])) {
            self::$allowedSortColumns[$modelClass] = array_values(array_unique(array_merge(
                $model->getFillable(),
                [$model->getKeyName(), 'created_at', 'updated_at'],
            )));
        }

        return self::$allowedSortColumns[$modelClass];
    }
}
