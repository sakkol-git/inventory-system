<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\TransactionAction;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Core\Models\User;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'transactionable_type',
        'transactionable_id',
        'action',
        'quantity',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'action' => TransactionAction::class,
            'quantity' => 'decimal:2',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    /**
     * The item this transaction is about (PlantStock, Chemical, Equipment, etc.).
     * This is the core of the polymorphic design — one Transaction table
     * serves ALL inventory types, avoiding N separate log tables.
     */
    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The user who performed the action. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForAction(Builder $query, TransactionAction $action): void
    {
        $query->where('action', $action);
    }

    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('transactionable_type', $type);
    }

    public function scopeRecent(Builder $query, int $days = 7): void
    {
        $query->where('created_at', '>=', now()->subDays($days));
    }
}
