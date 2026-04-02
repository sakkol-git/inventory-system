<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\Crud;

use Illuminate\Database\Eloquent\Model;

class CrudQuantityService
{
    public function extractQuantity(Model $instance): ?float
    {
        $quantity = $instance->quantity ?? null;

        return is_numeric($quantity) ? (float) $quantity : null;
    }
}
