<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Inventory\Requests\Stock\StorePlantStockRequest;
use App\Modules\Inventory\Requests\Stock\UpdatePlantStockRequest;
use App\Modules\Inventory\Resources\PlantStockResource;
use App\Modules\Inventory\Models\PlantStock;
use App\Modules\Inventory\Services\InventoryCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlantStockController extends Controller
{
    public function __construct(
        private readonly InventoryCrudService $crudService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlantStock::class);
        $stocks = $this->crudService->listItems(
            modelClass: PlantStock::class,
            request: $request,
            perPage: 10,
            with: ['species', 'variety', 'sample'],
            filterMap: [
                'species_id' => 'plant_species_id',
                'status' => 'status',
            ],
        );

        return PlantStockResource::collection($stocks);
    }

    public function store(StorePlantStockRequest $request): JsonResponse
    {
        $this->authorize('create', PlantStock::class);

        $stock = $this->crudService->create(
            modelClass: PlantStock::class,
            data: $request->validated(),
            user: auth('api')->user(),
        );

        $stock->load(['species', 'variety', 'sample']);

        return (new PlantStockResource($stock))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PlantStock $plantStock): PlantStockResource
    {
        $this->authorize('view', $plantStock);

        $plantStock->load(['species', 'variety', 'sample']);

        return new PlantStockResource($plantStock);
    }

    public function update(UpdatePlantStockRequest $request, PlantStock $plantStock): PlantStockResource
    {
        $this->authorize('update', $plantStock);

        $data = $request->validated();

        // ── Inventory Guard ─────────────────────────────────────────────────
        // When only one of the two fields is sent, cross-check against the
        // persisted value to ensure reserved never exceeds total quantity.
        $newQuantity = (int) ($data['quantity'] ?? $plantStock->quantity);
        $newReservedQuantity = (int) ($data['reserved_quantity'] ?? $plantStock->reserved_quantity);

        if ($newReservedQuantity > $newQuantity) {
            abort(422, 'Reserved quantity cannot exceed the total quantity.');
        }

        $plantStock = $this->crudService->update(
            instance: $plantStock,
            data: $data,
            user: auth('api')->user(),
        );

        $plantStock->load(['species', 'variety', 'sample']);

        return new PlantStockResource($plantStock);
    }

    public function destroy(PlantStock $plantStock): JsonResponse
    {
        $this->authorize('delete', $plantStock);

        $this->crudService->delete(
            instance: $plantStock,
            user: auth('api')->user(),
        );

        return response()->json(['message' => 'Stock record deleted successfully.']);
    }
}
