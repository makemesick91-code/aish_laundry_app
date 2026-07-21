<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Http;

use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\ServiceCatalog\Models\PriceListItem;
use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\ServiceCatalog\Models\ServiceAddon;
use App\Modules\ServiceCatalog\Models\ServiceCategory;
use App\Modules\ServiceCatalog\Models\ServicePackage;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;

/**
 * THE CATALOGUE AND PRICE-LIST RESPONSE SHAPES — ALLOW-LISTS, NOT MODEL DUMPS.
 *
 * Every method names the fields it emits, so a column added tomorrow does not
 * appear in an API response until somebody adds it here on purpose (Rule 32
 * hard rule 7).
 *
 * MONEY IS EMITTED AS AN INTEGER, NEVER A FORMATTED STRING.
 * `amount_rupiah` goes out as a JSON number of whole Rupiah. Formatting it as
 * "Rp17.500" here would push a presentation decision into the transport and,
 * worse, invite a client to parse money back out of a display string — which
 * Rule 04 forbids in a money path. Rendering is the client's job, applied to an
 * integer.
 */
final class CatalogProjection
{
    /** @return array<string, mixed> */
    public static function category(ServiceCategory $category): array
    {
        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'display_order' => (int) $category->display_order,
            'is_active' => (bool) $category->is_active,
            'version' => OptimisticConcurrency::versionOf($category),
        ];
    }

    /** @return array<string, mixed> */
    public static function service(Service $service): array
    {
        return [
            'id' => $service->id,
            'service_category_id' => $service->service_category_id,
            'code' => $service->code,
            'name' => $service->name,
            'description' => $service->description,

            // FR-031. An enumerated unit, never free text: `kiloan` measures
            // weight and `satuan` measures items, and a downstream reader must
            // be able to tell which without parsing a label.
            'unit_kind' => $service->unit_kind,

            // Grams for kiloan, item count for satuan. The unit is implied by
            // `unit_kind` and stated here so a client never has to guess.
            'minimum_quantity' => $service->minimum_quantity === null
                ? null
                : (int) $service->minimum_quantity,
            'minimum_quantity_unit' => $service->unit_kind === Service::UNIT_KILOAN ? 'gram' : 'item',

            // DESCRIPTIVE ONLY. Step 4 makes no promise about completion time
            // and nothing enforces this value; presenting it as a guarantee
            // would be a capability claim the product does not provide
            // (Rule 01).
            'turnaround_hours' => $service->turnaround_hours === null
                ? null
                : (int) $service->turnaround_hours,

            'is_active' => (bool) $service->is_active,
            'display_order' => (int) $service->display_order,
            'version' => OptimisticConcurrency::versionOf($service),
        ];
    }

    /**
     * @param  list<array{service_id: string, quantity: int}>  $items
     * @return array<string, mixed>
     */
    public static function package(ServicePackage $package, array $items = []): array
    {
        return [
            'id' => $package->id,
            'code' => $package->code,
            'name' => $package->name,
            'description' => $package->description,
            'is_active' => (bool) $package->is_active,
            'display_order' => (int) $package->display_order,

            // Composition only. The package's PRICE is not here: it lives on a
            // per-brand price list, because the same package is priced
            // differently per brand (FR-034).
            'items' => $items,

            'version' => OptimisticConcurrency::versionOf($package),
        ];
    }

    /** @return array<string, mixed> */
    public static function addon(ServiceAddon $addon): array
    {
        return [
            'id' => $addon->id,
            'code' => $addon->code,
            'name' => $addon->name,
            'description' => $addon->description,
            'is_active' => (bool) $addon->is_active,
            'display_order' => (int) $addon->display_order,
            'version' => OptimisticConcurrency::versionOf($addon),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function priceList(PriceList $priceList, array $items = []): array
    {
        return [
            'id' => $priceList->id,
            'laundry_brand_id' => $priceList->laundry_brand_id,
            'code' => $priceList->code,
            'name' => $priceList->name,
            'currency' => $priceList->currency,
            'status' => $priceList->status,

            // FR-035. The effective window, as dates — a price list applies to
            // business days, not to instants.
            'effective_from' => $priceList->effective_from?->toDateString(),
            'effective_until' => $priceList->effective_until?->toDateString(),

            'is_default' => (bool) $priceList->is_default,
            'published_at' => $priceList->published_at?->toIso8601String(),
            'supersedes_price_list_id' => $priceList->supersedes_price_list_id,

            // Stated explicitly so a client renders a published list read-only
            // rather than inferring editability from the status string.
            'is_editable' => $priceList->isDraft(),

            'items' => $items,
            'version' => OptimisticConcurrency::versionOf($priceList),
        ];
    }

    /** @return array<string, mixed> */
    public static function priceListItem(PriceListItem $item): array
    {
        return [
            'id' => $item->id,
            'price_list_id' => $item->price_list_id,
            'service_id' => $item->service_id,
            'service_package_id' => $item->service_package_id,
            'service_addon_id' => $item->service_addon_id,

            // INTEGER RUPIAH. A JSON number, never a formatted string — see the
            // class docblock.
            'amount_rupiah' => (int) $item->amount_rupiah,
            'currency' => 'IDR',

            'version' => OptimisticConcurrency::versionOf($item),
        ];
    }
}
