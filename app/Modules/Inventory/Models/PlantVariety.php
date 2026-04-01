<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\EscapesSearchTerm;
use App\Modules\Core\Concerns\HasActivityLogging;
use App\Modules\Core\Concerns\HasImageUpload;
use App\Modules\Core\Concerns\HasTransactions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class PlantVariety extends Model
{
    //
    use EscapesSearchTerm, HasActivityLogging, HasFactory, HasImageUpload, HasTransactions, SoftDeletes;

    protected $table = 'plant_varieties';

    protected $fillable = [
        'plant_species_id',
        'name',
        'variety_code',
        'description',
        'image_url',
        'image_path',
    ];

    // ─── Relationships ───────────────────────────────────────────────────────

    public function plantSpecies(): BelongsTo
    {
        return $this->belongsTo(PlantSpecies::class, 'plant_species_id');

    }
    public function plantSamples(): HasMany
    {
        return $this->hasMany(PlantSample::class, 'plant_variety_id');
    }
    public function plantStocks(): HasMany
    {
        return $this->hasMany(PlantStock::class, 'plant_variety_id');
    }

    // ─── Custom Methods ─────────────────────────────────────────────────────
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if(! $term) {
            return;
        }

        $escaped = $this->escapeLike($term);

        $query->where(function (Builder $q) use ($escaped):void {
            $q->where('name', 'ILIKE', "%{$escaped}%")
                ->orWhere('variety_code', 'ILIKE', "%{$escaped}%")
                ->orWhere('description', 'ILIKE', "%{$escaped}%");
        });
    }

}
