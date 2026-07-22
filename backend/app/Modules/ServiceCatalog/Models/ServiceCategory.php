<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A grouping for the service catalogue.
 *
 * Presentation structure, not pricing and not policy: a category decides how a
 * counter screen is arranged, and nothing about what anything costs or who may
 * order it. Keeping it that narrow is why it can be renamed freely without any
 * financial consequence.
 *
 * `tenant_id` is not mass-assignable — it comes from the verified TenantContext.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property bool $is_active
 */
class ServiceCategory extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'service_categories';

    protected $fillable = ['code', 'name', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'service_category_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
