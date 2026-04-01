<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Achievement;
use App\Modules\Inventory\Requests\Achievement\StoreAchievementRequest;
use App\Modules\Inventory\Requests\Achievement\UpdateAchievementRequest;
use App\Modules\Inventory\Resources\AchievementResource;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Modules\Inventory\Services\AchievementAssignmentService;
use App\Modules\Inventory\Services\InventoryCrudService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AchievementController extends Controller
{
    public function __construct(
        private readonly InventoryCrudService $crudService,
        private readonly AchievementAssignmentService $assignmentService,
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Achievement::class);

        $achievements = $this->crudService->listItems(
            modelClass: Achievement::class,
            request: $request,
            perPage: 10,
            with: ['users:id'],
        );

        return AchievementResource::collection($achievements);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(StoreAchievementRequest $request): JsonResponse
    {
        $this->authorize('create', Achievement::class);

        $data = $request->validated();

        $achievement = $this->assignmentService->createAchievement(
            data: $data,
            actor: auth('api')->user(),
        );

        return response()->json(new AchievementResource($achievement), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Achievement $achievement): AchievementResource
    {
        $this->authorize('view', $achievement);

        return new AchievementResource($achievement->load('users:id'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAchievementRequest $request, Achievement $achievement): AchievementResource
    {
        $this->authorize('update', $achievement);

        $data = $request->validated();

        $updatedAchievement = $this->assignmentService->updateAchievement(
            achievement: $achievement,
            data: $data,
            actor: auth('api')->user(),
        );

        return new AchievementResource($updatedAchievement);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Achievement $achievement): JsonResponse
    {
        $this->authorize('delete', $achievement);

        $this->crudService->delete(
            instance: $achievement,
            user: auth('api')->user(),
        );

        return response()->json(null, 204);
    }
}
