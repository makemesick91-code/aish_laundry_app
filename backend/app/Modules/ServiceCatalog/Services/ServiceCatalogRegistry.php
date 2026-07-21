<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Services;

use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\ServiceCatalog\Models\ServiceAddon;
use App\Modules\ServiceCatalog\Models\ServiceCategory;
use App\Modules\ServiceCatalog\Models\ServicePackage;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only writer of the service catalogue (FR-031 … FR-033, FR-040).
 *
 * Every method takes the resolved TenantContext explicitly rather than reading
 * an ambient one, matching CustomerRegistry and OutletMasterDataRegistry. A
 * service that resolves its own tenant is one that can be called from a queue
 * worker with the wrong tenant (Rule 20 hard rule 6).
 *
 * THE CATALOGUE CARRIES NO PRICE, AND THAT IS DELIBERATE (FR-034, FR-040).
 * A service, package, or add-on says WHAT is sold. What it COSTS lives on a
 * per-brand price list. Storing a price on the catalogue row would make it
 * impossible to price the same service differently per brand, which FR-034
 * requires, and would give the product two places where a price could live —
 * which is exactly how a "single canonical price source" stops being single.
 *
 * INACTIVE IS NOT DELETED (threat T-18). Nothing here hard-deletes: a service a
 * future order references must remain resolvable, so deactivation and soft
 * delete are the only removals offered.
 */
