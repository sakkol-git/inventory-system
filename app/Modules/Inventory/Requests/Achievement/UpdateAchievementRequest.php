<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests\Achievement;

use App\Modules\Core\Concerns\HasImageValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAchievementRequest extends FormRequest
{
    use HasImageValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'achievement_name' => ['sometimes','required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'criteria_type' => ['sometimes', 'required', 'string', 'max:100'],
            'criteria_value' => ['sometimes', 'required', 'integer', 'min:1'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            ...$this->imageRules(),
        ];
    }
}
