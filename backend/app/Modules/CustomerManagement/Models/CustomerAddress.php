<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved address belonging to a customer (FR-024).
 *
 * DATA CLASS: RESTRICTED. Never rendered in a list row, never sent to the
 * public tracking portal, and shown at full precision only to a role with a
 * pickup or delivery reason (FR-025, Rule 32 hard rule 4).
 *
 * `tenant_id` and `customer_id` are not mass-assignable: both are set from the
 * verified tenant context and the resolved parent, never from request input.
 * The composite foreign key would reject a mismatched pair anyway, but a
 * rejected write at the database is an error the operator sees, whereas
 * refusing the input is simply correct.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $label
 * @property string $address_line
 * @property bool $is_primary
 * @property bool $is_active
 */
class CustomerAddress extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'label',
        'address_line',
        'district',
        'city',
        'province',
        'postal_code',
        'notes',
        'is_pickup_suitable',
        'is_delivery_suitable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_pickup_suitable' => 'boolean',
            'is_delivery_suitable' => 'boolean',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
