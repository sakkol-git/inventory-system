<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Chemical;

use App\Modules\Core\Concerns\HasImageValidation;
use App\Modules\Inventory\Enums\ChemicalCategory;
use App\Modules\Inventory\Enums\DangerLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChemicalRequest extends FormRequest
{
    use HasImageValidation;

    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('chemicals.edit', 'api') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'common_name' => ['sometimes', 'required', 'string', 'max:255'],
            'chemical_code' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('chemicals', 'chemical_code')
                    ->ignore($this->route('chemical')?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'category' => ['sometimes', Rule::enum(ChemicalCategory::class)],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'danger_level' => ['sometimes', Rule::enum(DangerLevel::class)],
            'safety_measures' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            ...$this->imageRules(),
        ];
    }
}
