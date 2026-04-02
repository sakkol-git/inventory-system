<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Modules\Inventory\Models\PlantSample;
use App\Modules\Core\Models\User;

class PlantSamplePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('plants.view', 'api');
    }

    public function view(User $user, PlantSample $plantSample): bool
    {
        return $user->hasPermissionTo('plants.view', 'api');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('plants.create', 'api');
    }

    public function update(User $user, PlantSample $plantSample): bool
    {
        return $user->hasPermissionTo('plants.edit', 'api');
    }

    public function delete(User $user, PlantSample $plantSample): bool
    {
        return $user->hasPermissionTo('plants.delete', 'api');
    }
}
