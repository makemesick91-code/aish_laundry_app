<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http\Middleware;

use App\Modules\SharedKernel\Http\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the request correlation identifier before anything else runs, and
 * echoes it back on the response so a caller reporting a problem can quote an
 * identifier that appears in the server's own logs and audit trail.
 *
 * Registered FIRST in the API middleware stack: a request that fails in a later
 * middleware must still carry a correlation id in its error envelope.
 */
final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::fromClient($request->headers->get('X-Request-Id'));

        // Request-scoped: bound as an instance, so every consumer in this
        // request resolves the same value.
        app()->instance(CorrelationId::class, $correlationId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $correlationId->value);

        return $response;
    }
}
