<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Services;

use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\ServiceCatalog\Models\PriceListItem;
use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\ServiceCatalog\Models\ServiceAddon;
use App\Modules\ServiceCatalog\Models\ServicePackage;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Money\RupiahRounding;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * THE ONLY WRITER OF PRICE-LIST ITEMS — the money-bearing rows (FR-037, FR-040).
 *
 * MONEY ENTERS AS AN INTEGER NUMBER OF RUPIAH OR IT DOES NOT ENTER.
 * `RupiahRounding::fromInput()` refuses a float even when it is whole, and
 * refuses a formatted string like "Rp17.500". Accepting `17500.0` because it
 * happens to be exact would teach the codebase that floats are fine in a money
 * path as long as they round nicely, and that is how the first inexact one
 * arrives (Rule 04 hard rule 2).
 *
 * A PUBLISHED LIST IS FROZEN, AND THIS CLASS DOES NOT ARGUE WITH THAT.
 * The model guard on `PriceListItem` refuses any write touching a published
 * list. This class checks first only so the caller gets a readable message
 * rather than a RuntimeException — the guarantee is the model's, and it holds
 * for every writer including ones that never call this class (FR-035, FR-036).
 *
 * INVARIANT S7 — an INACTIVE service may not be priced. A price for something
 * the tenant has withdrawn from sale is a row nothing downstream can act on,
 * and it would quietly become live again the moment the service was
 * reactivated.
 */
final class PriceListItemRegistry
{
    /**
     * @param  array{service_id?: string, service_package_id?: string, service_addon_id?: string, amount_rupiah: mixed}  $attributes
     */
    public function addItem(TenantContext $context, PriceList $priceList, array $attributes): PriceListItem
    {
        $this->assertSameTenant($context, $priceList->tenant_id);
        $this->assertDraft($priceList);

        $target = $this->resolveExactlyOneTarget($context, $attributes);

        $item = new PriceListItem([
            'service_id' => $target['service_id'],
            'service_package_id' => $target['service_package_id'],
            'service_addon_id' => $target['service_addon_id'],
            'amount_rupiah' => $this->money($attributes['amount_rupiah'] ?? null),
        ]);

        $item->tenant_id = $context->tenantId();
        $item->price_list_id = $priceList->id;

        return $this->saveTranslatingUnique($item);
    }

    public function updateItem(TenantContext $context, PriceListItem $item, mixed $amount): PriceListItem
    {
        $this->assertSameTenant($context, $item->tenant_id);

        $list = $item->priceList()->first();

        if ($list !== null) {
            $this->assertDraft($list);
        }

        $item->amount_rupiah = $this->money($amount);
        $item->save();

        return $item->refresh();
    }

    /**
     * Remove an item from a DRAFT list.
     *
     * A published list's items are never removed — a Step 5 order captures a
     * price and a reprinted document must resolve it, so history is not deleted
     * (FR-036). `assertDraft()` is what makes this a safe operation to offer at
     * all.
     */
    public function removeItem(TenantContext $context, PriceListItem $item): void
    {
        $this->assertSameTenant($context, $item->tenant_id);

        $list = $item->priceList()->first();

        if ($list !== null) {
            $this->assertDraft($list);
        }

        $item->delete();
    }

    // ------------------------------------------------------------------
    // Guards
    // ------------------------------------------------------------------

    private function money(mixed $value): int
    {
        try {
            $amount = RupiahRounding::fromInput($value);
        } catch (InvalidArgumentException $exception) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                $exception->getMessage(),
                ['amount_rupiah' => ['invalid']]
            );
        }

        // INVARIANT P3 — a negative price is not a discount, it is a defect. A
        // discount is a separate concept and belongs to Step 5. The database
        // CHECK refuses it too; this produces the readable message.
        if ($amount < 0) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Harga tidak boleh negatif.',
                ['amount_rupiah' => ['negative']]
            );
        }

        return $amount;
    }

    /**
     * Exactly one of service / package / add-on, resolved WITHIN the tenant.
     *
     * A row priced against both a service and a package has no defined meaning;
     * a row priced against nothing is unusable. The database CHECK enforces the
     * count; this resolves each candidate inside the active tenant so a foreign
     * id produces a 404 rather than a constraint error (invariant P8).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{service_id: ?string, service_package_id: ?string, service_addon_id: ?string}
     */
    private function resolveExactlyOneTarget(TenantContext $context, array $attributes): array
    {
        $candidates = [
            'service_id' => $attributes['service_id'] ?? null,
            'service_package_id' => $attributes['service_package_id'] ?? null,
            'service_addon_id' => $attributes['service_addon_id'] ?? null,
        ];

        $present = array_filter($candidates, static fn (mixed $v): bool => $v !== null);

        if (count($present) !== 1) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Setiap baris harga harus merujuk tepat satu layanan, paket, atau tambahan.',
                ['target' => ['exactly_one_required']]
            );
        }

        $key = array_key_first($present);
        $id = (string) $present[$key];

        $model = match ($key) {
            'service_id' => Service::query()->forTenant($context->tenantId())->whereKey($id)->first(),
            'service_package_id' => ServicePackage::query()->forTenant($context->tenantId())->whereKey($id)->first(),
            'service_addon_id' => ServiceAddon::query()->forTenant($context->tenantId())->whereKey($id)->first(),
            default => null,
        };

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        // INVARIANT S7. A withdrawn service must not acquire a price that would
        // silently become live if it were reactivated.
        if ($model->getAttribute('is_active') === false) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Item yang tidak aktif tidak dapat diberi harga. Aktifkan kembali '
                .'item tersebut lebih dahulu.',
                ['target' => ['inactive']]
            );
        }

        return [...$candidates, $key => $id];
    }

    private function assertDraft(PriceList $priceList): void
    {
        if (! $priceList->isDraft()) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Daftar harga yang sudah diterbitkan bersifat permanen. Terbitkan '
                .'versi baru untuk mengubah harga.',
                ['price_list' => ['published']]
            );
        }
    }

    private function saveTranslatingUnique(PriceListItem $item): PriceListItem
    {
        try {
            $item->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'price_list_items_one_price_per')) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Item ini sudah memiliki harga pada daftar harga tersebut.',
                    ['target' => ['duplicate']]
                );
            }

            throw $exception;
        }

        return $item->refresh();
    }

    private function assertSameTenant(TenantContext $context, ?string $tenantId): void
    {
        if ($tenantId === null || ! hash_equals($context->tenantId(), $tenantId)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
