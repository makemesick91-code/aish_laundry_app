<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

/**
 * WHO ASKED FOR THIS OTP? (DEC-0040 decision item 3.)
 *
 * The DEC-0040 quiet-hours exemption is gated on an explicit customer request and on
 * NOTHING ELSE. That gate has to be something a caller cannot forget to state, so it
 * is a REQUIRED, TYPED argument to `OtpMessenger::send()` rather than an optional
 * flag defaulting to the permissive value.
 *
 * A default would have been the whole hole: every future caller would inherit
 * "customer requested this" without ever having established that a customer did.
 * Here a caller must name an origin, and only `CustomerRequest` is honoured —
 * anything else is REFUSED outright, not deferred and not quietly sent.
 *
 * `Automated` exists so that refusal is TESTABLE. Without a way to express a
 * non-customer origin, "an automated OTP is rejected" would be a claim with no
 * executable proof behind it (Rule 01).
 */
enum OtpDispatchOrigin: string
{
    /**
     * A customer explicitly requested this code, in this request cycle, from the
     * public tracking portal. The only origin that may be delivered.
     */
    case CustomerRequest = 'customer_request';

    /**
     * Anything else — a scheduler, a queue replay, a backfill, an operator action, a
     * future caller that has not established a customer asked. Always refused.
     */
    case Automated = 'automated';

    public function isCustomerInitiated(): bool
    {
        return $this === self::CustomerRequest;
    }

    /**
     * The audit classification this origin earns, or null when it earns none.
     *
     * Only a customer-initiated request is a `USER_INITIATED_SECURITY_TRANSACTION`.
     * An automated origin never acquires the classification, so it can never acquire
     * the exemption the classification carries.
     */
    public function securityClassification(): ?string
    {
        return $this->isCustomerInitiated()
            ? MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION
            : null;
    }
}
