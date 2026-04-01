<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantSampleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identity' => [
                'name' => $this->sample_name,
                'code' => $this->sample_code,
                'status' => $this->status?->value,
            ],
            // Relationships — only present when eager-loaded (prevents N+1)
            'relationships' => [
                'species' => new PlantSpeciesResource($this->whenLoaded('plantSpecies')),
                'variety' => new PlantVarietyResource($this->whenLoaded('plantVariety')),
            ],
            'details' => [
                'owner' => $this->owner_name,
                'department' => $this->department,
                'origin' => $this->origin_location,
                'quantity' => $this->quantity,
            ],
            'lab_info' => [
                'brought_at' => $this->brought_at?->format('Y-m-d'),
                'location' => $this->lab_location?->value,
            ],
            'meta' => [
                'description' => $this->description,
                'image' => ImageUploadService::resolveImageUrl($this->image_path, $this->image_url),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
        ];
    }
}
