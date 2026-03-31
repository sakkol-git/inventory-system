<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use App\Modules\Inventory\Models\Transaction;
use App\Modules\Inventory\Models\BorrowRecord;
use App\Modules\Inventory\Models\ChemicalUsageLog;
use App\Modules\Inventory\Models\MaintenanceRecord;
use App\Modules\Inventory\Models\UserDocument;
use App\Modules\Inventory\Models\Achievement;
use App\Modules\Inventory\Models\PlantSample;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'timezone',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function borrowRecords(): HasMany
    {
        return $this->hasMany(BorrowRecord::class);
    }

    public function activeBorrows(): HasMany
    {
        return $this->borrowRecords()->whereNull('returned_at');
    }

    public function chemicalUsageLogs(): HasMany
    {
        return $this->hasMany(ChemicalUsageLog::class);
    }

    public function performedMaintenance(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'performed_by');
    }

    // public function documents(): HasMany
    // {
    //     return $this->hasMany(UserDocument::class);
    // }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function contributedSamples(): HasMany
    {
        return $this->hasMany(PlantSample::class, 'contributor_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->hasRole('admin', 'api');
    }

    public function isLabManager(): bool
    {
        return $this->hasRole('lab-manager', 'api');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student', 'api');
    }

    // Eloquent factory override for module model namespace.
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    // JWT Authentication
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
