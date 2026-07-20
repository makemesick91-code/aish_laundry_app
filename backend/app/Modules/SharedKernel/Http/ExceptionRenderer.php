<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * TRANSLATES ANY THROWABLE INTO A GOVERNED ERROR ENVELOPE.
 *
 * THE INFORMATION-DISCLOSURE CONTRACT
 * -----------------------------------
 * What leaves this class is a stable code, a Bahasa Indonesia message, and — for
 * validation only — the field names that failed. What NEVER leaves it:
 *
 *   - a stack trace
 *   - SQL, a query fragment, a table name, or a constraint name
 *   - a database host, port, or username
 *   - Redis configuration or connection details
 *   - a file path or a class name
 *   - a token, a password, or any credential
 *   - a policy's internal reasoning
 *
 * `APP_DEBUG` DOES NOT WIDEN THIS. Laravel's default handler renders a full
 * trace when debug is on; that behaviour is deliberately not reproduced here.
 * On a PUBLIC-repository project where a staging URL can be found and probed,
 * "it was only leaking in debug mode" is not a mitigation (Rule 23 — assume a
 * hostile reader).
 *
 * The detail an operator needs still exists — it goes to the LOG, correlated by
 * request id, where the caller cannot read it.
 */
final class ExceptionRenderer
{
    public function render(Throwable $throwable, Request $request): JsonResponse
    {
        // Application-thrown errors already carry their code and a safe message.
        if ($throwable instanceof ApiException) {
            return ApiResponse::fromException($throwable);
        }

        if ($throwable instanceof ValidationException) {
            // Field names and validation messages are safe: they describe the
            // caller's OWN submission, nothing about stored data.
            return ApiResponse::error(
                ErrorCode::VALIDATION_FAILED,
                null,
                ['fields' => $throwable->errors()],
            );
        }

        if ($throwable instanceof TokenMismatchException) {
            return ApiResponse::error(ErrorCode::CSRF_FAILED);
        }

        if ($throwable instanceof AuthenticationException) {
            return ApiResponse::error(ErrorCode::UNAUTHENTICATED);
        }

        if ($throwable instanceof AuthorizationException || $throwable instanceof AccessDeniedHttpException) {
            // Never echoes the policy's own message: a policy message can name
            // the rule that failed, and naming the rule tells an attacker which
            // condition to satisfy next.
            return ApiResponse::error(ErrorCode::FORBIDDEN);
        }

        if ($throwable instanceof ModelNotFoundException || $throwable instanceof NotFoundHttpException) {
            // ModelNotFoundException carries the model CLASS NAME. Discarding it
            // matters: a 404 that names the model confirms the resource type
            // exists, and across a tenant boundary "not found" and "not yours"
            // must be indistinguishable (Rule 32, hard rule 2).
            return ApiResponse::error(ErrorCode::NOT_FOUND);
        }

        if ($throwable instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(ErrorCode::METHOD_NOT_ALLOWED);
        }

        if ($throwable instanceof TooManyRequestsHttpException) {
            return ApiResponse::error(ErrorCode::RATE_LIMITED);
        }

        if ($throwable instanceof QueryException) {
            // A QueryException's message contains the SQL, the bindings, and
            // often the database host and username. It is logged, never
            // returned. The caller is told the service had a problem.
            $this->logInternal($throwable, $request, 'database');

            return ApiResponse::error(ErrorCode::SERVICE_UNAVAILABLE);
        }

        $this->logInternal($throwable, $request, 'unclassified');

        return ApiResponse::error(ErrorCode::INTERNAL_ERROR);
    }

    /**
     * The operator's copy of what actually happened.
     *
     * Correlated by request id, so a caller who reports "request abc-123 failed"
     * leads straight to this entry without the caller ever having seen its
     * contents. The log context is additionally scrubbed by the redaction
     * processor before it is written.
     */
    private function logInternal(Throwable $throwable, Request $request, string $classification): void
    {
        Log::error('Unhandled API exception', [
            'classification' => $classification,
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }
}
