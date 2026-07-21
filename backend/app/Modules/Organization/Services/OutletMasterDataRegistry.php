<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Outlet;
use App\Modules\Organization\Models\OutletPrinter;
use App\Modules\Organization\Models\OutletServiceZone;
use App\Modules\Organization\Models\OutletShift;
use App\Modules\Organization\Models\TenantProofPolicy;
use App\Modules\Organization\Support\OperatingHours;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * The only writer of outlet master data and its satellites (FR-041 … FR-047).
 *
 * Every method takes the resolved TenantContext explicitly rather than reading an
 * ambient one, matching CustomerRegistry. A service that resolves its own tenant
 * is a service that can be called from a queue worker with the wrong one
 * (Rule 20 hard rule 6).
 *
 * THE OUTLET IS ALWAYS RESOLVED WITHIN THE ACTIVE TENANT FIRST.
 * `resolveOutlet()` filters on the verified tenant id before it looks at the id
 * at all, so an outlet belonging to another tenant and an outlet that does not
 * exist produce the SAME 404 (Rule 48 hard rule 5, threat T-06). The composite
 * foreign keys on every satellite would reject a cross-tenant pairing anyway;
 * resolving here means the caller gets a clean 404 rather than a constraint
 * error, and the guarantee holds at both layers.
 *
 * NOTHING HERE ACTS ON THE MASTER DATA IT WRITES. A quiet-hours window is
 * recorded, not enforced — enforcement is Step 7. A shift is defined, not closed —
 * closing is Step 5. A zone is drawn, not routed — routing is Step 8. A printer
 * is configured, and no document is named (DEC-0030).
 */
final class OutletMasterDataRegistry
{
    /**
     * Update an outlet's own master-data attributes.
     *
     * `tenant_id` and `laundry_brand_id` are NOT accepted here under any name.
     * Re-parenting an outlet to a different brand is a Step 3 organisational act
     * with its own permission; letting it ride along in a master-data update
     * would be a quiet privilege widening (threat T-05).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOutlet(TenantContext $context, Outlet $outlet, array $attributes): Outlet
    {
        $this->assertSameTenant($context, $outlet);

        if (array_key_exists('operating_hours', $attributes)) {
            $hours = $attributes['operating_hours'];

            if ($hours === null) {
                $outlet->operating_hours = null;
            } else {
                if (! is_array($hours)) {
                    throw ApiException::of(
                        ErrorCode::VALIDATION_FAILED,
                        'Jam operasional harus berupa objek per hari.',
                        ['operating_hours' => ['invalid']]
                    );
                }

                try {
                    $outlet->operating_hours = OperatingHours::fromArray($hours)->toArray();
                } catch (InvalidArgumentException $exception) {
                    throw ApiException::of(
                        ErrorCode::VALIDATION_FAILED,
                        $exception->getMessage(),
                        ['operating_hours' => ['invalid']]
                    );
                }
            }

            unset($attributes['operating_hours']);
        }

        $outlet->fill(array_intersect_key($attributes, array_flip([
            'name',
            'timezone',
            'daily_capacity_kg',
            'daily_capacity_orders',
            'quiet_hours_start',
            'quiet_hours_end',
            'contact_phone',
            'address_line',
            'is_active',
        ])));

        $outlet->save();

        return $outlet->refresh();
    }

    // ------------------------------------------------------------------
    // Service zones (FR-043)
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createZone(TenantContext $context, Outlet $outlet, array $attributes): OutletServiceZone
    {
        $this->assertSameTenant($context, $outlet);

        $zone = new OutletServiceZone($this->only($attributes, [
            'code', 'name', 'description', 'postal_codes', 'is_active', 'display_order',
        ]));

        return $this->attachAndSave($context, $outlet, $zone, 'outlet_service_zones_outlet_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updateZone(TenantContext $context, OutletServiceZone $zone, array $attributes): OutletServiceZone
    {
        $this->assertSameTenant($context, $zone);

        $zone->fill($this->only($attributes, [
            'code', 'name', 'description', 'postal_codes', 'is_active', 'display_order',
        ]));

        return $this->saveTranslatingUnique($zone, 'outlet_service_zones_outlet_code_unique');
    }

    // ------------------------------------------------------------------
    // Shifts (FR-044) — DEFINITIONS ONLY. Shift closing is Step 5.
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createShift(TenantContext $context, Outlet $outlet, array $attributes): OutletShift
    {
        $this->assertSameTenant($context, $outlet);

        $shift = new OutletShift($this->only($attributes, [
            'code', 'name', 'starts_at', 'ends_at', 'is_active', 'display_order',
        ]));

        // DERIVED, never accepted from the client, so the flag can never
        // contradict the hours it describes.
        $shift->crosses_midnight = OutletShift::deriveCrossesMidnight(
            (string) $shift->starts_at,
            (string) $shift->ends_at
        );

        return $this->attachAndSave($context, $outlet, $shift, 'outlet_shifts_outlet_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updateShift(TenantContext $context, OutletShift $shift, array $attributes): OutletShift
    {
        $this->assertSameTenant($context, $shift);

        $shift->fill($this->only($attributes, [
            'code', 'name', 'starts_at', 'ends_at', 'is_active', 'display_order',
        ]));

        $shift->crosses_midnight = OutletShift::deriveCrossesMidnight(
            (string) $shift->starts_at,
            (string) $shift->ends_at
        );

        return $this->saveTranslatingUnique($shift, 'outlet_shifts_outlet_code_unique');
    }

    // ------------------------------------------------------------------
    // Printers (FR-045) — A DEVICE, NOT A DOCUMENT (DEC-0030).
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createPrinter(TenantContext $context, Outlet $outlet, array $attributes): OutletPrinter
    {
        $this->assertSameTenant($context, $outlet);

        $printer = new OutletPrinter($this->only($attributes, [
            'code', 'name', 'device_kind', 'connection_kind', 'device_identifier', 'is_default', 'is_active',
        ]));

        return $this->attachAndSave($context, $outlet, $printer, 'outlet_printers_outlet_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updatePrinter(TenantContext $context, OutletPrinter $printer, array $attributes): OutletPrinter
    {
        $this->assertSameTenant($context, $printer);

        $printer->fill($this->only($attributes, [
            'code', 'name', 'device_kind', 'connection_kind', 'device_identifier', 'is_default', 'is_active',
        ]));

        return $this->saveTranslatingUnique($printer, 'outlet_printers_outlet_code_unique');
    }

    // ------------------------------------------------------------------
    // Proof policy (FR-046) — CONFIGURATION ONLY. Capture is Step 8.
    // ------------------------------------------------------------------

    /**
     * The tenant's proof policy, creating the canonical default if absent.
     *
     * The default requires a recipient name on both legs. It is deliberately NOT
     * "nothing": Rule 09 hard rule 2 says some proof is always required, and a
     * tenant that has never opened this screen must still be covered by a policy
     * that means something.
     */
    public function proofPolicy(TenantContext $context): TenantProofPolicy
    {
        $policy = TenantProofPolicy::query()
            ->forTenant($context->tenantId())
            ->first();

        if ($policy !== null) {
            return $policy;
        }

        $policy = new TenantProofPolicy;
        $policy->tenant_id = $context->tenantId();
        $policy->save();

        return $policy->refresh();
    }

