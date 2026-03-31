<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;

class UserPolicy
{


    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view', 'api');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.view', 'api');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.create', 'api');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.edit', 'api');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.delete', 'api');
    }
}
