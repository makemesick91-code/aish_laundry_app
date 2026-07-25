<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

/**
 * THE ONE MESSAGE CLASS THAT QUIET HOURS DO NOT DEFER (DEC-0040).
 *
 * WHY A CLASS AND NOT A BOOLEAN "URGENT" FLAG
 * -------------------------------------------
 * NOT-022 permits a quiet-hours exception only where the Master Source or an
 * accepted decision record explicitly grants one. DEC-0040 grants exactly one, and
 * names it, precisely so the exception cannot be argued outward: "urgent" is a
 * judgement every future message would be pushed into, whereas
 * `USER_INITIATED_SECURITY_TRANSACTION` is an OBSERVABLE FACT — the customer made an
 * explicit request, in this request cycle, seconds ago, while looking at the screen.
 *
 * Quiet hours 20.00–08.00 exist to stop a BUSINESS messaging a customer at an
 * unwelcome hour. They were never meant to stop a customer completing something they
 * themselves started. That distinction is the whole content of this class.
 *
 * WHAT CARRYING THIS CLASS DOES NOT BUY
 * -------------------------------------
 * Nothing except the quiet-hours schedule. Rate limiting, the resend cooldown, the
 * five-minute expiry, the attempt limit, single-use consumption, destination /
 * action / token / order binding, dedup, and the account-takeover rule (an OTP and a
 * tracking link never in one message — TRK-029, NOT-014) all still apply in full.
 *
 * IT ALSO CANNOT BE CLAIMED BY MARKETING
 * --------------------------------------
 * A message's category comes from its TEMPLATE and never from a caller (FR-096,
 * NOT-024). This classification is assigned only by `OtpMessenger`, which renders
 * exactly one template and requires a live plaintext code it holds for the duration
 * of one call. There is no argument a caller can pass to acquire it.
 *
 * A database CHECK on `notification_intents` refuses a row that carries this
 * classification AND `deferred_for_quiet_hours`, so the exemption is structural
 * rather than a convention some future code path must remember.
 */
final class MessageSecurityClassification
{
    /**
     * A security transaction the CUSTOMER explicitly asked for (DEC-0040, FR-091).
     *
     * Exempt from quiet hours. Never marketing, never a scheduled outbound
     * notification, and never assigned to a message no customer requested.
     */
    public const USER_INITIATED_SECURITY_TRANSACTION = 'USER_INITIATED_SECURITY_TRANSACTION';

    /**
     * The closed set. A value outside it is refused by the database CHECK, so
     * inventing a second exempt class requires a migration and — per DEC-0040's
     * supersession policy — a new decision record.
     *
     * @var list<string>
     */
    public const ALL = [
        self::USER_INITIATED_SECURITY_TRANSACTION,
    ];

    /** Does this classification carry the DEC-0040 quiet-hours exemption? */
    public static function isQuietHoursExempt(?string $classification): bool
    {
        return $classification === self::USER_INITIATED_SECURITY_TRANSACTION;
    }
}
