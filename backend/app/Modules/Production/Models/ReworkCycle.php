<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A rework cycle, created from a failed QC inspection and linked immutably to it
 * (FR-084). cycle_no is monotonic per job; the row is never deleted.
 */
class ReworkCycle extends Model
{
    use HasUuids;

    protected $table = 'rework_cycles';

    protected $fillable = [
        'tenant_id', 'job_id', 'source_inspection_id', 'cycle_no',
        'reason_code', 'reason', 'stage', 'started_by_membership_id',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'cycle_no' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
