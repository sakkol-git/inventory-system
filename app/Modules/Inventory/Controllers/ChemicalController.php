<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Core\Http\Controllers\Controller;

use App\Modules\Inventory\Requests\Chemical\StoreChemicalRequest;
use App\Modules\Inventory\Requests\Chemical\UpdateChemicalRequest;
use App\Modules\Inventory\Resources\ChemicalResource;
use App\Modules\Inventory\Models\Chemical;
use App\Modules\Inventory\Services\InventoryCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChemicalController extends Controller
{
    public function __construct(
        private readonly InventoryCrudService $crudService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Chemical::class);
        $chemicals = $this->crudService->listItems(
            modelClass: Chemical::class,
            request: $request,
            perPage: 10,
            filterMap: [
                'category' => 'category',
            ],
            booleanScopeMap: [
                'available_only' => 'available',
                'expired_only' => 'expired',
                'low_stock' => 'lowStock',
                'expiring_soon' => 'expiringSoon',
            ],
        );

        return ChemicalResource::collection($chemicals);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChemicalRequest $request): JsonResponse
    {
        $this->authorize('create', Chemical::class);

        $data = $request->validated();

        $chemical = $this->crudService->create(
            modelClass: Chemical::class,
            data: $data,
            user: auth('api')->user(),
        );

        return (new ChemicalResource($chemical))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Chemical $chemical): ChemicalResource
    {
        $this->authorize('view', $chemical);

        return new ChemicalResource($chemical);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChemicalRequest $request, Chemical $chemical): ChemicalResource
    {
        $this->authorize('update', $chemical);

        $data = $request->validated();

        $chemical = $this->crudService->update(
            instance: $chemical,
            data: $data,
            user: auth('api')->user(),
        );

        return new ChemicalResource($chemical);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Chemical $chemical): JsonResponse
    {
        $this->authorize('delete', $chemical);

        $this->crudService->delete(
            instance: $chemical,
            user: auth('api')->user(),
        );

        return response()->json(['message' => 'Chemical deleted successfully.']);
    }
}
