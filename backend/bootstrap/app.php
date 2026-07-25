<?php

use App\Modules\Identity\Http\Middleware\AuthenticateApiRequest;
use App\Modules\SharedKernel\Http\ExceptionRenderer;
use App\Modules\SharedKernel\Http\Middleware\AssignCorrelationId;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tracking\Http\Middleware\PublicTrackingHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * ORDER MATTERS AND IS DELIBERATE.
         *
         * 1. AssignCorrelationId runs FIRST so that a request failing anywhere
         *    later still carries an identifier in its error envelope. An error
         *    the caller cannot quote back is an error nobody can diagnose.
         *
         * 2. EnsureFrontendRequestsAreStateful promotes a FIRST-PARTY SPA
         *    request to a cookie session, applying CSRF validation and cookie
         *    encryption. A request from any other origin never becomes stateful,
         *    so a cross-site request cannot ride the user's session cookie.
         */
        $middleware->api(prepend: [
            AssignCorrelationId::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            // Establishes identity and distinguishes expired from revoked.
            'auth.api' => AuthenticateApiRequest::class,
            // Resolves and IMMUTABLY binds the tenant context. Always applied
            // AFTER auth.api — it needs an authenticated user to verify against.
            'tenant.context' => ResolveTenantContext::class,

            /*
             * Step 7 — the public tracking portal's transport contract (FR-092).
             *
             * Applied as ONE middleware rather than per handler, because a header
             * each controller must remember is a header that will eventually be
             * forgotten on exactly the response that needed it. It carries
             * `noindex`, `no-store`, `no-referrer`, and a CSP that forbids every
             * remote origin — see the class docblock for why each is load-bearing.
             */
            'public.tracking.headers' => PublicTrackingHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A SINGLE renderer for every API failure, so no controller can emit an
         * unenveloped error and no framework default can leak internals.
         */
        $exceptions->render(function (Throwable $throwable, Illuminate\Http\Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return app(ExceptionRenderer::class)->render($throwable, $request);
        });
    })->create();
