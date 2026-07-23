<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Models;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant-scoped order (FR-048). The aggregate root for its lines.
 *
 * TENANT SCOPE IS EXPLICIT, NOT AMBIENT — `scopeForTenant()` at every call site,
 * exactly as Customer and Outlet do it (Rule 02 hard rule 8).
 *
 * ALMOST NOTHING IS MASS-ASSIGNABLE. `tenant_id`, `outlet_id`, `order_number`,
 * `client_reference`, `status`, and every money and audit column are set by
 * `OrderRegistry` from server-derived values or the verified TenantContext,
 * never from a request body. A client that could set `total_rupiah` or `status`
 * could charge itself nothing or mark its own order paid — which is exactly what
 * FR-051 and FR-064 forbid. Only the two genuinely client-authored fields are
 * fillable.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $outlet_id
 * @property string $customer_id
 * @property string $order_number
 * @property string $client_reference
 * @property string $status
 * @property string $currency
 * @property int $subtotal_rupiah
 * @property int $discount_rupiah
 * @property int $total_rupiah
 */
class Order extends Model
{
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    // The intake statuses this step operates. The COLUMN accepts all fifteen
    // canonical statuses (Rule 19), but Step 5 only writes these; the production
    // stages are Step 6.
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_RECEIVED = 'RECEIVED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /** The fifteen canonical order statuses (Rule 19); mirrors the DB CHECK. */
    public const CANONICAL_STATUSES = [
        'DRAFT', 'RECEIVED', 'AWAITING_PROCESS', 'SORTING', 'WASHING', 'DRYING',
        'FINISHING', 'QUALITY_CONTROL', 'REWORK', 'READY_FOR_PICKUP',
        'SCHEDULED_FOR_DELIVERY', 'OUT_FOR_DELIVERY', 'COMPLETED', 'CANCELLED', 'ISSUE',
    ];

    protected $table = 'orders';

    /**
     * Deliberately narrow. Everything financial, every identifier, and the
     * status are server-owned (see the class docblock).
     */
    protected $fillable = [
        'special_instructions',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_rupiah' => 'integer',
            'discount_rupiah' => 'integer',
            'total_rupiah' => 'integer',
            'placed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'order_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** A terminal order (COMPLETED or CANCELLED) never leaves its state casually. */
    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, 'COMPLETED'], true);
    }
}
