<?php

use App\Http\Controllers\HealthController;
use App\Modules\Authorization\Http\Controllers\PermissionController;
use App\Modules\CustomerManagement\Http\Controllers\CustomerConsentController;
use App\Modules\CustomerManagement\Http\Controllers\CustomerController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\SessionController;
use App\Modules\Organization\Http\Controllers\OutletMasterDataController;
use App\Modules\Organization\Http\Controllers\StaffAssignmentController;
use App\Modules\ServiceCatalog\Http\Controllers\PriceListController;
use App\Modules\ServiceCatalog\Http\Controllers\ServiceCatalogController;
use App\Modules\Tenancy\Http\Controllers\ContextController;
use App\Modules\Tenancy\Http\Controllers\MembershipController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes
|--------------------------------------------------------------------------
|
| Mounted under /api/v1 by bootstrap/app.php (Rule 06 — the API is versioned and
| every client surface consumes the same versioned HTTP API).
|
| Step 3 registered operational, authentication, tenancy and RBAC routes. Step 4
| adds LAUNDRY MASTER DATA under DEC-0028 and DEC-0030: customers, their
| addresses and consent, the service catalogue, price lists, outlet master data,
| and staff assignment.
|
| There is still deliberately no route here for an order, a payment, a receipt,
| production, tracking, a pickup, a delivery, a reminder, a receivable, or a
| subscription: every one of those belongs to Step 5 or later, and adding it
| early is scope leakage (CLAUDE.md §3 — roadmap lock).
|
| Note what is ABSENT from the Step 4 block below and is absent on purpose: no
| bulk-mutation route and no export route (threats T-19, T-20). Their absence is
| asserted by test rather than assumed.
|
| THREE ACCESS TIERS, applied by middleware rather than remembered per handler:
|
|   (a) PUBLIC              — operational probes and the unauthenticated half of
|                             authentication.
|   (b) auth.api            — an authenticated identity, but NO tenant yet. This
|                             is where a user chooses which tenant to act in.
|   (c) auth.api + tenant.context
|                           — an authenticated identity AND a server-verified
|                             ACTIVE membership in the selected tenant.
|
| Anything touching tenant data lives in tier (c). No exceptions (Rule 02).
*/

// ---------------------------------------------------------------------------
// (a) PUBLIC
// ---------------------------------------------------------------------------

Route::get('health', [HealthController::class, 'health'])->name('api.v1.health');
Route::get('readiness', [HealthController::class, 'readiness'])->name('api.v1.readiness');

Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

// Both password-reset endpoints are rate-limited internally and both respond
// identically for known and unknown accounts — see PasswordResetController.
Route::post('auth/password-reset/request', [PasswordResetController::class, 'request'])
    ->name('api.v1.auth.password-reset.request');
Route::post('auth/password-reset/complete', [PasswordResetController::class, 'complete'])
    ->name('api.v1.auth.password-reset.complete');

// ---------------------------------------------------------------------------
// (b) AUTHENTICATED, NO TENANT CONTEXT REQUIRED
//
// You cannot require an active tenant in order to choose one. These endpoints
// compensate by scoping every query to the authenticated user's own records.
// ---------------------------------------------------------------------------

Route::middleware('auth.api')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');

    // Session self-service. Scoped to the caller's own sessions throughout;
    // a user may never touch anybody else's.
    Route::get('sessions', [SessionController::class, 'index'])->name('api.v1.sessions.index');
    Route::post('sessions/revoke-others', [SessionController::class, 'revokeOthers'])
        ->name('api.v1.sessions.revoke-others');
    Route::delete('sessions/{session}', [SessionController::class, 'revoke'])
        ->name('api.v1.sessions.revoke');

    // Tenant selection.
    Route::get('context/tenants', [ContextController::class, 'tenants'])->name('api.v1.context.tenants');
    Route::post('context/tenant', [ContextController::class, 'selectTenant'])->name('api.v1.context.tenant');
});

// ---------------------------------------------------------------------------
// (c) AUTHENTICATED **AND** TENANT-RESOLVED
//
// Every handler below runs with an immutable, server-verified TenantContext.
// ---------------------------------------------------------------------------

