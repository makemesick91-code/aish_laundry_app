<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A package combining several services at a defined price (FR-032).
 *
 * The package is master data. What it COSTS lives on a price list, not here —
 * a package whose price were stored on the package itself could not be priced
 * differently per brand, which FR-034 requires.
 */
class ServicePackage extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'service_packages';

    protected $fillable = ['code', 'name', 'description', 'is_active', 'display_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
