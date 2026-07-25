<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

/**
 * WHAT AN ADAPTER RETURNS — a closed set of first-party outcomes.
 *
 * A vendor's own status codes, error bodies, and exception classes stop at the
 * adapter. Whatever a provider says, it arrives here as one of six outcomes plus an
 * optional first-party failure code. That mapping is what keeps FR-093 true: the
 * dispatcher, the outbox, the API, and the UI all reason about `accepted` vs
 * `timeout` vs `rejected`, never about a vendor's numbering.
 *
 * `ACCEPTED` MEANS ACCEPTED, NOT DELIVERED
 * ----------------------------------------
 * The provider took the message. That is all it means and all it is ever rendered
 * as. We hold no delivery receipt, so claiming the customer received anything would
 * be a false claim (Rule 01). Every surface in this step says "diterima penyedia",
 * never "terkirim ke pelanggan".
 */
final class ProviderResult
{
    public const ACCEPTED = 'accepted';

    /** The provider understood and refused — bad number, template not approved, blocked recipient. */
    public const REJECTED = 'rejected';

    /** The adapter cannot send at all: credentials absent, adapter disabled. */
    public const UNAVAILABLE = 'unavailable';

    public const TIMEOUT = 'timeout';

    /** A response arrived but was not the shape the adapter expects. */
    public const MALFORMED = 'malformed';

    public const ERROR = 'error';

    private function __construct(
        public readonly string $outcome,
        public readonly ?string $providerReference = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $detail = null,
    ) {
    }

    public static function accepted(?string $providerReference = null): self
    {
        return new self(self::ACCEPTED, $providerReference);
    }

    public static function rejected(string $failureCode, ?string $detail = null): self
    {
        return new self(self::REJECTED, null, $failureCode, $detail);
    }

    public static function unavailable(string $failureCode = 'provider_unavailable', ?string $detail = null): self
    {
        return new self(self::UNAVAILABLE, null, $failureCode, $detail);
    }

    public static function timeout(?string $detail = null): self
    {
        return new self(self::TIMEOUT, null, 'provider_timeout', $detail);
    }

    public static function malformed(?string $detail = null): self
    {
        return new self(self::MALFORMED, null, 'provider_malformed_response', $detail);
    }

    public static function error(string $failureCode, ?string $detail = null): self
    {
        return new self(self::ERROR, null, $failureCode, $detail);
    }

    public function isAccepted(): bool
    {
        return $this->outcome === self::ACCEPTED;
    }

    /**
     * Is another attempt worth making?
     *
     * `REJECTED` is NOT retryable: the provider understood the request and refused
     * it, so repeating it produces the same refusal and burns the tenant's message
     * allowance. Everything else is a transport-class failure that may clear.
     */
    public function isRetryable(): bool
    {
        return in_array($this->outcome, [self::TIMEOUT, self::MALFORMED, self::ERROR, self::UNAVAILABLE], true);
    }
}
