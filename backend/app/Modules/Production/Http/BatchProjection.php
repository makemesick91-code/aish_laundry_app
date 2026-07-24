<?php

declare(strict_types=1);

namespace App\Modules\Production\Http;

use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionBatchEvent;
use App\Modules\Production\Models\ProductionBatchItem;
use App\Modules\Production\Models\ProductionItem;

/**
 * The production batch API projection — MINIMAL exposure (Rule 32). It carries no
 * money, no customer personal data, and no internal audit detail beyond the batch
 * membership timeline the operator needs.
 */
final class BatchProjection
{
    public static function summary(ProductionBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'code' => $batch->code,
            'stage' => $batch->stage,
            'status' => $batch->status,
            'version' => (int) $batch->version,
            'outlet_id' => $batch->outlet_id,
            'item_count' => ProductionBatchItem::query()->forTenant($batch->tenant_id)
                ->where('batch_id', $batch->id)->count(),
            'closed_at' => optional($batch->closed_at)->toIso8601String(),
            'updated_at' => optional($batch->updated_at)->toIso8601String(),
        ];
    }

    public static function detail(ProductionBatch $batch): array
    {
        $members = ProductionBatchItem::query()->forTenant($batch->tenant_id)
            ->where('batch_id', $batch->id)->orderBy('created_at')->get();

        $itemsById = ProductionItem::query()->forTenant($batch->tenant_id)
            ->whereIn('id', $members->pluck('production_item_id')->all())->get()->keyBy('id');

        $membership = $members->map(static function (ProductionBatchItem $m) use ($itemsById): array {
            /** @var ProductionItem|null $item */
            $item = $itemsById->get($m->production_item_id);

            return [
                'production_item_id' => $m->production_item_id,
                'service_type' => $item?->service_type,
                'stage' => $item?->stage,
                'added_at' => optional($m->created_at)->toIso8601String(),
            ];
        })->all();

        return array_merge(self::summary($batch), ['items' => $membership]);
    }

    public static function timeline(ProductionBatch $batch): array
    {
        return ProductionBatchEvent::query()->forTenant($batch->tenant_id)
            ->where('batch_id', $batch->id)->orderBy('occurred_at')->get()
            ->map(static fn (ProductionBatchEvent $e) => [
                'type' => $e->type,
                'actor_membership_id' => $e->actor_membership_id,
                'production_item_id' => $e->payload['production_item_id'] ?? null,
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            ])->all();
    }
}
