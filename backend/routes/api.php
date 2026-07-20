<?php

use App\Http\Controllers\HealthController;
use App\Modules\Authorization\Http\Controllers\PermissionController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\SessionController;
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
| Step 3 registers operational, authentication, tenancy and RBAC routes ONLY.
| There is deliberately no route here for a customer, a service, a price list,
| an order, a payment, a receipt, production, tracking, a pickup, a delivery, a
| reminder, a receivable, or a subscription: every one of those belongs to Step 4
| or later, and adding it early is scope leakage (CLAUDE.md §3 — roadmap lock).
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
});
