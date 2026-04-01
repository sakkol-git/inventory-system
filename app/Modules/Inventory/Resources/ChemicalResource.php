<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChemicalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'common_name' => $this->common_name,
            'chemical_code' => $this->chemical_code,
            'category' => $this->category?->value,
            'quantity' => $this->quantity,
            'storage_location' => $this->storage_location,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'danger_level' => $this->danger_level?->value,
            'safety_measures' => $this->safety_measures,
            'description' => $this->description,
            'image_url' => ImageUploadService::resolveImageUrl($this->image_path, $this->image_url),
            'is_expired' => $this->is_expired,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
