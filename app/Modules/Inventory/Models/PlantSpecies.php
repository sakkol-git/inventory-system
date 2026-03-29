<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\HasActivityLogging;
use App\Modules\Core\Concerns\HasImageUpload;
use App\Modules\Core\Concerns\HasTransactions;
use App\Enums\PlantGrowthType;
use Database\Factories\PlantSpeciesFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Concerns\EscapesSearchTerm;
use App\Modules\Research\Models\Experiment;

class PlantSpecies extends Model
{
    /** @use HasFactory<PlantSpeciesFactory> */
    use EscapesSearchTerm, HasActivityLogging, HasFactory, HasImageUpload, HasTransactions, SoftDeletes;

    protected $table = 'plant_species';

    protected $fillable = [
        'common_name',
        'khmer_name',
        'scientific_name',
        'family',
        'growth_type',
        'native_region',
        'propagation_method',
        'description',
        'image_url',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'growth_type' => PlantGrowthType::class,
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function varieties(): HasMany
    {
        return $this->hasMany(PlantVariety::class, 'plant_species_id');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(PlantSample::class, 'plant_species_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(PlantStock::class, 'plant_species_id');
    }

    // public function experiments(): HasMany
    // {
    //     return $this->hasMany(Experiment::class, 'plant_species_id');
    // }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeFamily(Builder $query, string $family): void
    {
        $query->where('family', $family);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $escaped = $this->escapeLike($term);

        $query->where(function (Builder $q) use ($escaped): void {
            $q->where('common_name', 'like', "%{$escaped}%")
                ->orWhere('scientific_name', 'like', "%{$escaped}%")
                ->orWhere('khmer_name', 'like', "%{$escaped}%");
        });
    }
}
