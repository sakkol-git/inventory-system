<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Chemical;

use App\Modules\Core\Concerns\HasImageValidation;
use App\Modules\Inventory\Enums\ChemicalCategory;
use App\Modules\Inventory\Enums\DangerLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChemicalRequest extends FormRequest
{
    use HasImageValidation;

    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('chemicals.create', 'api') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'common_name' => ['required', 'string', 'max:255'],
            'chemical_code' => ['nullable', 'string', 'max:100', Rule::unique('chemicals', 'chemical_code')->whereNull('deleted_at')],
            'category' => ['required', Rule::enum(ChemicalCategory::class)],
            'quantity' => ['required', 'integer', 'min:0'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'danger_level' => ['required', Rule::enum(DangerLevel::class)],
            'safety_measures' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            ...$this->imageRules(),
        ];
    }
}
