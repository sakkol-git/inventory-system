<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\HasActivityLogging;
use App\Modules\Core\Concerns\HasTransactions;
use App\Modules\Inventory\Enums\StockStatus;
use Database\Factories\PlantStockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantStock extends Model
{
    /** @use HasFactory<PlantStockFactory> */
    use HasActivityLogging, HasFactory, HasTransactions, SoftDeletes;

    protected $table = 'plant_stocks';

    // Mass assignable attributes
    protected $fillable = [
        'plant_species_id',
        'plant_variety_id',
        'plant_sample_id',
        'quantity',
        'reserved_quantity',
        'status',
    ];

    // Auto convert DB value to php types
    protected function casts(): array
    {
        return [
            'status' => StockStatus::class,
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }

    // ─── Computed Attributes ─────────────────────────────────────────────────

    /**
     * Available = total − reserved. Always >= 0 because DB uses unsignedInteger
     * and the update guard in the controller enforces reserved <= quantity.
     */
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function species(): BelongsTo
    {
        return $this->belongsTo(PlantSpecies::class, 'plant_species_id');
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(PlantVariety::class, 'plant_variety_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(PlantSample::class, 'plant_sample_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', StockStatus::AVAILABLE)
            ->where('quantity', '>', 0);
    }

    public function scopeLowStock(Builder $query, int $threshold = 10): void
    {
        $query->where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0);
    }
}
