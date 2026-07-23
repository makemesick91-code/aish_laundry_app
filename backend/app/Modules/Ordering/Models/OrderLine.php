<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced line of an order. Carries a PRICE SNAPSHOT (FR-036): the unit price,
 * the service name, and which price-list version it came from, captured at intake
 * and never rewritten by a later price-list change.
 *
 * Nothing here is client-mass-assignable. Lines are written only by
 * `OrderRegistry`, which sets `tenant_id`, `order_id`, and every money and
 * snapshot field from server-validated values (Rule 04, T-05 mass assignment).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property int $line_number
 * @property string $unit
 * @property int $quantity_milli
 * @property int $unit_price_rupiah
 * @property int $discount_rupiah
 * @property int $subtotal_rupiah
 */
class OrderLine extends Model
{
    use HasUuids;

    public const UNIT_KILOGRAM = 'kilogram';

    public const UNIT_PIECE = 'piece';

    public const UNIT_PACKAGE = 'package';

    public const UNIT_ADDON = 'addon';

    protected $table = 'order_lines';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity_milli' => 'integer',
            'unit_price_rupiah' => 'integer',
            'discount_rupiah' => 'integer',
            'subtotal_rupiah' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** @return list<string> */
    public static function units(): array
    {
        return [self::UNIT_KILOGRAM, self::UNIT_PIECE, self::UNIT_PACKAGE, self::UNIT_ADDON];
    }
}
