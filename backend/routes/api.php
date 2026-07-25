<?php

use App\Http\Controllers\HealthController;
use App\Modules\Authorization\Http\Controllers\PermissionController;
use App\Modules\CustomerManagement\Http\Controllers\CustomerAddressController;
use App\Modules\CustomerManagement\Http\Controllers\CustomerConsentController;
use App\Modules\CustomerManagement\Http\Controllers\CustomerController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\SessionController;
use App\Modules\Notification\Http\Controllers\NotificationController;
use App\Modules\Ordering\Http\Controllers\OrderController;
use App\Modules\Tracking\Http\Controllers\PublicTrackingController;
use App\Modules\Tracking\Http\Controllers\TrackingLinkController;
use App\Modules\Production\Http\Controllers\BatchController;
use App\Modules\Production\Http\Controllers\ProductionController;
use App\Modules\Production\Http\Controllers\QualityControlEvidenceController;
use App\Modules\Organization\Http\Controllers\OutletMasterDataController;
use App\Modules\Organization\Http\Controllers\StaffAssignmentController;
use App\Modules\Payments\Http\Controllers\PaymentController;
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
| Step 5 adds orders and payments; Step 6 adds production operations (FR-071 …
| FR-085) under DEC-0037. There is still deliberately no route here for tracking,
| a pickup, a delivery, a reminder, a receivable, or a subscription: every one of
| those belongs to Step 7 or later, and adding it early is scope leakage
| (CLAUDE.md §3 — roadmap lock).
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

/*
 * STEP 7 — THE PUBLIC TRACKING PORTAL (FR-089 … FR-092), authorised by the
 * canonical roadmap and DEC-0039.
 *
 * UNAUTHENTICATED BY DESIGN. A customer must be able to follow their laundry with
 * no account and no app install (DEC-0006, DEC-0014). The token in the path IS the
 * credential, and everything that makes that safe is applied here rather than
 * remembered per handler:
 *
 *   - `public.tracking.headers` sets noindex, no-store, no-referrer, and a CSP that
 *     forbids every remote origin. `no-referrer` is load-bearing, not hygiene: the
 *     token is in the URL, so a referrer would hand it to a third party.
 *   - The resolver rate-limits per token-hash and per client IP, and returns ONE
 *     response for unknown, malformed, expired, revoked, superseded, and throttled
 *     (TRK-007, Rule 48 hard rule 5).
 *
 * There is NO public write route beyond the two OTP endpoints, because FR-086 …
 * FR-099 define no other customer-initiated portal write. Requesting a pickup or a
 * delivery from the portal is Step 8 and is deliberately absent (DEC-0039 §5).
 */
