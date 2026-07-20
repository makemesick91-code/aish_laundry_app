<?php

declare(strict_types=1);

namespace App\Modules\Authorization;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\Policies\AuditEntryPolicy;
use App\Modules\Authorization\Policies\DeviceSessionPolicy;
use App\Modules\Authorization\Policies\LaundryBrandPolicy;
use App\Modules\Authorization\Policies\MembershipPolicy;
use App\Modules\Authorization\Policies\OutletPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\DeviceSession;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Step 3 authorization surface.
 *
 * SCOPE DISCIPLINE: policies exist here for STEP 3 RESOURCES ONLY — tenant
 * context, membership, laundry brand, outlet, device session, role assignment,
 * permission inspection, and audit read. There is no policy for a customer, an
 * order, a payment, a delivery, or a report, because none of those exists.
 * Adding one early would be scope leakage (CLAUDE.md §3, roadmap lock).
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
