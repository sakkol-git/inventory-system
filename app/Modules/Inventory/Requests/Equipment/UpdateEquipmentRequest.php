<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Equipment;

use App\Modules\Inventory\Enums\EquipmentCategory;
use App\Modules\Inventory\Enums\EquipmentCondition;
use App\Modules\Inventory\Enums\EquipmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'equipment_name' => ['sometimes', 'required', 'string', 'max:255'],
            'equipment_code' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('equipment', 'equipment_code')
                    ->ignore($this->route('equipment')?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'category' => ['sometimes', Rule::enum(EquipmentCategory::class)],
            'status' => ['sometimes', Rule::enum(EquipmentStatus::class)],
            'condition' => ['sometimes', Rule::enum(EquipmentCondition::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model_name' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
