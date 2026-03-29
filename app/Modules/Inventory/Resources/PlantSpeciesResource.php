<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantSpeciesResource extends JsonResource
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
            'common_name' => $this->common_name,
            'khmer_name' => $this->khmer_name,
            'scientific_name' => $this->scientific_name,
            'family' => $this->family,
            // Return the enum value (string) if set
            'growth_type' => $this->growth_type?->value,
            'native_region' => $this->native_region,
            'propagation_method' => $this->propagation_method,
            'description' => $this->description,
            'image_url' => ImageUploadService::resolveImageUrl($this->image_path, $this->image_url),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
