<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;

class UserPolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('admin', 'api');
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->hasPermissionTo('users.view', 'api');
    }

    public function view(User $user, User $model): bool
    {
        return $this->isAdmin($user) || $user->hasPermissionTo('users.view', 'api');
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->hasPermissionTo('users.create', 'api');
    }

    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user) || $user->hasPermissionTo('users.edit', 'api');
    }

    public function delete(User $user, User $model): bool
    {
        return $this->isAdmin($user) || $user->hasPermissionTo('users.delete', 'api');
    }
}
