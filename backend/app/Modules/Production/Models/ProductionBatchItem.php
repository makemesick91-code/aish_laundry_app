<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CURRENT membership of a production item in a batch (FR-074). The composite
 * foreign keys bind both the batch and the item to the SAME tenant, so a batch
 * can never gain a member from another tenant (Rule 02/48). The unique
 * (batch_id, production_item_id) index refuses duplicate membership.
 *
 * This row is the CURRENT fact. Removing an item deletes it; the historical fact
 * that the item was once a member is preserved forever in production_batch_events
 * (append-only), so "remove" loses no history (FR-074).
 */
class ProductionBatchItem extends Model
{
    use HasUuids;

    protected $table = 'production_batch_items';

    protected $fillable = [
        'tenant_id', 'batch_id', 'production_item_id',
    ];

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function productionItem(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }
}
