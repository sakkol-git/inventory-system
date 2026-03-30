<?php

namespace App\Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTransactions
{
    public function transactions(): MorphToMany
    {
        return $this->morphToMany();
    }
}
