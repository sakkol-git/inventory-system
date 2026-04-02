<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Stock;

use App\Modules\Inventory\Enums\StockStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller via policies
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'plant_species_id' => ['sometimes', 'integer', 'exists:plant_species,id'],
            'plant_variety_id' => ['nullable', 'integer', 'exists:plant_varieties,id'],
            'plant_sample_id' => ['nullable', 'integer', 'exists:plant_samples,id'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            // lte:quantity validates against the incoming payload value when both are present.
            // The controller also guards against reserved > quantity when only one field is sent.
            'reserved_quantity' => ['sometimes', 'integer', 'min:0', 'lte:quantity'],
            'status' => ['sometimes', 'required', Rule::enum(StockStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reserved_quantity.lte' => 'Reserved quantity cannot exceed the total quantity.',
        ];
    }
}
