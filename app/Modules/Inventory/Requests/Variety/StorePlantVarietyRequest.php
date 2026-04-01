<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Variety;

use App\Modules\Core\Concerns\HasImageValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlantVarietyRequest extends FormRequest
{
    use HasImageValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('plants.create', 'api') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'plant_species_id' => ['required', 'integer', 'exists:plant_species,id'],
            'name' => ['required', 'string', 'max:255'],
            'variety_code' => [
                'required', 'string', 'max:100',
                Rule::unique('plant_varieties', 'variety_code')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            ...$this->imageRules(),
        ];
    }
}
