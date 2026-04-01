<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantStockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory' => [
                'total' => $this->quantity,
                'reserved' => $this->reserved_quantity,
                // Computed by model accessor — guaranteed >= 0
                'net_available' => $this->available_quantity,
                'status' => $this->status?->value,
            ],
            // Relationships — only present when eager-loaded (prevents N+1)
            'relations' => [
                'species' => new PlantSpeciesResource($this->whenLoaded('species')),
                'variety' => new PlantVarietyResource($this->whenLoaded('variety')),
                'sample' => new PlantSampleResource($this->whenLoaded('sample')),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
