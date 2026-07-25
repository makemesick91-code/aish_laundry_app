<?php

use App\Modules\Tracking\Http\Controllers\PublicTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Step 3 exposed no web surface at all: every client consumed the versioned HTTP
| API under /api/v1 (Rule 06). Step 7 adds EXACTLY ONE web route, and it is the
| public tracking portal.
|
| WHY THE PORTAL IS SERVER-RENDERED HERE AND NOT A FLUTTER SURFACE
| ---------------------------------------------------------------
| Rule 05 and TRACKING_DOMAIN §9 both state that Flutter is NOT mandatory for this
| surface and that a lighter web stack is permitted if it loads materially faster
| on low-end Android browsers. This is the most performance-critical surface in
| the product — opened once, on an unknown device, over an unknown network, by a
| customer who did not choose to install anything.
|
| Server-rendered Blade is the lightest option available and, importantly, it
| introduces NO new dependency, NO new toolchain, and NO third-party asset: Blade
| ships with the Laravel runtime Step 3 already established. The page carries no
| script at all.
|
| OQ-014 (which web stack the portal uses) required this choice to be recorded in
| a decision record by the Step that builds it. The repository owner RATIFIED it on
| 26 July 2026 as DEC-0041, so this is now a settled product decision rather than a
| provisional implementation.
|
| DEC-0041 fences what it ratifies, and the fence is binding here: Blade is for the
| PUBLIC TRACKING PORTAL ONLY. A second Blade surface — an operator page, an admin
| page, a login page — is outside that record and needs its own. Token validation,
| tenant isolation, the projection, masking, consent, and the notification rules
| stay in canonical services; a view renders an already-decided projection and never
| re-derives one. No persistent browser storage of the token, no public session, and
| no Step 8/Step 9 control on this surface.
|
| `scripts/validate-dec-0041-portal-stack.py` audits all of that structurally, so
| the boundary is a gate rather than a paragraph.
|
| The route is not under /api/v1 because it serves HTML to a browser, not JSON to
| a client. The JSON projection of the same data IS under /api/v1
| (`public/tracking/{token}`), so no surface gets a private back channel.
*/

Route::middleware('public.tracking.headers')->group(function (): void {
    Route::get('lacak/{token}', [PublicTrackingController::class, 'page'])
        ->name('web.tracking.page');
});
