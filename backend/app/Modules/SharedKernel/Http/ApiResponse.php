<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http;

use Illuminate\Http\JsonResponse;

/**
 * THE SINGLE RESPONSE ENVELOPE.
 *
 * Every `/api/v1` response — success or failure — is built here, so the shape
 * cannot drift per controller (Rule 06: "a consistent envelope, consistent error
 * shape").
 *
 *   success:  { "data": <payload>, "meta": { "request_id": "..." } }
 *   failure:  { "error": { "code": "...", "message": "...", "details": {...} },
 *               "meta": { "request_id": "..." } }
 *
 * `details` is omitted entirely when empty rather than rendered as `null`, so a
 * client never has to distinguish "absent" from "null".
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => array_merge(['request_id' => self::requestId()], $meta),
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(ErrorCode $code, ?string $message = null, array $details = []): JsonResponse
    {
        $error = [
            'code' => $code->value,
            'message' => $message ?? $code->defaultMessage(),
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return new JsonResponse([
            'error' => $error,
            'meta' => ['request_id' => self::requestId()],
        ], $code->httpStatus());
    }

    public static function fromException(ApiException $exception): JsonResponse
    {
        return self::error($exception->errorCode, $exception->getMessage(), $exception->details);
    }

    private static function requestId(): string
    {
        if (app()->bound(CorrelationId::class)) {
            return app(CorrelationId::class)->value;
        }

        return CorrelationId::generate()->value;
    }
}
