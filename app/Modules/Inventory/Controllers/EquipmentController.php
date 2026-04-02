<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Inventory\Requests\Equipment\StoreEquipmentRequest;
use App\Modules\Inventory\Requests\Equipment\UpdateEquipmentRequest;
use App\Modules\Inventory\Resources\EquipmentResource;
use App\Modules\Inventory\Models\Equipment;
use App\Modules\Core\Contracts\ICrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    public function __construct(
        private readonly ICrudService $crudService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Equipment::class);
        $equipment = $this->crudService->listItems(
            modelClass: Equipment::class,
            request: $request,
            perPage: 10,
            filterMap: [
                'category' => 'category',
                'status' => 'status',
            ],
            booleanScopeMap: [
                'available_only' => 'available',
                'borrowed_only' => 'borrowed',
            ],
        );

        return EquipmentResource::collection($equipment);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);

        $data = $request->validated();

        $equipment = $this->crudService->create(
            modelClass: Equipment::class,
            data: $data,
            user: auth('api')->user(),
        );

        return (new EquipmentResource($equipment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Equipment $equipment): EquipmentResource
    {
        $this->authorize('view', $equipment);

        return new EquipmentResource($equipment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $this->authorize('update', $equipment);

        $data = $request->validated();

        $equipment = $this->crudService->update(
            instance: $equipment,
            data: $data,
            user: auth('api')->user(),
        );

        return new EquipmentResource($equipment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Equipment $equipment): JsonResponse
    {
        $this->authorize('delete', $equipment);

        $this->crudService->delete(
            instance: $equipment,
            user: auth('api')->user(),
        );

        return response()->json(['message' => 'Equipment deleted successfully.']);
    }
}
