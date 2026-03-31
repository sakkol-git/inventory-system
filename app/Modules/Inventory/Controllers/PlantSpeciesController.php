<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Inventory\Requests\Species\StorePlantSpeciesRequest;
use App\Modules\Inventory\Requests\Species\UpdatePlantSpeciesRequest;
use App\Modules\Inventory\Resources\PlantSpeciesResource;
use App\Modules\Inventory\Models\PlantSpecies;
use App\Modules\Inventory\Services\InventoryCrudService;
use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlantSpeciesController extends Controller
{
    public function __construct(
        private readonly InventoryCrudService $crudService,
        private readonly ImageUploadService $imageService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlantSpecies::class);
        $query = PlantSpecies::query();

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('family')) {
            $query->family($request->input('family'));
        }

        return PlantSpeciesResource::collection($query->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePlantSpeciesRequest $request): JsonResponse
    {
        $this->authorize('create', PlantSpecies::class);

        $data = $request->validated();
        $this->imageService->handleImageData($data, PlantSpecies::imageFolder());

        $species = $this->crudService->create(
            modelClass: PlantSpecies::class,
            data: $data,
            user: auth('api')->user(),
        );

        return (new PlantSpeciesResource($species))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PlantSpecies $plantSpecies): PlantSpeciesResource
    {
        $this->authorize('view', $plantSpecies);

        return new PlantSpeciesResource($plantSpecies);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePlantSpeciesRequest $request, PlantSpecies $plantSpecies): PlantSpeciesResource
    {
        $this->authorize('update', $plantSpecies);

        $data = $request->validated();
        $this->imageService->handleImageData($data, PlantSpecies::imageFolder(), $plantSpecies);

        $plantSpecies = $this->crudService->update(
            instance: $plantSpecies,
            data: $data,
            user: auth('api')->user(),
        );

        return new PlantSpeciesResource($plantSpecies);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlantSpecies $plantSpecies): JsonResponse
    {
        $this->authorize('delete', $plantSpecies);

        $this->crudService->delete(
            instance: $plantSpecies,
            user: auth('api')->user(),
        );

        return response()->json(['message' => 'Plant species deleted successfully.']);
    }


}
