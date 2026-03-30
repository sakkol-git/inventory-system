<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    private string $guard = 'api';

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function list(): Collection
    {
        return Role::where('guard_name', $this->guard)
            ->with('permissions')
            ->get()
            ->map(fn (Role $r) => $this->roleArray($r));
    }

    public function create(array $data): Role
    {
        $role = Role::create(['name' => $data['name'], 'guard_name' => $this->guard]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function find(int $id): Role
    {
        return Role::where('guard_name', $this->guard)
            ->with('permissions')
            ->findOrFail($id);
    }

    public function update(int $id, array $data): Role
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($id);

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function delete(int $id): void
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($id);
        $role->delete();
    }

    // ─── Permissions ─────────────────────────────────────────────────────────

    public function getPermissions(int $roleId): Collection
    {
        $role = Role::where('guard_name', $this->guard)
            ->with('permissions')
            ->findOrFail($roleId);

        return $role->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]);
    }

    public function assignPermission(int $roleId, int|string $permission): array
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($roleId);
        $permissionModel = $this->resolvePermission($permission);
        $role->givePermissionTo($permissionModel);

        return ['role' => $role->name, 'permission' => $permissionModel->name];
    }

    public function revokePermission(int $roleId, int|string $permission): array
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($roleId);
        $permissionModel = $this->resolvePermission($permission);
        $role->revokePermissionTo($permissionModel);

        return ['role' => $role->name, 'permission' => $permissionModel->name];
    }

    // ─── Users ───────────────────────────────────────────────────────────────

    public function getUsers(int $roleId): Collection
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($roleId);

        return User::role($role->name, $this->guard)->get(['id', 'name', 'email', 'role']);
    }

    public function assignToUser(int $roleId, int $userId): array
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($roleId);
        $user = User::findOrFail($userId);
        $user->assignRole($role);

        return ['role' => $role->name, 'user' => $user->name];
    }

    public function revokeFromUser(int $roleId, int $userId): array
    {
        $role = Role::where('guard_name', $this->guard)->findOrFail($roleId);
        $user = User::findOrFail($userId);
        $user->removeRole($role);

        return ['role' => $role->name, 'user' => $user->name];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function roleArray(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'created_at' => $role->created_at,
        ];
    }

    private function resolvePermission(int|string $value): Permission
    {
        if (is_numeric($value)) {
            return Permission::where('guard_name', $this->guard)->findOrFail((int) $value);
        }

        return Permission::findByName((string) $value, $this->guard);
    }
}
