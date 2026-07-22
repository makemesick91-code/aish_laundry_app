<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pickup and delivery COVERAGE AREA for one outlet (FR-043).
 *
 * COVERAGE, NOT ROUTING. A zone records where the outlet is willing to serve. It
 * does not sequence stops, estimate arrival, assign a courier, or optimise
 * anything — those are Step 8, and Rule 09 hard rule 1 forbids claiming a
 * routing capability the product does not implement.
 *
 * `tenant_id` and `outlet_id` are NOT mass-assignable. Both are resolved
 * server-side from the verified TenantContext and a tenant-scoped outlet lookup;
 * accepting either from a request body would let a caller aim a zone at another
 * tenant's outlet and rely on the database to say no (threat T-05).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $outlet_id
 * @property string $code
 * @property bool $is_active
 */
class OutletServiceZone extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'outlet_service_zones';

    protected $fillable = [
        'code',
        'name',
        'description',
        'postal_codes',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'postal_codes' => 'array',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
