<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Http\Controllers\Controller;
use App\Modules\Core\Requests\Role\StoreRoleRequest;
use App\Modules\Core\Requests\Role\UpdateRoleRequest;
use App\Modules\Core\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        Gate::authorize('manage-roles');

        return response()->json(['data' => $this->roleService->list()]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        Gate::authorize('manage-roles');

        $role = $this->roleService->create($request->validated());

        return response()->json(['data' => $this->roleService->roleArray($role)], 201);
    }

    public function show($id): JsonResponse
    {
        Gate::authorize('manage-roles');

        $role = $this->roleService->find($id);

        return response()->json(['data' => $this->roleService->roleArray($role)]);
    }

    public function update(UpdateRoleRequest $request, $id): JsonResponse
    {
        Gate::authorize('manage-roles');

        $role = $this->roleService->update($id, $request->validated());

        return response()->json(['data' => $this->roleService->roleArray($role)]);
    }

    public function destroy($id): JsonResponse
    {
        Gate::authorize('manage-roles');

        $this->roleService->delete($id);

        return response()->json(['message' => 'Role deleted successfully']);
    }

    // ─── Role ↔ Permissions ──────────────────────────────────────────────────

    public function permissions($id): JsonResponse
    {
        Gate::authorize('manage-roles');

        return response()->json(['data' => $this->roleService->getPermissions($id)]);
    }

    public function assignPermission(Request $request, $id): JsonResponse
    {
        Gate::authorize('manage-roles');

        $request->validate(['permission' => 'required']);

        $result = $this->roleService->assignPermission($id, $request->permission);

        return response()->json(['message' => "Permission '{$result['permission']}' assigned to role '{$result['role']}'"]);
    }

    public function revokePermission($id, $permission): JsonResponse
    {
        Gate::authorize('manage-roles');

        $result = $this->roleService->revokePermission($id, $permission);

        return response()->json(['message' => "Permission '{$result['permission']}' revoked from role '{$result['role']}'"]);
    }

    // ─── Role ↔ Users ────────────────────────────────────────────────────────

    public function users($id): JsonResponse
    {
        Gate::authorize('manage-roles');

        return response()->json(['data' => $this->roleService->getUsers($id)]);
    }

    public function assignToUser(Request $request, $id): JsonResponse
    {
        Gate::authorize('manage-roles');

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $result = $this->roleService->assignToUser($id, $request->user_id);

        return response()->json(['message' => "Role '{$result['role']}' assigned to user '{$result['user']}'"]);
    }

    public function revokeFromUser($id, $userId): JsonResponse
    {
        Gate::authorize('manage-roles');

        $result = $this->roleService->revokeFromUser($id, $userId);

        return response()->json(['message' => "Role '{$result['role']}' revoked from user '{$result['user']}'"]);
    }
}
