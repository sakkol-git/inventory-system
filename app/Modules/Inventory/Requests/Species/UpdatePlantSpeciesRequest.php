<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Species;

use App\Modules\Core\Concerns\HasImageValidation;
use App\Modules\Inventory\Enums\PlantGrowthType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantSpeciesRequest extends FormRequest
{
    use HasImageValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return $this->user()->hasPermissionTo('plants.edit', 'api');
        return true; // Authorization is handled in the controller via policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'common_name' => ['sometimes', 'required', 'string', 'max:255'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'scientific_name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('plant_species', 'scientific_name')
                    ->ignore($this->route('plantSpecies')?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'family' => ['nullable', 'string', 'max:255'],
            'growth_type' => ['sometimes', 'required', Rule::enum(PlantGrowthType::class)],
            'native_region' => ['nullable', 'string', 'max:255'],
            'propagation_method' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            ...$this->imageRules(),
        ];
    }
}
