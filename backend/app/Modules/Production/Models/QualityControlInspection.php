<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A quality-control inspection verdict (QUALITY_CONTROL_STATE_MACHINE.md).
 * APPEND-ONLY: the table refuses UPDATE/DELETE at the database boundary, and a
 * re-inspection is a NEW row — a recorded FAIL is never rewritten into a PASS
 * (FR-082, FR-084).
 */
class QualityControlInspection extends Model
{
    use HasUuids;

    protected $table = 'quality_control_inspections';

    public const UPDATED_AT = null;

    public const VERDICT_PENDING = 'PENDING';
    public const VERDICT_PASSED = 'PASSED';
    public const VERDICT_FAILED = 'FAILED_REWORK_REQUIRED';
    public const VERDICT_WAIVED = 'WAIVED_WITH_AUTHORIZATION';

    protected $fillable = [
        'tenant_id', 'job_id', 'verdict', 'defect_reason_code', 'defect_reason',
        'inspector_membership_id', 'evidence_path', 'inspected_at',
    ];

    protected $casts = [
        'inspected_at' => 'datetime',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
