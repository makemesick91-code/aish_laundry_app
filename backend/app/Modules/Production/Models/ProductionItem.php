<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A production item — per order line. For kiloan services it carries quantity
 * progress; for satuan services it carries discrete per-unit flags (FR-075).
 */
class ProductionItem extends Model
{
    use HasOptimisticVersion;
    use HasUuids;

    protected $table = 'production_items';

    public const SERVICE_KILOAN = 'kiloan';
    public const SERVICE_SATUAN = 'satuan';

    protected $fillable = [
        'tenant_id', 'job_id', 'order_line_id', 'service_type',
        'stage', 'quantity_done', 'units_total', 'units_done', 'flags', 'version',
    ];

    protected $casts = [
        'flags' => 'array',
        'quantity_done' => 'decimal:3',
        'units_total' => 'integer',
        'units_done' => 'integer',
        'version' => 'integer',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
