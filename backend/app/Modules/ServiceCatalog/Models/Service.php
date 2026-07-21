<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable laundry service: kiloan (by weight) or satuan (per item) — FR-031.
 *
 * `$table` is `service_catalog` rather than `services`, matching the token
 * DEC-0030 permits. The class is `Service` because that is the domain term
 * (Rule 17 — one concept, one term); the table name is the catalogue.
 *
 * `tenant_id` is not mass-assignable: it comes from the verified TenantContext.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $unit_kind
 * @property bool $is_active
 */
class Service extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /** FR-031 — the only two shapes. Mirrored by a DB check constraint. */
    public const UNIT_KILOAN = 'kiloan';

    public const UNIT_SATUAN = 'satuan';

    protected $table = 'service_catalog';

    protected $fillable = [
        'service_category_id',
        'code',
        'name',
        'description',
        'unit_kind',
        'minimum_quantity',
        'turnaround_hours',
        'is_active',
        'effective_from',
        'effective_until',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
