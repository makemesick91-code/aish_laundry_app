<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http;

use RuntimeException;
use Throwable;

/**
 * The one exception type application code throws to produce a governed error
 * response. It carries an ErrorCode, never an HTTP status chosen ad hoc, so the
 * status/code pairing stays consistent across every module.
 *
 * `$details` is rendered to the client and must therefore contain ONLY
 * information the caller is already entitled to — typically validation field
 * names. It never carries a token, a credential, an internal identifier from
 * another tenant, or a policy rationale.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details  Client-safe supplementary data.
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $message = null,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? $errorCode->defaultMessage(), $errorCode->httpStatus(), $previous);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function of(ErrorCode $code, ?string $message = null, array $details = []): self
    {
        return new self($code, $message, $details);
    }
}
