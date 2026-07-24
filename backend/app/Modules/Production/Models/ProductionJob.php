<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A production job — the internal work lifecycle for one order
 * (PRODUCTION_STATE_MACHINE.md). Tenant scope is EXPLICIT (scopeForTenant at
 * every call site), never ambient. State transitions go through
 * ProductionRegistry, never a client-set status.
 */
class ProductionJob extends Model
{
    use HasOptimisticVersion;
    use HasUuids;

    protected $table = 'production_jobs';

    public const STATE_CREATED = 'CREATED';
    public const STATE_IN_PROGRESS = 'IN_PROGRESS';
    public const STATE_BLOCKED = 'BLOCKED';
    public const STATE_AWAITING_QC = 'AWAITING_QC';
    public const STATE_REWORK_IN_PROGRESS = 'REWORK_IN_PROGRESS';
    public const STATE_CLOSED = 'CLOSED';
    public const STATE_ABANDONED = 'ABANDONED';

    public const STATES = [
        self::STATE_CREATED, self::STATE_IN_PROGRESS, self::STATE_BLOCKED,
        self::STATE_AWAITING_QC, self::STATE_REWORK_IN_PROGRESS,
        self::STATE_CLOSED, self::STATE_ABANDONED,
    ];

    public const TERMINAL_STATES = [self::STATE_CLOSED, self::STATE_ABANDONED];

    protected $fillable = [
        'tenant_id', 'outlet_id', 'order_id', 'state', 'version',
        'block_reason_code', 'block_reason',
        'created_by_membership_id', 'updated_by_membership_id',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->state, self::TERMINAL_STATES, true);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionItem::class, 'job_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionEvent::class, 'job_id');
    }
}
