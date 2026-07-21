<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Http;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerAddress;
use App\Modules\CustomerManagement\Support\PhoneNumber;

/**
 * THE ALLOW-LIST PROJECTION for customer data (Rule 32, hard rule 7).
 *
 * WHY AN ALLOW-LIST AND NOT A DENYLIST
 * ------------------------------------
 * A denylist ("hide internal_notes") is safe only until somebody adds a column.
 * The new column leaks by DEFAULT, and nothing fails — the leak is discovered by
 * a customer, not by a test. An allow-list inverts that: a field absent from
 * this file is never assembled, so it cannot leak (FR-030).
 *
 * MASKING IS THE DEFAULT, NOT THE EXCEPTION
 * -----------------------------------------
 * `summary()` — the list-row shape — masks the phone and omits the address
 * ENTIRELY. Rule 32 hard rule 4 forbids rendering an address in a list row at
 * all, not merely masking it there.
 *
 * `detail()` still masks the phone. Unmasking is a deliberate, per-record,
 * permissioned, recorded action and is never a side effect of opening a record
 * (Rule 32, hard rule 5). Step 4 offers no unmasking endpoint, so no unmasked
 * phone leaves this application.
 *
 * WHAT IS NEVER HERE
 * ------------------
 * `phone_normalized` (a match key, not display data), `internal_notes` on any
 * customer-facing surface, and anything belonging to another tenant.
 */
final class CustomerProjection
{
    /**
     * List-row shape. No address, masked phone.
     *
     * @return array<string, mixed>
     */
    public static function summary(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'name' => $customer->name,
            'phone_masked' => PhoneNumber::mask($customer->phone_normalized),
            'status' => $customer->status,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Detail shape for an authorised staff member inside the tenant.
     *
     * `internal_notes` appears here and only here: it is staff-facing by
     * definition and never reaches a customer surface (FR-030).
     *
     * @return array<string, mixed>
     */
    public static function detail(Customer $customer): array
    {
        return array_merge(self::summary($customer), [
            'email' => $customer->email,
            'internal_notes' => $customer->internal_notes,
            'addresses' => $customer->addresses
                ->map(static fn (CustomerAddress $a): array => self::address($a))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function address(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'address_line' => $address->address_line,
            'district' => $address->district,
            'city' => $address->city,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
            'notes' => $address->notes,
            'is_pickup_suitable' => $address->is_pickup_suitable,
            'is_delivery_suitable' => $address->is_delivery_suitable,
            'is_primary' => $address->is_primary,
            'is_active' => $address->is_active,
        ];
    }
}
