<?php

declare(strict_types=1);

namespace App\Modules\Core\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate::authorize('manage-roles') is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:100', 'unique:roles,name,' . $roleId],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
