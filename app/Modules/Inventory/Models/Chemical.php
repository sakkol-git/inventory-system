<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\HasActivityLogging;
use App\Modules\Core\Concerns\HasImageUpload;
use App\Modules\Core\Concerns\HasTransactions;
use App\Modules\Inventory\Enums\ChemicalCategory;
use App\Modules\Inventory\Enums\DangerLevel;
use Database\Factories\ChemicalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Core\Concerns\EscapesSearchTerm;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chemical extends Model
{
    /** @use HasFactory<ChemicalFactory> */
    use EscapesSearchTerm, HasActivityLogging, HasFactory, HasImageUpload, HasTransactions, SoftDeletes;

    protected $table = 'chemicals';

    protected $fillable = [
        'common_name',
        'chemical_code',
        'category',
        'quantity',
        'storage_location',
        'expiry_date',
        'danger_level',
        'safety_measures',
        'description',
        'image_url',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'category' => ChemicalCategory::class,
            'danger_level' => DangerLevel::class,
            'expiry_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function batches(): HasMany
    {
        return $this->hasMany(ChemicalBatch::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ChemicalUsageLog::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /** Chemicals that are not expired and have stock. */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('quantity', '>', 0)
            ->where(function (Builder $q): void {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', now());
            });
    }

    public function scopeLowStock(Builder $query, int $threshold = 10): void
    {
        $query->where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0);
    }

    /** Chemicals expiring within the next N days. */
    public function scopeExpiringSoon(Builder $query, int $days = 30): void
    {
        $query->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now());
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $escaped = $this->escapeLike($term);

        $query->where(function (Builder $q) use ($escaped): void {
            $q->where('common_name', 'like', "%{$escaped}%")
                ->orWhere('chemical_code', 'like', "%{$escaped}%");
        });
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
