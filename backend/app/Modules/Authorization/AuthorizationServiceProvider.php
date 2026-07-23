<?php

declare(strict_types=1);

namespace App\Modules\Authorization;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\Policies\AuditEntryPolicy;
use App\Modules\Authorization\Policies\CustomerPolicy;
use App\Modules\Authorization\Policies\DeviceSessionPolicy;
use App\Modules\Authorization\Policies\LaundryBrandPolicy;
use App\Modules\Authorization\Policies\MembershipPolicy;
use App\Modules\Authorization\Policies\OrderPolicy;
use App\Modules\Authorization\Policies\OutletPolicy;
use App\Modules\Authorization\Policies\PriceListPolicy;
use App\Modules\Authorization\Policies\ServicePolicy;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Organization\Models\Outlet;
use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\DeviceSession;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Step 3 authorization surface.
 *
 * SCOPE DISCIPLINE: policies exist here for STEP 3, STEP 4, and the STEP 5
 * order surface authorised by the canonical roadmap and DEC-0035 — tenant
 * context, membership, laundry brand, outlet, device session, role assignment,
 * permission inspection, audit read, the Step 4 master data (DEC-0028, DEC-0030),
 * and the Step 5 order aggregate.
 *
 * There is still no policy for a payment, a receipt, production, tracking, a
 * delivery, a reminder, or a subscription. Payment/receipt arrive later in
 * Step 5; production and beyond are Step 6+ and remain scope leakage until their
 * own step is authorised (CLAUDE.md §3, roadmap lock, Rule 36 hard rule 8).
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-request instance. Never a durable singleton: the whole point of
        // DEC-0025 §5 is that permissions are recomputed from live state.
        $this->app->scoped(EffectivePermissions::class);
    }

    public function boot(): void
    {
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(LaundryBrand::class, LaundryBrandPolicy::class);
        Gate::policy(Outlet::class, OutletPolicy::class);
        Gate::policy(DeviceSession::class, DeviceSessionPolicy::class);
        Gate::policy(AuditEntry::class, AuditEntryPolicy::class);

        // Step 4 master data (DEC-0028).
        Gate::policy(Customer::class, CustomerPolicy::class);

        // ONE policy governs the whole catalogue — category, service, package,
        // and add-on — registered against Service as its representative model.
        // They share a permission pair, and nobody would sensibly grant the
        // right to author services while withholding the right to author the
        // categories they sit in.
        Gate::policy(Service::class, ServicePolicy::class);

        Gate::policy(PriceList::class, PriceListPolicy::class);

        // Step 5 orders (DEC-0035, canonical roadmap authorisation).
        Gate::policy(Order::class, OrderPolicy::class);

        $this->defineContextGates();
    }

    /**
     * Gates for the Step 3 capabilities that have no Eloquent model behind them.
     */
    private function defineContextGates(): void
    {
        // The generic permission check, so a controller can ask
        // `Gate::allows('permission', PermissionRegistry::OUTLET_VIEW)` without
        // reaching into EffectivePermissions itself.
        Gate::define('permission', function (User $user, string $permission): bool {
            $context = $this->activeContext();

            if ($context === null) {
                return false;
            }

            return $this->app->make(EffectivePermissions::class)->has($context, $permission);
        });

        Gate::define('tenant-context.view', fn (User $user): bool => $this->holds(PermissionRegistry::TENANT_VIEW));

        Gate::define('tenant-context.switch', fn (User $user): bool => $this->holds(PermissionRegistry::TENANT_SWITCH));

        // Always granted to an active member: inspecting your OWN effective
        // permissions discloses nothing about anybody else, and withholding it
        // would leave a user unable to understand why an action was denied.
        Gate::define('authorization.inspect-own-permissions', fn (User $user): bool => $this->holds(PermissionRegistry::PERMISSION_INSPECT));

        // Listing and revoking your OWN sessions is self-service and is never
        // gated behind an administrative role.
        Gate::define('session.view-own', fn (User $user): bool => true);

        Gate::define('session.revoke-own', fn (User $user): bool => true);
    }

    private function holds(string $permission): bool
    {
        $context = $this->activeContext();

        if ($context === null) {
            return false;
        }

        return $this->app->make(EffectivePermissions::class)->has($context, $permission);
    }

    private function activeContext(): ?TenantContext
    {
        return $this->app->bound(TenantContext::class)
            ? $this->app->make(TenantContext::class)
            : null;
    }
}
