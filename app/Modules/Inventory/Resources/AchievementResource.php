<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Core\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'achievement_name' => $this->achievement_name,
            'description' => $this->description,
            'criteria' => [
                'type' => $this->criteria_type,
                'value' => $this->criteria_value,
            ],
            'icon' => $this->icon,
            'image' => ImageUploadService::resolveImageUrl($this->image_path, $this->image_url),
            'assigned_user_ids' => $this->whenLoaded('users', fn () => $this->users->pluck('id')->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
