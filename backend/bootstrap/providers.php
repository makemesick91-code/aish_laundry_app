<?php

return [
    App\Providers\AppServiceProvider::class,

    /*
     * Registers the Step 3 policies and gates, and binds EffectivePermissions as
     * a request-scoped (never singleton) instance. Without this entry every
     * policy silently falls through to Laravel's default handling — which is how
     * an authorization gap comes to look like a working system (DEC-0025 §5).
     */
    App\Modules\Authorization\AuthorizationServiceProvider::class,

    /*
     * Points Sanctum at App\Modules\Identity\Models\AccessToken, which carries
     * the UUID key this schema requires and the revocation columns the session
     * error contract depends on. Registered before use, not remembered.
     */
    App\Modules\Identity\IdentityServiceProvider::class,
];
