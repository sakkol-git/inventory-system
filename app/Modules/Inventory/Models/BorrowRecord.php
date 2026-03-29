<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Concerns\HasActivityLogging;
use App\Enums\BorrowStatus;
use Database\Factories\BorrowRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Modules\Core\Models\User;
use HasActivityLogging as GlobalHasActivityLogging;

class BorrowRecord extends Model
{
    /** @use HasFactory<BorrowRecordFactory> */
    use HasActivityLogging, HasFactory;

    protected $table = 'borrow_records';

    protected $fillable = [
        'user_id',
        'borrowable_type',
        'borrowable_id',
        'quantity',
        'status',
        'borrowed_at',
        'due_at',
        'returned_at',
        'reviewed_by',
        'reviewed_at',
        'rejected_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => BorrowStatus::class,
            'quantity' => 'integer',
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    /**
     * The item being borrowed (Equipment, Chemical, PlantSample, etc.).
     * Polymorphic — a single borrow_records table works for every inventory type.
     */
    public function borrowable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The user who borrowed the item. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The user who reviewed (approved or rejected) the borrow request. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /** Active borrows that haven't been returned. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('returned_at')
            ->where('status', '!=', BorrowStatus::RETURNED);
    }

    /** Overdue: past due_at and not yet returned. */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('returned_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    /** Borrows for a specific polymorphic type. */
    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('borrowable_type', $type);
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->returned_at === null
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function getIsReturnedAttribute(): bool
    {
        return $this->returned_at !== null;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === BorrowStatus::PENDING;
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->status === BorrowStatus::REJECTED;
    }
}
