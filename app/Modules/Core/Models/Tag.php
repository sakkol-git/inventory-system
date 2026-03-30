<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use App\Modules\Research\Models\Experiment;
use App\Modules\Research\Models\Protocol;
use App\Modules\Research\Models\LabNotebook;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    // ─── Boot ────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    // ─── Polymorphic Relationships ──────────────────────────────────────────

    // public function experiments(): MorphToMany
    // {
    //     return $this->morphedByMany(Experiment::class, 'taggable')
    //         ->withTimestamps();
    // }

    // public function protocols(): MorphToMany
    // {
    //     return $this->morphedByMany(Protocol::class, 'taggable')
    //         ->withTimestamps();
    // }

    // public function notebooks(): MorphToMany
    // {
    //     return $this->morphedByMany(LabNotebook::class, 'taggable')
    //         ->withTimestamps();
    // }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Find a tag by name or create it.
     */
    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug($name);

        return self::firstOrCreate(
            ['slug' => $slug],
            ['name' => trim($name), 'slug' => $slug],
        );
    }

    /**
     * Resolve an array of tag names into an array of Tag IDs.
     *
     * @param  string[]  $names
     * @return int[]
     */
    public static function resolveNames(array $names): array
    {
        return collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => self::findOrCreateByName($name)->id)
            ->values()
            ->all();
    }
}
