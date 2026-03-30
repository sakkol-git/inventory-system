<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Modules\Inventory\Models\PlantSpecies;
use App\Modules\Core\Models\User;

class PlantSpeciesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('plants.view', 'api');
    }

    public function view(User $user, PlantSpecies $plantSpecies): bool
    {
        return $user->hasPermissionTo('plants.view', 'api');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('plants.create', 'api');
    }

    public function update(User $user, PlantSpecies $plantSpecies): bool
    {
        return $user->hasPermissionTo('plants.edit', 'api');
    }

    public function delete(User $user, PlantSpecies $plantSpecies): bool
    {
        return $user->hasPermissionTo('plants.delete', 'api');
    }
}
