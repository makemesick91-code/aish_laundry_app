<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes
|--------------------------------------------------------------------------
|
| Mounted under the /api/v1 prefix by bootstrap/app.php (Rule 06 — the API is
| versioned and every client surface consumes the same versioned HTTP API).
|
| Step 3 Phase A registers operational endpoints only. No authentication,
| tenancy, or RBAC route exists yet, and no business feature route may be
| added here before its own Step.
|
*/

Route::get('health', [HealthController::class, 'health'])->name('api.v1.health');
Route::get('readiness', [HealthController::class, 'readiness'])->name('api.v1.readiness');
