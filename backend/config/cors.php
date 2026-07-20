<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| THE RULE THIS FILE EXISTS TO ENFORCE:
|
|   `supports_credentials => true` and `allowed_origins => ['*']` MUST NEVER
|   APPEAR TOGETHER.
|
| That combination lets ANY website on the internet issue authenticated,
| cookie-bearing requests to this API using a signed-in user's browser. It is
| the single most common way an otherwise careful SPA setup becomes a
| cross-site data-exfiltration path. Browsers reject the pair outright, and a
| developer's usual response to that rejection is to reflect the Origin header
| back — which is the same hole with extra steps.
|
| Origins are therefore an EXPLICIT ALLOWLIST, supplied by environment and
| defaulting to local development only. `allowed_origins_patterns` stays empty:
| a regex allowlist is where a subtly over-broad pattern hides.
|
*/

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('AISH_CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))
)));

if (in_array('*', $allowedOrigins, true)) {
    // Fail closed rather than silently shipping a wildcard with credentials.
    throw new RuntimeException(
        'AISH_CORS_ALLOWED_ORIGINS may not contain "*". This API sends credentials '
        .'(Sanctum cookie sessions), and a wildcard credentialed CORS policy would let any '
        .'site issue authenticated requests on a signed-in user\'s behalf. List origins explicitly.'
    );
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    // Deliberately empty. See the header comment.
    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'X-Request-Id',
        'X-Tenant-Id',
        'X-Outlet-Id',
        'X-Device-Id',
    ],

    // The correlation id is exposed so a browser client can surface it in a
    // support request. It carries no data of its own.
    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    /*
     * Required for the Sanctum SPA cookie flow. Safe ONLY because
     * `allowed_origins` above is an explicit allowlist and can never be '*'.
     */
    'supports_credentials' => true,

];
