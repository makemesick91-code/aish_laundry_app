<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An append-only production domain event (FR-084). The table refuses UPDATE and
 * DELETE at the database boundary; this model never updates a row. The
 * (tenant_id, client_reference) unique index is the server-side idempotency key
 * (FR-079).
 */
class ProductionEvent extends Model
{
    use HasUuids;

    protected $table = 'production_events';

    public const UPDATED_AT = null; // append-only: never updated

    protected $fillable = [
        'tenant_id', 'job_id', 'type', 'actor_membership_id',
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