Route::middleware('public.tracking.headers')->group(function (): void {
    Route::get('public/tracking/{token}', [PublicTrackingController::class, 'show'])
        ->name('api.v1.public.tracking.show');
    Route::post('public/tracking/{token}/otp', [PublicTrackingController::class, 'requestOtp'])
        ->name('api.v1.public.tracking.otp.request');
    Route::post('public/tracking/{token}/otp/verify', [PublicTrackingController::class, 'verifyOtp'])
        ->name('api.v1.public.tracking.otp.verify');
});

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

    // Saved addresses (FR-024, FR-025). Masking is applied server-side by
    // AddressProjection; the list shape carries no location at any permission
    // level. Archive and reactivate are POSTs, never DELETE: an address a past
    // pickup went to is not removable (threat T-18).
    Route::get('customers/{customer}/addresses', [CustomerAddressController::class, 'index'])
        ->name('api.v1.customers.addresses.index');
    Route::get('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'show'])
        ->name('api.v1.customers.addresses.show');
    Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store'])
        ->name('api.v1.customers.addresses.store');
    Route::patch('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])
        ->name('api.v1.customers.addresses.update');
    Route::post('customers/{customer}/addresses/{address}/archive', [CustomerAddressController::class, 'archive'])
        ->name('api.v1.customers.addresses.archive');
    Route::post('customers/{customer}/addresses/{address}/reactivate', [CustomerAddressController::class, 'reactivate'])
        ->name('api.v1.customers.addresses.reactivate');

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

    // -----------------------------------------------------------------------
    // Step 5 — orders (FR-048 … FR-060) and payments (FR-061 … FR-069),
    // authorised by the canonical roadmap and DEC-0035.
    //
    // There is NO order destroy and NO payment update/destroy route: an order is
    // cancelled (with a reason) and a payment is reversed (with a reason), never
    // deleted — the ledger is append-only (FR-066). Placing and cancelling have
    // their own routes and permissions because each is a distinct control point.
    // -----------------------------------------------------------------------
    Route::get('orders', [OrderController::class, 'index'])->name('api.v1.orders.index');
    Route::post('orders', [OrderController::class, 'store'])->name('api.v1.orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('api.v1.orders.show');
    Route::post('orders/{order}/place', [OrderController::class, 'place'])->name('api.v1.orders.place');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('api.v1.orders.cancel');
    Route::get('orders/{order}/receipt', [OrderController::class, 'receipt'])->name('api.v1.orders.receipt');

    Route::get('orders/{order}/payments', [PaymentController::class, 'index'])->name('api.v1.orders.payments.index');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('api.v1.orders.payments.store');
    Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm'])->name('api.v1.payments.confirm');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('api.v1.payments.reverse');

    // Step 6 — production operations (FR-071 … FR-085), authorised by DEC-0037.
    // Each write is RBAC-gated (ProductionJobPolicy), idempotent on
    // client_reference, and optimistic on expected_version. A cashier/courier/
    // customer holds no production permission; a foreign job 404s like an absent
    // one (Rule 48).
    Route::get('production/queue', [ProductionController::class, 'index'])->name('api.v1.production.queue');
    Route::get('production/jobs/{job}', [ProductionController::class, 'show'])->name('api.v1.production.jobs.show');
    Route::post('production/jobs/{job}/advance', [ProductionController::class, 'advance'])->name('api.v1.production.jobs.advance');
    Route::post('production/jobs/{job}/block', [ProductionController::class, 'block'])->name('api.v1.production.jobs.block');
    Route::post('production/jobs/{job}/resume', [ProductionController::class, 'resume'])->name('api.v1.production.jobs.resume');
    Route::post('production/jobs/{job}/quality-control/send', [ProductionController::class, 'sendToQualityControl'])->name('api.v1.production.jobs.qc.send');
    Route::post('production/jobs/{job}/quality-control', [ProductionController::class, 'recordQualityControl'])->name('api.v1.production.jobs.qc.record');
    Route::post('production/jobs/{job}/rework/complete', [ProductionController::class, 'completeRework'])->name('api.v1.production.jobs.rework.complete');
    Route::post('production/jobs/{job}/ready', [ProductionController::class, 'markReady'])->name('api.v1.production.jobs.ready');

    // Step 6 · FR-083 QC defect-photo evidence. Upload gates on production.qc;
    // reads and signed-URL retrieval on production.view. Stored privately, served
    // only through short-lived signed URLs; a foreign job/inspection/evidence 404s
    // like an absent one (Rule 48).
    Route::post('production/jobs/{job}/quality-control/{inspection}/evidence', [QualityControlEvidenceController::class, 'store'])->name('api.v1.production.qc.evidence.store');
    Route::get('production/jobs/{job}/quality-control/{inspection}/evidence', [QualityControlEvidenceController::class, 'index'])->name('api.v1.production.qc.evidence.index');
    Route::get('production/jobs/{job}/quality-control/{inspection}/evidence/{evidence}/url', [QualityControlEvidenceController::class, 'url'])->name('api.v1.production.qc.evidence.url');

    // Step 6 · FR-074 batch operations. Reads gate on production.view; writes on
    // production.operate (PermissionRegistry — OPERATE covers "batch"). A CLOSED
    // batch is immutable; membership is tenant/outlet-safe and stage-compatible.
    Route::get('production/batches', [BatchController::class, 'index'])->name('api.v1.production.batches.index');
    Route::post('production/batches', [BatchController::class, 'store'])->name('api.v1.production.batches.store');
    Route::get('production/batches/{batch}', [BatchController::class, 'show'])->name('api.v1.production.batches.show');
    Route::patch('production/batches/{batch}', [BatchController::class, 'update'])->name('api.v1.production.batches.update');
    Route::post('production/batches/{batch}/close', [BatchController::class, 'close'])->name('api.v1.production.batches.close');
    Route::get('production/batches/{batch}/timeline', [BatchController::class, 'timeline'])->name('api.v1.production.batches.timeline');
    Route::post('production/batches/{batch}/items', [BatchController::class, 'addItem'])->name('api.v1.production.batches.items.add');
    Route::delete('production/batches/{batch}/items/{item}', [BatchController::class, 'removeItem'])->name('api.v1.production.batches.items.remove');

    // -----------------------------------------------------------------------
    // Step 7 — customer tracking links (FR-086 … FR-088), authorised by the
    // canonical roadmap and DEC-0039.
    //
    // Note what is ABSENT and is absent on purpose: there is NO list-all-tokens
    // route and NO export route. Either would be an enumeration surface over a
    // tenant's live customer credentials — the same reasoning that keeps bulk
    // mutation and export off the Step 4 master-data surface (threats T-19, T-20).
    // Their absence is asserted by test rather than assumed.
    //
    // The plaintext token is returned by exactly TWO of these routes — issue and
    // rotate — and once each. No route can retrieve it afterwards, because only its
    // hash was ever stored (TRK-002, TRK-019). There is deliberately no "resend the
    // link" route: recovery is rotation, which invalidates the lost one.
    //
    // Revoke and rotate are POSTs carrying a MANDATORY reason, never DELETEs: each
    // RECORDS an act with an actor and a reason (TRACKING_ACCESS_LIFECYCLE §9), and
    // the row survives as evidence.
    // -----------------------------------------------------------------------
    Route::get('orders/{order}/tracking-link', [TrackingLinkController::class, 'show'])
        ->name('api.v1.orders.tracking-link.show');
    Route::post('orders/{order}/tracking-link', [TrackingLinkController::class, 'store'])
        ->name('api.v1.orders.tracking-link.store');
    Route::post('tracking-links/{token}/rotate', [TrackingLinkController::class, 'rotate'])
        ->name('api.v1.tracking-links.rotate');
    Route::post('tracking-links/{token}/revoke', [TrackingLinkController::class, 'revoke'])
        ->name('api.v1.tracking-links.revoke');

    // -----------------------------------------------------------------------
    // Step 7 — notification history and dispatch (FR-093 … FR-099).
    //
    // Reads gate on notification.view; anything causing another send gates on
    // notification.send, because every send costs the tenant real money with a
    // third-party provider (Rule 14 guardrail 8, NOT-020).
    //
    // There is NO compose route: every message goes through a catalogued template
    // whose category is fixed, so no marketing message can be typed into a
    // transactional path (FR-096, NOT-024). There is NO delete route: both
    // notification tables are append-only, because "failures are visible" (FR-099)
    // is only true if they cannot be tidied away.
    // -----------------------------------------------------------------------
    Route::get('orders/{order}/notifications', [NotificationController::class, 'index'])
        ->name('api.v1.orders.notifications.index');
    Route::get('notifications/provider-state', [NotificationController::class, 'providerState'])
        ->name('api.v1.notifications.provider-state');
    Route::get('notifications/{intent}', [NotificationController::class, 'show'])
        ->name('api.v1.notifications.show');
    Route::post('notifications/{intent}/retry', [NotificationController::class, 'retry'])
        ->name('api.v1.notifications.retry');
    Route::post('notifications/{intent}/manual-link', [NotificationController::class, 'manualLink'])
        ->name('api.v1.notifications.manual-link');
});
