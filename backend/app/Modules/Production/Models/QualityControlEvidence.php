<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored QC defect-photo evidence record (FR-083). Append-only: the table
 * refuses UPDATE and DELETE at the database boundary; this model never updates a
 * row. It carries only METADATA — the bytes live in the private object store
 * under [storage_key], read only through a short-lived signed URL. The
 * (tenant_id, client_reference) partial unique index is the server-side
 * idempotency key.
 */
class QualityControlEvidence extends Model
{
    use HasUuids;

    protected $table = 'quality_control_evidence';

    public const UPDATED_AT = null; // append-only: never updated

    public const STATUS_STORED = 'stored';

    protected $fillable = [
        'tenant_id', 'outlet_id', 'job_id', 'inspection_id',
        'uploaded_by_membership_id', 'content_type', 'byte_size',
        'checksum_sha256', 'storage_key', 'status', 'client_reference',
        'occurred_at',
    ];

    protected $casts = [
        'byte_size' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
