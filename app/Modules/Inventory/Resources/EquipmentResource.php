<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_name' => $this->equipment_name,
            'equipment_code' => $this->equipment_code,
            'category' => $this->category?->value,
            'status' => $this->status?->value,
            'condition' => $this->condition?->value,
            'location' => $this->location,
            'manufacturer' => $this->manufacturer,
            'model_name' => $this->model_name,
            'serial_number' => $this->serial_number,
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'purchase_price' => $this->purchase_price,
            'description' => $this->description,
            'image_url' => ImageUploadService::resolveImageUrl($this->image_path, $this->image_url),
            'is_borrowable' => $this->is_borrowable,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
