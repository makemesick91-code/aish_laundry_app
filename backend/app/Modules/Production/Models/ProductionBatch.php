<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A production batch (FR-074) — an outlet-scoped grouping of production items that
 * are processed together through ONE stage while each order remains individually
 * identifiable. Tenant scope is EXPLICIT (scopeForTenant at every call site),
 * never ambient. Membership and lifecycle transitions go through
 * ProductionBatchService, never a client-set status.
 *
 * A batch is OPEN or CLOSED. A CLOSED batch is immutable and cannot gain members
 * — enforced both by ProductionBatchService and by database triggers installed in
 * 2026_07_24_100000_create_production_tables.php (defence in depth, Rule 18).
 */
class ProductionBatch extends Model
{
    use HasOptimisticVersion;
    use HasUuids;

    protected $table = 'production_batches';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_CLOSED];

    protected $fillable = [
        'tenant_id', 'outlet_id', 'code', 'stage', 'status', 'version',
        'created_by_membership_id', 'closed_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionBatchItem::class, 'batch_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionBatchEvent::class, 'batch_id');
    }
}
