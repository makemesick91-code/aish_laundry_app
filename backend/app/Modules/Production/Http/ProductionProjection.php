<?php

declare(strict_types=1);

namespace App\Modules\Production\Http;

use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionItem;
use App\Modules\Production\Models\ProductionJob;

/**
 * The production API projection — MINIMAL exposure (Rule 32). It carries no
 * money, no customer personal data, and no internal audit detail beyond the
 * production timeline the operator needs.
 */
final class ProductionProjection
{
    public static function summary(ProductionJob $job): array
    {
        return [
            'id' => $job->id,
            'order_id' => $job->order_id,
            'outlet_id' => $job->outlet_id,
            'state' => $job->state,
            'version' => (int) $job->version,
            'block_reason_code' => $job->block_reason_code,
            'updated_at' => optional($job->updated_at)->toIso8601String(),
        ];
    }

    public static function detail(ProductionJob $job): array
    {
        $items = ProductionItem::query()->forTenant($job->tenant_id)->where('job_id', $job->id)->get()
            ->map(static fn (ProductionItem $i) => [
                'id' => $i->id,
                'order_line_id' => $i->order_line_id,
                'service_type' => $i->service_type,
                'stage' => $i->stage,
                'quantity_done' => $i->quantity_done,
                'units_total' => (int) $i->units_total,
                'units_done' => (int) $i->units_done,
            ])->all();

        return array_merge(self::summary($job), ['items' => $items]);
    }

    public static function timeline(ProductionJob $job): array
    {
        return ProductionEvent::query()->forTenant($job->tenant_id)->where('job_id', $job->id)
            ->orderBy('occurred_at')->get()
            ->map(static fn (ProductionEvent $e) => [
                'type' => $e->type,
                'actor_membership_id' => $e->actor_membership_id,
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
            ])->all();
    }
}
