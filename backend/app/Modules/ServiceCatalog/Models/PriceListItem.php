<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * ONE PRICE, IN INTEGER RUPIAH (FR-037).
 *
 * `amount_rupiah` is cast to `integer`, never to `float` or `decimal`. Rule 04
 * hard rule 2 forbids binary floating point anywhere in a money path, and a cast
 * is part of that path: a `float` cast would reintroduce the defect the
 * `bigint` column was chosen to avoid, one layer above the schema where it is
 * far less visible.
 *
 * There is no `setAmountAttribute` accepting a decimal string and no currency
 * conversion. Money enters as an integer number of Rupiah or it does not enter.
 *
 * Items of a PUBLISHED list are immutable for the same reason the list is
 * (FR-035, FR-036): a Step 5 order captures a price, and a reprinted nota must
 * show what the customer agreed.
 */
class PriceListItem extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;

    protected $table = 'price_list_items';

    protected $fillable = [
        'service_id',
        'service_package_id',
        'service_addon_id',
        'amount_rupiah',
    ];

    protected function casts(): array
    {
        // INTEGER. See the class docblock — this cast is part of the money path.
        return ['amount_rupiah' => 'integer'];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class, 'price_list_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            $list = $item->priceList()->first();

            if ($list !== null && $list->isPublished()) {
                throw new RuntimeException(
                    'Items of a published price list are immutable (FR-035). '
                    .'Publish a new version instead.'
                );
            }
        };

        static::updating($guard);
        static::deleting($guard);
        static::creating($guard);
    }
}
