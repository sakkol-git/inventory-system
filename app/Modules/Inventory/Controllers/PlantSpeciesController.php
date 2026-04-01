<?php


declare(strict_types=1);


namespace App\Modules\Inventory\Controllers;


use App\Modules\Core\Http\Controllers\Controller;


use App\Modules\Inventory\Requests\Species\StorePlantSpeciesRequest;
use App\Modules\Inventory\Requests\Species\UpdatePlantSpeciesRequest;
use App\Modules\Inventory\Resources\PlantSpeciesResource;
use App\Modules\Inventory\Models\PlantSpecies;
use App\Modules\Inventory\Services\InventoryCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;


class PlantSpeciesController extends Controller
{
   public function __construct(
       private readonly InventoryCrudService $crudService,
   ) {}



    // Listing Plant Species
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlantSpecies::class);

        $species = $this->crudService->listItems(
            modelClass: PlantSpecies::class,
            request: $request,
            perPage: 10,
            filterMap: ['family' => 'family'],
        );

        return PlantSpeciesResource::collection($species);
    }

    // Showing a single Plant Species
   public function show(PlantSpecies $plantSpecies): PlantSpeciesResource
   {
       $this->authorize('view', $plantSpecies);


       return new PlantSpeciesResource($plantSpecies);
   }

    // Creating a new Plant Species
    public function store(StorePlantSpeciesRequest $request): JsonResponse
    {
        $this->authorize('create', PlantSpecies::class);

        $data = $request->validated();

        $species = $this->crudService->create(
            modelClass: PlantSpecies::class,
            data: $data,
            user: auth('api')->user(),
        );

        return response()->json(new PlantSpeciesResource($species), 201);
    }

    // Updating a Plant Species
    public function update(UpdatePlantSpeciesRequest $request, PlantSpecies $plantSpecies): PlantSpeciesResource
    {
        $this->authorize('update', $plantSpecies);

        $data = $request->validated();

        $updatedSpecies = $this->crudService->update(
            instance: $plantSpecies,
            data: $data,
            user: auth('api')->user(),
        );

        return new PlantSpeciesResource($updatedSpecies);
    }

    public function destroy(PlantSpecies $plantSpecies): JsonResponse
    {
        $this->authorize('delete', $plantSpecies);

        // apply soft delete and log transaction
        $this->crudService->delete(
            instance: $plantSpecies,
            user: auth('api')->user(),
        );
        return response()->json(['message' => 'Plant species deleted successfully']);
    }


}





