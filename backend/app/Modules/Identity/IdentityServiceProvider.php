<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Models\AccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Binds Sanctum to this module's access-token model.
 *
 * WHY THIS IS LOAD-BEARING AND NOT COSMETIC
 * -----------------------------------------
 * Sanctum defaults to its own `PersonalAccessToken`. That model has neither the
 * UUID key this schema requires nor the `revoked_at` / `revoked_by_user_id`
 * columns the API's error contract depends on. Without this binding:
 *
 *   - `createToken()` writes a NULL primary key into a `uuid ... primary`
 *     column and the insert is rejected outright; and
 *   - revocation would have to be expressed by DELETING the row, which cannot
 *     distinguish "deliberately revoked" from "never existed", collapsing
 *     SESSION_REVOKED into a bare UNAUTHENTICATED.
 *
 * The distinction between expired, revoked, and unknown is a stated part of the
 * session contract, so the binding that makes it possible belongs in the
 * application's boot path rather than in a caller's memory.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Sanctum::usePersonalAccessTokenModel(AccessToken::class);
    }
}