final class ServiceCatalogRegistry
{
    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createCategory(TenantContext $context, array $attributes): ServiceCategory
    {
        $category = new ServiceCategory($this->only($attributes, ['code', 'name', 'display_order', 'is_active']));
        $category->tenant_id = $context->tenantId();

        return $this->saveTranslatingUnique($category, 'service_categories_tenant_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updateCategory(TenantContext $context, ServiceCategory $category, array $attributes): ServiceCategory
    {
        $this->assertSameTenant($context, $category);

        $category->fill($this->only($attributes, ['code', 'name', 'display_order', 'is_active']));

        return $this->saveTranslatingUnique($category, 'service_categories_tenant_code_unique');
    }

    // ------------------------------------------------------------------
    // Services (FR-031)
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createService(TenantContext $context, array $attributes): Service
    {
        $service = new Service($this->serviceAttributes($attributes));
        $service->tenant_id = $context->tenantId();

        $this->assertCategoryInTenant($context, $attributes['service_category_id'] ?? null);
        $this->assertMinimumMatchesUnitKind($service);

        return $this->saveTranslatingUnique($service, 'service_catalog_tenant_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updateService(TenantContext $context, Service $service, array $attributes): Service
    {
        $this->assertSameTenant($context, $service);

        if (array_key_exists('service_category_id', $attributes)) {
            $this->assertCategoryInTenant($context, $attributes['service_category_id']);
        }

        $service->fill($this->serviceAttributes($attributes));
        $this->assertMinimumMatchesUnitKind($service);

        return $this->saveTranslatingUnique($service, 'service_catalog_tenant_code_unique');
    }

    // ------------------------------------------------------------------
    // Packages (FR-032)
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createPackage(TenantContext $context, array $attributes): ServicePackage
    {
        $package = new ServicePackage($this->only($attributes, ['code', 'name', 'description', 'is_active', 'display_order']));
        $package->tenant_id = $context->tenantId();

        return $this->saveTranslatingUnique($package, 'service_packages_tenant_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updatePackage(TenantContext $context, ServicePackage $package, array $attributes): ServicePackage
    {
        $this->assertSameTenant($context, $package);

        $package->fill($this->only($attributes, ['code', 'name', 'description', 'is_active', 'display_order']));

        return $this->saveTranslatingUnique($package, 'service_packages_tenant_code_unique');
    }

    /**
     * Set a package's composition, replacing it wholesale.
     *
     * REPLACE RATHER THAN PATCH, because a composition is only meaningful as a
     * whole. Adding and removing lines one request at a time leaves the package
     * transiently describing something the tenant never intended, and a failure
     * halfway through leaves it there permanently.
     *
     * Every referenced service is re-resolved WITHIN the active tenant, so a
     * service id from another tenant does not resolve — the composite foreign
     * key would refuse it too, and refusing here gives a clean 404 instead of a
     * constraint error (invariant S5).
     *
     * @param  list<array{service_id: string, quantity: int}>  $items
     */
    public function setPackageItems(TenantContext $context, ServicePackage $package, array $items): ServicePackage
    {
        $this->assertSameTenant($context, $package);

        $serviceIds = array_values(array_unique(array_map(
            static fn (array $i): string => $i['service_id'],
            $items
        )));

        $resolved = Service::query()
            ->forTenant($context->tenantId())
            ->whereIn('id', $serviceIds)
            ->pluck('id')
            ->all();

        if (count($resolved) !== count($serviceIds)) {
            // Identical to "no such service", so a caller cannot use the
            // composition endpoint to discover another tenant's catalogue
            // (Rule 48 hard rule 5).
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        DB::transaction(function () use ($context, $package, $items): void {
            DB::table('service_package_items')
                ->where('tenant_id', $context->tenantId())
                ->where('service_package_id', $package->id)
                ->delete();

            foreach ($items as $item) {
                DB::table('service_package_items')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $context->tenantId(),
                    'service_package_id' => $package->id,
                    'service_id' => $item['service_id'],
                    'quantity' => $item['quantity'],
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $package->refresh();
    }

    // ------------------------------------------------------------------
    // Add-ons (FR-033) — CATALOGUE ENTRIES ONLY. Applying one to an order
    // line is Step 5 (DEC-0031 B), and nothing here links to anything
    // orderable.
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    public function createAddon(TenantContext $context, array $attributes): ServiceAddon
    {
        $addon = new ServiceAddon($this->only($attributes, ['code', 'name', 'description', 'is_active', 'display_order']));
        $addon->tenant_id = $context->tenantId();

        return $this->saveTranslatingUnique($addon, 'service_addons_tenant_code_unique');
    }

    /** @param array<string, mixed> $attributes */
    public function updateAddon(TenantContext $context, ServiceAddon $addon, array $attributes): ServiceAddon
    {
        $this->assertSameTenant($context, $addon);

        $addon->fill($this->only($attributes, ['code', 'name', 'description', 'is_active', 'display_order']));

        return $this->saveTranslatingUnique($addon, 'service_addons_tenant_code_unique');
    }

    // ------------------------------------------------------------------
    // Guards and plumbing
    // ------------------------------------------------------------------

    /**
     * INVARIANT S3 — a kiloan service measures WEIGHT, a satuan service measures
     * ITEMS, and `minimum_quantity` means a different thing in each.
     *
     * Grams for kiloan, item count for satuan. Both integers, so there is no
     * floating-point weight for the scale and the counter to disagree about.
     * The check is that the value is coherent, not merely present: a minimum of
     * zero is not a minimum.
     */
    private function assertMinimumMatchesUnitKind(Service $service): void
    {
        if ($service->minimum_quantity === null) {
            return;
        }

        if ((int) $service->minimum_quantity <= 0) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                $service->unit_kind === Service::UNIT_KILOAN
                    ? 'Berat minimum harus lebih besar dari nol, dinyatakan dalam gram.'
                    : 'Jumlah minimum harus lebih besar dari nol.',
                ['minimum_quantity' => ['must_be_positive']]
            );
        }
    }

    private function assertCategoryInTenant(TenantContext $context, mixed $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $exists = ServiceCategory::query()
            ->forTenant($context->tenantId())
            ->whereKey($categoryId)
            ->exists();

        if (! $exists) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }

    /** @param array<string, mixed> $attributes */
    private function serviceAttributes(array $attributes): array
    {
        return $this->only($attributes, [
            'service_category_id',
            'code',
            'name',
            'description',
            'unit_kind',
            'minimum_quantity',
            'turnaround_hours',
            'is_active',
            'effective_from',
            'effective_until',
            'display_order',
        ]);
    }

    /**
     * Translate the database's uniqueness refusal into a field-level message.
     *
     * Not a read-then-write check: two concurrent creates would both see no
     * clash and both commit. The unique index decides; this turns its refusal
     * into something an operator can act on.
     */
    private function saveTranslatingUnique(Model $model, string $codeIndex): Model
    {
        try {
            $model->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), $codeIndex)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Kode ini sudah dipakai pada tenant Anda.',
                    ['code' => ['duplicate']]
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

    private function assertSameTenant(TenantContext $context, Model $model): void
    {
        $tenantId = $model->getAttribute('tenant_id');

        if (! is_string($tenantId) || ! hash_equals($context->tenantId(), $tenantId)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