Route::middleware(['auth.api', 'tenant.context'])->group(function (): void {
    Route::get('context/outlets', [ContextController::class, 'outlets'])->name('api.v1.context.outlets');
    Route::post('context/outlet', [ContextController::class, 'selectOutlet'])->name('api.v1.context.outlet');

    Route::get('memberships/current', [MembershipController::class, 'current'])
        ->name('api.v1.memberships.current');

    Route::get('authorization/permissions', [PermissionController::class, 'index'])
        ->name('api.v1.authorization.permissions');

    // -----------------------------------------------------------------------
    // STEP 4 — LAUNDRY MASTER DATA (FR-021 … FR-047)
    // -----------------------------------------------------------------------

    // Customers (FR-021 … FR-030). No destroy route: a customer referenced by a
    // future order must stay resolvable, so archival replaces deletion (T-18).
    Route::get('customers', [CustomerController::class, 'index'])->name('api.v1.customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('api.v1.customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('api.v1.customers.show');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('api.v1.customers.update');
    Route::post('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('api.v1.customers.archive');

    // Consent (FR-027, FR-028). Read and APPEND only — no update, no delete.
    Route::get('customers/{customer}/consents', [CustomerConsentController::class, 'index'])
        ->name('api.v1.customers.consents.index');
    Route::post('customers/{customer}/consents', [CustomerConsentController::class, 'store'])
        ->name('api.v1.customers.consents.store');

    // -----------------------------------------------------------------------
    // Outlet master data (FR-041 … FR-047).
    //
    // Every satellite is nested UNDER its outlet, so the tenant-scoped outlet
    // lookup happens before the satellite is addressed at all. A flat
    // `/zones/{id}` route would make the outlet a body field somebody could
    // aim at another tenant, and would rely on a foreign key to say no.
    //
    // No destroy route anywhere below: a zone, shift, or printer a future order
    // or delivery references must stay resolvable, so `is_active: false`
    // replaces deletion (T-18).
    // -----------------------------------------------------------------------
    Route::get('outlets/{outlet}/master-data', [OutletMasterDataController::class, 'show'])
        ->name('api.v1.outlets.master-data.show');
    Route::patch('outlets/{outlet}/master-data', [OutletMasterDataController::class, 'update'])
        ->name('api.v1.outlets.master-data.update');

    // FR-043 — coverage definition only. Routing is Step 8.
    Route::get('outlets/{outlet}/service-zones', [OutletMasterDataController::class, 'zones'])
        ->name('api.v1.outlets.service-zones.index');
    Route::post('outlets/{outlet}/service-zones', [OutletMasterDataController::class, 'storeZone'])
        ->name('api.v1.outlets.service-zones.store');
    Route::patch('outlets/{outlet}/service-zones/{zone}', [OutletMasterDataController::class, 'updateZone'])
        ->name('api.v1.outlets.service-zones.update');

    // FR-044 — definitions only. Shift closing and cash variance are Step 5.
    Route::get('outlets/{outlet}/shifts', [OutletMasterDataController::class, 'shifts'])
        ->name('api.v1.outlets.shifts.index');
    Route::post('outlets/{outlet}/shifts', [OutletMasterDataController::class, 'storeShift'])
        ->name('api.v1.outlets.shifts.store');
    Route::patch('outlets/{outlet}/shifts/{shift}', [OutletMasterDataController::class, 'updateShift'])
        ->name('api.v1.outlets.shifts.update');

    // FR-045 — printer CONFIGURATION. The document a printer prints is FR-052
    // in Step 5, and `receipt`/`nota`/`struk` remain forbidden (DEC-0030).
    Route::get('outlets/{outlet}/printers', [OutletMasterDataController::class, 'printers'])
        ->name('api.v1.outlets.printers.index');
    Route::post('outlets/{outlet}/printers', [OutletMasterDataController::class, 'storePrinter'])
        ->name('api.v1.outlets.printers.store');
    Route::patch('outlets/{outlet}/printers/{printer}', [OutletMasterDataController::class, 'updatePrinter'])
        ->name('api.v1.outlets.printers.update');

    // FR-046 — tenant-wide proof policy. CONFIGURATION only; capturing a proof
    // at a custody transfer is Step 8. Not nested under an outlet because the
    // policy is tenant-wide by design (see OutletPolicy::manageProofPolicy).
    Route::get('proof-policy', [OutletMasterDataController::class, 'proofPolicy'])
        ->name('api.v1.proof-policy.show');
    Route::patch('proof-policy', [OutletMasterDataController::class, 'updateProofPolicy'])
        ->name('api.v1.proof-policy.update');

    // -----------------------------------------------------------------------
    // Staff assignment within the tenant (ROADMAP Step 4 scope, FR-018).
    //
    // TWO DIFFERENT ACTS, TWO DIFFERENT PERMISSIONS, kept apart on purpose:
    // assigning an OUTLET says where somebody works and confers nothing;
    // assigning a ROLE confers capability and passes the escalation guard.
    // One endpoint doing both would make the roster screen a privilege path.
    //
    // Step 4 introduces NO new role or permission model (DEC-0031 A2).
    // -----------------------------------------------------------------------
    Route::get('staff', [StaffAssignmentController::class, 'index'])
        ->name('api.v1.staff.index');
    Route::get('staff/{membership}', [StaffAssignmentController::class, 'show'])
        ->name('api.v1.staff.show');

    Route::post('staff/{membership}/outlets', [StaffAssignmentController::class, 'assignOutlet'])
        ->name('api.v1.staff.outlets.assign');

    // Revocation is a POST, not a DELETE: it RECORDS a revocation (who, when)
    // rather than removing the row, so the roster history a later audit needs
    // survives (DEC-0025 §6's discipline applied to assignment).
    Route::post('staff/{membership}/outlets/{assignment}/revoke', [StaffAssignmentController::class, 'revokeOutlet'])
        ->name('api.v1.staff.outlets.revoke');

    Route::post('staff/{membership}/roles', [StaffAssignmentController::class, 'assignRole'])
        ->name('api.v1.staff.roles.assign');
    Route::delete('staff/{membership}/roles/{role}', [StaffAssignmentController::class, 'removeRole'])
        ->name('api.v1.staff.roles.remove');

    // -----------------------------------------------------------------------
    // Service catalogue (FR-031 … FR-033, FR-040).
    //
    // The catalogue says WHAT is sold. What it COSTS is on a per-brand price
    // list below, because FR-034 requires the same service to be priced
    // differently per brand and FR-040 requires exactly one canonical source.
    //
    // No destroy route: a service a future order references must stay
    // resolvable, so `is_active: false` replaces deletion (T-18).
    // -----------------------------------------------------------------------
    Route::get('service-categories', [ServiceCatalogController::class, 'categories'])
        ->name('api.v1.service-categories.index');
    Route::post('service-categories', [ServiceCatalogController::class, 'storeCategory'])
        ->name('api.v1.service-categories.store');
    Route::patch('service-categories/{category}', [ServiceCatalogController::class, 'updateCategory'])
        ->name('api.v1.service-categories.update');

    Route::get('services', [ServiceCatalogController::class, 'services'])
        ->name('api.v1.services.index');
    Route::post('services', [ServiceCatalogController::class, 'storeService'])
        ->name('api.v1.services.store');
    Route::get('services/{service}', [ServiceCatalogController::class, 'showService'])
        ->name('api.v1.services.show');
    Route::patch('services/{service}', [ServiceCatalogController::class, 'updateService'])
        ->name('api.v1.services.update');

    Route::get('service-packages', [ServiceCatalogController::class, 'packages'])
        ->name('api.v1.service-packages.index');
    Route::post('service-packages', [ServiceCatalogController::class, 'storePackage'])
        ->name('api.v1.service-packages.store');
    Route::patch('service-packages/{package}', [ServiceCatalogController::class, 'updatePackage'])
        ->name('api.v1.service-packages.update');

    // PUT, and a wholesale replacement: a composition is only meaningful as a
    // whole, and patching it line by line leaves the package transiently
    // describing something the tenant never intended.
    Route::put('service-packages/{package}/items', [ServiceCatalogController::class, 'setPackageItems'])
        ->name('api.v1.service-packages.items.set');

    // FR-033 — CATALOGUE ENTRIES ONLY. Applying an add-on to an order line is
    // Step 5, and no route here links an add-on to anything orderable.
    Route::get('service-addons', [ServiceCatalogController::class, 'addons'])
        ->name('api.v1.service-addons.index');
    Route::post('service-addons', [ServiceCatalogController::class, 'storeAddon'])
        ->name('api.v1.service-addons.store');
    Route::patch('service-addons/{addon}', [ServiceCatalogController::class, 'updateAddon'])
        ->name('api.v1.service-addons.update');

    // -----------------------------------------------------------------------
    // Per-brand price lists (FR-034 … FR-040).
    //
    // Publishing has its OWN route and its OWN permission because it is the
    // irreversible act: a published version is frozen and becomes the price
    // customers are charged. There is no update route for a published list —
    // superseding creates a new version and leaves the prior one byte-identical
    // (FR-035, FR-036).
    //
    // No price-list destroy route: a published list is the record of what a past
    // order was charged. The single DELETE below removes an item from a DRAFT,
    // which has never priced anything.
    // -----------------------------------------------------------------------
    Route::get('price-lists', [PriceListController::class, 'index'])
        ->name('api.v1.price-lists.index');
    Route::post('price-lists', [PriceListController::class, 'store'])
        ->name('api.v1.price-lists.store');
    Route::get('price-lists/{priceList}', [PriceListController::class, 'show'])
        ->name('api.v1.price-lists.show');
    Route::post('price-lists/{priceList}/publish', [PriceListController::class, 'publish'])
        ->name('api.v1.price-lists.publish');

    Route::post('price-lists/{priceList}/items', [PriceListController::class, 'storeItem'])
        ->name('api.v1.price-lists.items.store');
    Route::patch('price-lists/{priceList}/items/{item}', [PriceListController::class, 'updateItem'])
        ->name('api.v1.price-lists.items.update');
    Route::delete('price-lists/{priceList}/items/{item}', [PriceListController::class, 'destroyItem'])
        ->name('api.v1.price-lists.items.destroy');
});
