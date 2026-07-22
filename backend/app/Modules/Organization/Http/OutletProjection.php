<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http;

use App\Modules\Organization\Models\Outlet;
use App\Modules\Organization\Models\OutletPrinter;
use App\Modules\Organization\Models\OutletServiceZone;
use App\Modules\Organization\Models\OutletShift;
use App\Modules\Organization\Models\TenantProofPolicy;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;

/**
 * THE OUTLET MASTER-DATA RESPONSE SHAPE — AN ALLOW-LIST, NOT A MODEL DUMP.
 *
 * Every method below names the fields it emits. A column added to `outlets`
 * tomorrow does not appear in an API response until somebody adds it here on
 * purpose (Rule 32 hard rule 7).
 *
 * The alternative — serialising the model and hiding what should not escape — is
 * the wrong default: it leaks by omission, and the omission is invisible in
 * review because nothing in the diff mentions the new field.
 *
 * `version` is the optimistic-concurrency token a client echoes back when it
 * edits (threat T-12). It is derived from `updated_at`, and it is opaque: a
 * client compares it and returns it, and never parses it.
 */
final class OutletProjection
{
    /** @return array<string, mixed> */
    public static function summary(Outlet $outlet): array
    {
        return [
            'id' => $outlet->id,
            'name' => $outlet->name,
            'code' => $outlet->code,
            'timezone' => $outlet->timezone,
            'is_active' => (bool) $outlet->is_active,
            'version' => OptimisticConcurrency::versionOf($outlet),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Outlet $outlet): array
    {
        return [
            ...self::summary($outlet),
            'laundry_brand_id' => $outlet->laundry_brand_id,
            'address_line' => $outlet->address_line,
            'contact_phone' => $outlet->contact_phone,

            // FR-041. Local wall clock, always accompanied by the timezone that
            // makes it meaningful — a bare "08:00" in an API response is an
            // invitation to interpret it in the reader's own zone.
            'operating_hours' => $outlet->operating_hours,

            // FR-042. Descriptive in Step 4; nothing schedules against it yet.
            'daily_capacity_kg' => $outlet->daily_capacity_kg,
            'daily_capacity_orders' => $outlet->daily_capacity_orders,

            // FR-047. Reported with its timezone for the same reason.
            'quiet_hours' => [
                'start' => $outlet->quiet_hours_start,
                'end' => $outlet->quiet_hours_end,
                'timezone' => $outlet->timezone,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function zone(OutletServiceZone $zone): array
    {
        return [
            'id' => $zone->id,
            'outlet_id' => $zone->outlet_id,
            'code' => $zone->code,
            'name' => $zone->name,
            'description' => $zone->description,
            'postal_codes' => $zone->postal_codes ?? [],
            'is_active' => (bool) $zone->is_active,
            'display_order' => (int) $zone->display_order,
            'version' => OptimisticConcurrency::versionOf($zone),
        ];
    }

    /** @return array<string, mixed> */
    public static function shift(OutletShift $shift): array
    {
        return [
            'id' => $shift->id,
            'outlet_id' => $shift->outlet_id,
            'code' => $shift->code,
            'name' => $shift->name,
            'starts_at' => $shift->starts_at,
            'ends_at' => $shift->ends_at,

            // Emitted explicitly so a client never has to infer from the times
            // whether 22:00–06:00 spans midnight.
            'crosses_midnight' => (bool) $shift->crosses_midnight,
            'is_active' => (bool) $shift->is_active,
            'display_order' => (int) $shift->display_order,
            'version' => OptimisticConcurrency::versionOf($shift),
        ];
    }

    /** @return array<string, mixed> */
    public static function printer(OutletPrinter $printer): array
    {
        return [
            'id' => $printer->id,
            'outlet_id' => $printer->outlet_id,
            'code' => $printer->code,
            'name' => $printer->name,
            'device_kind' => $printer->device_kind,
            'connection_kind' => $printer->connection_kind,

            // A device address, never a credential — see OutletPrinter.
            'device_identifier' => $printer->device_identifier,
            'is_default' => (bool) $printer->is_default,
            'is_active' => (bool) $printer->is_active,
            'version' => OptimisticConcurrency::versionOf($printer),
        ];
    }

    /** @return array<string, mixed> */
    public static function proofPolicy(TenantProofPolicy $policy): array
    {
        $emit = static fn (array $fields): array => array_combine(
            $fields,
            array_map(static fn (string $f): bool => (bool) $policy->{$f}, $fields)
        );

        return [
            'id' => $policy->id,
            'pickup' => $emit(TenantProofPolicy::pickupFields()),
            'delivery' => $emit(TenantProofPolicy::deliveryFields()),
            'version' => OptimisticConcurrency::versionOf($policy),
        ];
    }
}
