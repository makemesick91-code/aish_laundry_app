<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An append-only production batch event (FR-074). The table refuses UPDATE and
 * DELETE at the database boundary; this model never updates a row. The
 * (tenant_id, client_reference) partial unique index is the server-side
 * idempotency key for batch commands (Rule 07/20), and the ordered stream of
 * rows for one batch is its membership timeline.
 */
class ProductionBatchEvent extends Model
{
    use HasUuids;

    protected $table = 'production_batch_events';

    public const UPDATED_AT = null; // append-only: never updated

    public const TYPE_CREATED = 'BatchCreated';
    public const TYPE_UPDATED = 'BatchUpdated';
    public const TYPE_ITEM_ADDED = 'BatchItemAdded';
    public const TYPE_ITEM_REMOVED = 'BatchItemRemoved';
    public const TYPE_CLOSED = 'BatchClosed';

    protected $fillable = [
        'tenant_id', 'batch_id', 'type', 'actor_membership_id',
        'payload', 'client_reference', 'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
