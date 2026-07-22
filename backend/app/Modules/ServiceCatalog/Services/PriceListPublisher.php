<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Services;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and publishes price lists (FR-034 … FR-036).
 *
 * PUBLISHING IS THE IRREVERSIBLE ACT
 * ----------------------------------
 * Creating a draft costs nothing and can be edited freely. Publishing freezes
 * the row and its items permanently and makes it the price customers are
 * charged. That is why it needs its own permission (PRICE_LIST_PUBLISH) rather
 * than riding on PRICE_LIST_MANAGE.
 *
 * OVERLAP IS THE DATABASE'S ANSWER, NOT THIS CLASS'S
 * --------------------------------------------------
 * This class does NOT query for overlapping active lists before inserting.
 * Doing so would be a lost-update race: two concurrent publishes each see no
 * overlap and both commit. The EXCLUDE constraint rejects the second writer at
 * the engine, and this class translates that rejection into a client error
 * (invariant P4, threat T-10).
 *
 * That is the whole point: correctness does not depend on which publish ran
 * first, only on which one the database accepted.
 */
final class PriceListPublisher
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array{code: string, name: string, effective_from: string, effective_until?: ?string}  $attributes
     */
    public function createDraft(
        TenantContext $context,
        string $laundryBrandId,
        array $attributes,
    ): PriceList {
        // FR-034 + invariant P1. The brand is resolved WITHIN the active tenant,
        // so a brand id from another tenant simply does not resolve. The
        // composite foreign key would reject it too; refusing it here means the
        // caller gets a clean 404 rather than a constraint error.
        $brand = LaundryBrand::query()
            ->where('tenant_id', $context->tenantId())
            ->whereKey($laundryBrandId)
            ->first();

        if ($brand === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        $priceList = new PriceList([
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'effective_from' => $attributes['effective_from'],
            'effective_until' => $attributes['effective_until'] ?? null,
        ]);

        $priceList->tenant_id = $context->tenantId();
        $priceList->laundry_brand_id = $brand->id;
        $priceList->status = PriceList::STATUS_DRAFT;
        $priceList->currency = 'IDR';
        $priceList->save();

        $this->audit->record(
            action: AuditAction::PRICE_LIST_CREATED,
            subjectType: PriceList::class,
            subjectId: $priceList->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: [
                'code' => $priceList->code,
                'laundry_brand_id' => $priceList->laundry_brand_id,
                'effective_from' => $priceList->effective_from?->toDateString(),
            ],
        );

        return $priceList;
    }

    /**
     * Publish a draft, optionally superseding a currently active list.
     *
     * The supersede path closes the outgoing list's effective window rather than
     * deleting it: FR-036 requires that a past order can still resolve the price
     * that applied when it was created, so history is never removed.
     */
    public function publish(
        TenantContext $context,
        PriceList $priceList,
        ?PriceList $supersedes = null,
    ): PriceList {
        if (! $priceList->isDraft()) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Hanya daftar harga berstatus draf yang dapat diterbitkan.'
            );
        }

        if ($supersedes !== null) {
            $this->assertSameTenantAndBrand($context, $priceList, $supersedes);
        }

        try {
            return DB::transaction(function () use ($context, $priceList, $supersedes): PriceList {
                if ($supersedes !== null) {
                    // Close the outgoing window the day before the incoming one
                    // starts, so the two never overlap and no day is unpriced.
                    $supersedes->status = PriceList::STATUS_SUPERSEDED;
                    $supersedes->effective_until = $priceList->effective_from->copy()->subDay();
                    $supersedes->is_default = false;

                    // `effective_until` is not in MUTABLE_AFTER_PUBLISH, so the
                    // model guard would reject this. Closing a window is a
                    // lifecycle act on the OUTGOING row, and it is performed
                    // with a targeted update rather than by widening the guard —
                    // widening it would permit editing any published date.
                    DB::table('price_lists')
                        ->where('tenant_id', $context->tenantId())
                        ->where('id', $supersedes->id)
                        ->update([
                            'status' => PriceList::STATUS_SUPERSEDED,
                            'effective_until' => $supersedes->effective_until,
                            'is_default' => false,
                            'updated_at' => now(),

                            // The concurrency token must advance here too.
                            // Going around the model to satisfy the immutability
                            // guard also went around `HasOptimisticVersion`, so
                            // this row changed underneath anyone holding its
                            // version while still answering to that version — a
                            // stale write would have been accepted against a
                            // list that had just been superseded (SEC-04).
                            //
                            // Incremented IN SQL rather than from a value read
                            // in PHP, so two concurrent publishes cannot both
                            // compute the same next number.
                            'version' => DB::raw('version + 1'),
                        ]);

                    $priceList->supersedes_price_list_id = $supersedes->id;
                }

                $priceList->status = PriceList::STATUS_ACTIVE;
                $priceList->published_at = now();
                $priceList->published_by_membership_id = $context->membershipId();
                $priceList->save();

                // PUBLICATION is the moment prices become chargeable, and the
                // moment a past order's price is frozen against later edits
                // (FR-035, Rule 04 invariant 11). It is the single most
                // consequential Step 4 write, and the one a financial dispute
                // reaches for first.
                $this->audit->record(
                    action: AuditAction::PRICE_LIST_PUBLISHED,
                    subjectType: PriceList::class,
                    subjectId: $priceList->id,
                    tenantId: $context->tenantId(),
                    actorUserId: $context->userId(),
                    actorMembershipId: $context->membershipId(),
                    metadata: [
                        'code' => $priceList->code,
                        'laundry_brand_id' => $priceList->laundry_brand_id,
                        'effective_from' => $priceList->effective_from?->toDateString(),
                        'supersedes_price_list_id' => $supersedes?->id,
                    ],
                );

                return $priceList->refresh();
            });
        } catch (QueryException $exception) {
            // The EXCLUDE constraint fired: another ACTIVE list for this brand
            // already covers part of this window.
            if (str_contains($exception->getMessage(), 'price_lists_no_overlapping_active')) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Rentang berlaku daftar harga ini bertumpang tindih dengan '
                    .'daftar harga aktif lain pada brand yang sama.',
                    ['effective_from' => ['overlap']]
                );
            }

            if (str_contains($exception->getMessage(), 'price_lists_one_default_per_brand')) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Brand ini sudah memiliki daftar harga bawaan yang aktif.',
                    ['is_default' => ['duplicate']]
                );
            }

            throw $exception;
        }
    }

    private function assertSameTenantAndBrand(
        TenantContext $context,
        PriceList $incoming,
        PriceList $outgoing,
    ): void {
        $sameTenant = hash_equals($context->tenantId(), $outgoing->tenant_id)
            && hash_equals($context->tenantId(), $incoming->tenant_id);

        if (! $sameTenant) {
            // Indistinguishable from "does not exist" (Rule 48, hard rule 5).
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        if (! hash_equals($incoming->laundry_brand_id, $outgoing->laundry_brand_id)) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Daftar harga hanya dapat menggantikan daftar harga pada brand yang sama.'
            );
        }
    }
}
