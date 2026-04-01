<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantVarietyResource extends JsonResource
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
            // Expose FK so consumers can re-POST the same payload
            'plant_species_id' => $this->plant_species_id,
            'name' => $this->name,
            'variety_code' => $this->variety_code,
            'description' => $this->description,
            'image_url' => ImageUploadService::resolveImageUrl($this->image_path, $this->image_url),
            // Only embedded when the relationship was eager-loaded
            'plant_species' => new PlantSpeciesResource($this->whenLoaded('plantSpecies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
