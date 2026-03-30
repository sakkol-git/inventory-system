<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

/**
 * HasImageValidation — shared validation rules for image upload fields.
 *
 * Provides a reusable set of validation rules for:
 *  • `image`     — uploaded file (jpg, jpeg, png, webp; max 2 MB)
 *  • `image_url` — external URL
 */
trait HasImageValidation
{
    /**
     * Validation rules for image fields.
     *
     * @return array<string, array<mixed>>
     */
    protected function imageRules(): array
    {
        return [
            'image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
