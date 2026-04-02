<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Modules\Inventory\Models\Equipment;
use App\Modules\Core\Models\User;

class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('equipment.view', 'api');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->hasPermissionTo('equipment.view', 'api');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('equipment.create', 'api');
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->hasPermissionTo('equipment.edit', 'api');
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $user->hasPermissionTo('equipment.delete', 'api');
    }
}
