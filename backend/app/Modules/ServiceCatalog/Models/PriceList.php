<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Models;

use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
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
    use HasOptimisticVersion;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * The LIFECYCLE columns a caller may change once a list is no longer a
     * draft.
     *
     * None of them is a price, an effective date, a brand, a code or a name —
     * changing any of those after publication would rewrite what a past order
     * was charged (FR-035, Rule 04 invariant 11).
     */
    private const MUTABLE_AFTER_PUBLISH = [
        'status',
        'supersedes_price_list_id',
        'is_default',
    ];

    /**
     * Columns the PERSISTENCE MECHANISM owns, which a caller never chooses.
     *
     * Held separately from the list above, and that separation is the SEC-04
     * fix rather than a tidying-up.
     *
     * `version` was in neither list. `HasOptimisticVersion` registers its
     * `updating` hook during `bootTraits()`, which runs BEFORE `booted()`, so by
     * the time the immutability check below ran, the concurrency counter was
     * already dirty on every single update. The counter therefore appeared as a
     * forbidden business-field change, and EVERY permitted post-publish
     * mutation threw. The allow-list above was, in practice, empty.
     *
     * Nothing caught it because no test ever asserted that a permitted
     * post-publish update SUCCEEDS. The negative tests all passed — they were
     * passing for the wrong reason, since a guard that refuses everything
     * refuses the forbidden cases too.
     *
     * The fix is not to append `version` to the caller-facing list. That would
     * say a client may choose its own concurrency token, which is exactly what
     * `HasOptimisticVersion` refuses to allow. It is a separate category:
     * server-owned bookkeeping that changes as a CONSEQUENCE of a permitted
     * write, never as the substance of one.
     */
    private const SYSTEM_MANAGED = [
        'version',
        'updated_at',
        'deleted_at',
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
            $forbidden = array_diff(
                $changed,
                self::MUTABLE_AFTER_PUBLISH,
                self::SYSTEM_MANAGED
            );

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
