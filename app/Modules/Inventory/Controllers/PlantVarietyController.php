<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Inventory\Models\PlantVariety;
use App\Modules\Inventory\Requests\Variety\StorePlantVarietyRequest;
use App\Modules\Inventory\Requests\Variety\UpdatePlantVarietyRequest;
use App\Modules\Inventory\Services\InventoryCrudService;
use App\Modules\Inventory\Resources\PlantVarietyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\JsonResponse;

class PlantVarietyController extends Controller
{

    // Constructor with any necessary dependencies (e.g., services)
    public function __construct(
        private readonly InventoryCrudService $crudService,
    )
    {}
    /**
     * Display a listing of plant varieties.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Authorization check (if needed)
        $this->authorize('viewAny', PlantVariety::class);

        $plantVariety = $this->crudService->listItems(
            modelClass: PlantVariety::class,
            request: $request,
            perPage: 10,
            with: ['plantSpecies'],
            filterMap: ['species_id' => 'plant_species_id'],
        );

        return PlantVarietyResource::collection($plantVariety);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlantVarietyRequest $request): JsonResponse
    {
        // authorization is handled in the FormRequest
        $this->authorize('create', PlantVariety::class);

        // Validated Request Data
        $data = $request->validated();

        // Create the Plant Variety using the InventoryCrudService
        $variety = $this->crudService->create(
            modelClass: PlantVariety::class,
            data: $data,
            user: auth('api')->user(),
        );

        return (new PlantVarietyResource($variety))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a single plant variety.
     */
    public function show(PlantVariety $plantVariety): PlantVarietyResource
    {
        // Authorization check
        $this->authorize('view', $plantVariety);

        // Eager load related species for the resource
        $plantVariety->load('plantSpecies');

        return new PlantVarietyResource($plantVariety);
    }

    /**
     * Update the specified plant variety.
     */
    public function update(UpdatePlantVarietyRequest $request, PlantVariety $plantVariety): PlantVarietyResource
    {
        // authorization is handled in the FormRequest
        $this->authorize('update', $plantVariety);

        // Validated Request Data
        $data = $request->validated();

        // Update the Plant Variety using the InventoryCrudService
        $updatedVariety = $this->crudService->update(
            instance: $plantVariety,
            data: $data,
            user: auth('api')->user(),
        );

        return new PlantVarietyResource($updatedVariety);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlantVariety $plantVariety): JsonResponse
    {
        // Authorization check
        $this->authorize('delete', $plantVariety);

        // Delete the Plant Variety using the InventoryCrudService
        $this->crudService->delete(
            instance: $plantVariety,
            user: auth('api')->user(),
        );

        return response()->json(['message' => 'Plant variety deleted successfully.']);
    }
}
