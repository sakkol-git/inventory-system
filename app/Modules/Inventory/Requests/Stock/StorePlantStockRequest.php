<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Stock;

use App\Modules\Inventory\Enums\StockStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlantStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller via policies
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            // Relationships
            'plant_species_id' => ['required', 'integer', 'exists:plant_species,id'],
            'plant_variety_id' => ['nullable', 'integer', 'exists:plant_varieties,id'],
            'plant_sample_id' => ['nullable', 'integer', 'exists:plant_samples,id'],

            // Numbers (Unsigned means min:0)
            'quantity' => ['required', 'integer', 'min:0'],

            // Logical Check: Reserved cannot be less than 0, but ideally shouldn't exceed quantity
            'reserved_quantity' => ['required', 'integer', 'min:0', 'lte:quantity'],

            // Status
            'status' => ['required', Rule::enum(StockStatus::class)],
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
