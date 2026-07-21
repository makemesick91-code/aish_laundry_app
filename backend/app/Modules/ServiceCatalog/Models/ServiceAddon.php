<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An add-on such as express handling or special treatment (FR-033).
 *
 * CATALOGUE ENTRY ONLY. Applying an add-on to an order line is Step 5, and
 * there is deliberately no relationship here to anything orderable
 * (DEC-0031 B, DEC-0030).
 */
class ServiceAddon extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'service_addons';

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
