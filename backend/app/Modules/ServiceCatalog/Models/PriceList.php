<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use App\Modules\Organization\Models\LaundryBrand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * A per-brand, versioned price list (FR-034 … FR-036).
 *
 * A PUBLISHED VERSION IS FROZEN, AND THE MODEL REFUSES TO THAW IT
 * ---------------------------------------------------------------
 * FR-035: "publishing a new version shall not alter any previously published
 * version." FR-036 depends on that: a Step 5 order captures the price that
 * applied, and a reprinted nota must show the price the customer actually
 * agreed. If a published version could be edited, every historical order's
 * price would silently change with it — the exact failure Rule 04 hard rule 9
 * exists to prevent.
 *
 * `booted()` therefore blocks any update to a row that has left `draft`, except
 * the narrow lifecycle transitions listed in MUTABLE_AFTER_PUBLISH. Those are
 * bookkeeping about the version (it has been superseded, it has been archived),
 * never about what anything costs.
 *
 * WHY THIS IS A MODEL GUARD AND NOT A DATABASE RULE
 * ------------------------------------------------
 * `customer_consents` uses PostgreSQL RULEs because FR-028 names a MIGRATION as
 * an attack path, and a migration bypasses the application entirely. Price lists
 * have no equivalent requirement: the threat is an ordinary code path editing a
 * published row, and the application boundary is the only writer. Adding a RULE
 * here would also block the legitimate supersede/archive transitions, so the
 * guard sits where the distinction can be made.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $laundry_brand_id
 * @property string $status
 * @property bool $is_default
 */
class PriceList extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * The only columns that may change once a list is no longer a draft.
     *
     * All four are lifecycle bookkeeping. None of them is a price, an effective
     * date, or a brand — changing any of those after publication would rewrite
     * what a past order was charged.
     */
    private const MUTABLE_AFTER_PUBLISH = [
        'status',
        'supersedes_price_list_id',
        'is_default',
        'deleted_at',
        'updated_at',
    ];

    protected $table = 'price_lists';

    protected $fillable = [
        'code',
        'name',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_default' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function laundryBrand(): BelongsTo
    {
        return $this->belongsTo(LaundryBrand::class, 'laundry_brand_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_list_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return in_array(
            $this->status,
            [self::STATUS_ACTIVE, self::STATUS_SUPERSEDED, self::STATUS_ARCHIVED],
            true
        );
    }

    protected static function booted(): void
    {
        static::updating(function (self $priceList): void {
            // `getOriginal('status')` is the status as loaded, so a row being
            // published in THIS save is still a draft here and passes.
            if ($priceList->getOriginal('status') === self::STATUS_DRAFT) {
                return;
            }

            $changed = array_keys($priceList->getDirty());
            $forbidden = array_diff($changed, self::MUTABLE_AFTER_PUBLISH);

            if ($forbidden !== []) {
                throw new RuntimeException(
                    'A published price list is immutable (FR-035). Publish a new '
                    .'version instead of editing this one. Attempted to change: '
                    .implode(', ', $forbidden)
                );
            }
        });
    }
}
