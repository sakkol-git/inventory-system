<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;
use App\Modules\Inventory\Models\PlantSample;
use App\Modules\Inventory\Requests\Sample\StorePlantSampleRequest;
use App\Modules\Inventory\Requests\Sample\UpdatePlantSampleRequest;
use App\Modules\Core\Contracts\ICrudService;
use Illuminate\Http\Request;
use App\Modules\Inventory\Resources\PlantSampleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\JsonResponse;

class PlantSampleController extends Controller
{

    public function __construct(
        private readonly ICrudService $crudService,
    )
    {}
    /**
     * Display a listing of plant sameples.
     */
 public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlantSample::class);
        $samples = $this->crudService->listItems(
            modelClass: PlantSample::class,
            request: $request,
            perPage: 10,
            with: ['plantSpecies', 'plantVariety'],
            filterMap: [
                'status' => 'status',
                'species_id' => 'plant_species_id',
            ],
        );

        return PlantSampleResource::collection($samples);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlantSampleRequest $request): JsonResponse
    {
        //Authorization check
        $this->authorize('create', PlantSample::class);

        // Validated Request Data
        $data = $request->validated();

        // Create the Plant Sample using the CrudService
        $plantSample = $this->crudService->create(
            modelClass: PlantSample::class,
            data: $data,
            user: auth('api')->user(),
        );

        return (new PlantSampleResource($plantSample))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PlantSample $plantSample): PlantSampleResource
    {
        $this->authorize('view', $plantSample);

        return new PlantSampleResource($plantSample);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlantSampleRequest $request, PlantSample $plantSample): PlantSampleResource
    {
        //Auth check
        $this->authorize('update', $plantSample);

        // Validated Request Data
        $data = $request->validated();

        // update the Plant Sample using the CrudService
        $updatedSample = $this->crudService->update(
            instance: $plantSample,
            data: $data,
            user: auth('api')->user(),
        );

        return new PlantSampleResource($updatedSample);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlantSample $plantSample): JsonResponse
    {
        //Authorization check
        $this->authorize('delete', $plantSample);

        $this->crudService->delete(
            instance: $plantSample,
            user: auth('api')->user(),
        );

        return response()->json([
            'message' => 'Plant sample deleted successfully.',
        ], 204);
    }
}
