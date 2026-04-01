<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\LabLocation;
use App\Modules\Inventory\Enums\SampleStatus;
use App\Modules\Core\Concerns\EscapesSearchTerm;
use App\Modules\Core\Concerns\HasActivityLogging;
use App\Modules\Core\Concerns\HasImageUpload;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class PlantSample extends Model
{
    // traits
    use EscapesSearchTerm, HasActivityLogging, HasFactory, HasImageUpload, HasAttributes, SoftDeletes;


    // table name
    protected $table = 'plant_samples';


    // fillable fields
    protected $fillable = [
        'plant_species_id',
        'plant_variety_id',
        'contributor_id',
        'sample_name',
        'sample_code',
        'owner_name',
        'department',
        'origin_location',
        'brought_at',
        'lab_location',
        'status',
        'description',
        'image_url',
        'image_path',
        'quantity',
    ];

    // casts
    protected function casts(): array
    {
        return [
            'brought_at' => 'date',
            'lab_location' => LabLocation::class,
            'status' => SampleStatus::class,
            'quantity' => 'integer',
        ];
    }

    // relationships
    public function plantSpecies():BelongsTo
    {
        return $this->belongsTo(PlantSpecies::class, 'plant_species_id');
    }
    public function plantVariety():BelongsTo
    {
        return $this->belongsTo(PlantVariety::class, 'plant_variety_id');
    }
    public function contributor():BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }
    public function stocks(): HasMany
    {
        return $this->hasMany(PlantStock::class, 'plant_sample_id');
    }

    // Scopes Search
     public function scopeSearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $escaped = $this->escapeLike($term);

        $query->where(function (Builder $q) use ($escaped): void {
            $q->where('sample_name', 'like', "%{$escaped}%")
                ->orWhere('sample_code', 'like', "%{$escaped}%");
        });
    }
}
