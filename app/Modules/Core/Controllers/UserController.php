<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Core\Requests\User\StoreUserRequest;
use App\Modules\Core\Requests\User\UpdateUserRequest;
use App\Modules\Core\Resources\UserResource;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{

    public function __construct(
        private readonly UserService $userService,
    )
    {}

    // Display and listing of users
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only([ 'search', 'role']);
        $perPage = min($request->integer('per_page', 15), 100);

        $user = $this->userService->getPaginatedUsers($filters, $perPage);
        return UserResource::collection($user);
    }

    // Display a single user

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('permissions'));
    }

    // Create a new user
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->create($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);

    }

    // Update an existing user
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $updatedUser = $this->userService->update($user, $request->validated());

        return new UserResource($updatedUser);
    }


    // Delete a user
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        return response()->json(['message' => 'User deleted successfully']);
    }
}