    /** @param array<string, mixed> $attributes */
    public function updateProofPolicy(TenantContext $context, array $attributes): TenantProofPolicy
    {
        $policy = $this->proofPolicy($context);

        $policy->fill($this->only(
            $attributes,
            [...TenantProofPolicy::pickupFields(), ...TenantProofPolicy::deliveryFields()]
        ));

        // Refused HERE with a readable message, and refused AGAIN by the database
        // check constraint if anything ever bypasses this class. The duplication
        // is deliberate: the message is for the operator, the constraint is the
        // guarantee (Rule 18 hard rule 2).
        if (! $policy->requiresSomeProof()) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Setiap serah terima wajib memiliki minimal satu bukti. Pilih '
                .'sekurang-kurangnya satu bentuk bukti untuk penjemputan dan '
                .'untuk pengantaran.',
                ['proof' => ['at_least_one_required']]
            );
        }

        $policy->save();

        return $policy->refresh();
    }

    // ------------------------------------------------------------------
    // Shared plumbing
    // ------------------------------------------------------------------

    /**
     * Tenant-scoped outlet lookup.
     *
     * A foreign outlet id and an absent one produce the SAME 404, with the same
     * body. Distinguishing them would confirm that another tenant holds that
     * outlet (Rule 48 hard rule 5).
     */
    public function resolveOutlet(TenantContext $context, string $outletId): Outlet
    {
        $outlet = Outlet::query()
            ->forTenant($context->tenantId())
            ->whereKey($outletId)
            ->first();

        if ($outlet === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $outlet;
    }

    /**
     * Bind a satellite to its outlet and tenant server-side, then save.
     *
     * `tenant_id` comes from the verified context and `outlet_id` from an outlet
     * already resolved within it. Neither is ever read from the request body.
     */
    private function attachAndSave(
        TenantContext $context,
        Outlet $outlet,
        Model $satellite,
        string $codeIndex,
    ): Model {
        $satellite->setAttribute('tenant_id', $context->tenantId());
        $satellite->setAttribute('outlet_id', $outlet->id);

        return $this->saveTranslatingUnique($satellite, $codeIndex);
    }

    /**
     * Translate the database's uniqueness refusal into a field-level message.
     *
     * The check is NOT done by reading first and inserting after: two concurrent
     * creates would both see no clash and both commit. The unique index decides,
     * and this method turns its refusal into something an operator can act on
     * (the same discipline as the price-list exclusion constraint).
     */
    private function saveTranslatingUnique(Model $model, string $codeIndex): Model
    {
        try {
            $model->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), $codeIndex)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Kode ini sudah dipakai pada outlet tersebut.',
                    ['code' => ['duplicate']]
                );
            }

            if (str_contains($exception->getMessage(), 'outlet_printers_one_default_per_outlet')) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Outlet ini sudah memiliki printer bawaan yang aktif.',
                    ['is_default' => ['duplicate']]
                );
            }

            throw $exception;
        }

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $attributes, array $keys): array
    {
        return array_intersect_key($attributes, array_flip($keys));
    }

    /**
     * Defence in depth.
     *
     * Every query above is already tenant-scoped, so a foreign record should
     * never reach this service. If one ever does it fails closed, as a 404 that
     * discloses nothing, rather than being written under the wrong tenant.
     */
    private function assertSameTenant(TenantContext $context, Model $model): void
    {
        $tenantId = $model->getAttribute('tenant_id');

        if (! is_string($tenantId) || ! hash_equals($context->tenantId(), $tenantId)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
