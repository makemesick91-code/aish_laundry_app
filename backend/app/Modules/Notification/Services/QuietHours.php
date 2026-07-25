<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Contracts\MessageSecurityClassification;
use App\Modules\Organization\Models\Outlet;
use Carbon\CarbonTimeZone;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * QUIET HOURS — 20.00 to 08.00 OUTLET LOCAL TIME (FR-097, NOT-003, NOT-004).
 *
 * OUTLET LOCAL, NOT SERVER, NOT DEVICE
 * ------------------------------------
 * Indonesia spans WIB, WITA, and WIT. A tenant with outlets in Jakarta and Jayapura
 * is two hours apart, so evaluating quiet hours against server time would message
 * one set of customers at 22.00 their time while claiming to respect their evening.
 * The timezone comes from `outlets.timezone`, which Step 4 made a first-class field
 * for exactly this reason.
 *
 * MIDNIGHT CROSSING
 * -----------------
 * The window wraps, so it cannot be expressed as `start <= h < end`. It is
 * `h >= 20 || h < 8`, which puts 23.30 and 01.30 both inside it and defers both to
 * the SAME next 08.00 — 01.30 defers by six and a half hours, not by thirty-two.
 *
 * DEFER, NEVER DROP, NEVER SEND ANYWAY (NOT-021)
 * ----------------------------------------------
 * A message due inside the window is rescheduled to the next permitted moment. It
 * is not discarded and it is not sent regardless.
 *
 * THERE IS EXACTLY ONE EXCEPTION, AND IT IS NAMED (DEC-0040)
 * ----------------------------------------------------------
 * NOT-022 permits a quiet-hours exception ONLY where the Master Source or an
 * accepted decision record explicitly grants one. DEC-0040 grants exactly one, for
 * exactly one class: `USER_INITIATED_SECURITY_TRANSACTION` — a code the CUSTOMER
 * explicitly requested, in this request cycle, for an FR-091 sensitive action.
 *
 * The reasoning is that quiet hours exist to stop a BUSINESS messaging a customer at
 * an unwelcome hour, not to stop a customer completing something they themselves
 * started thirty seconds ago. A five-minute challenge deferred to 08.00 is not a
 * delayed message; it is a message that is never usefully sent, which made FR-091
 * unavailable for twelve hours a day.
 *
 * Note what the exception is NOT gated on: urgency, importance, or category. It is
 * gated on the observable fact of an explicit customer request, established by
 * `OtpDispatchOrigin` at the one call site that holds a live plaintext code. "Urgent"
 * was rejected deliberately — it is a judgement every future message would be argued
 * into, and it would become the route every message quietly took.
 *
 * Every other message class, INCLUDING every other transactional template, defers
 * exactly as before. `NotificationPolicyTest` asserts that across the whole
 * catalogue, so the exception stays demonstrably confined to the one class.
 */
final class QuietHours
{
    /** Inclusive. 20:00 is INSIDE the window; 19:59 is not. */
    public const START_HOUR = 20;

    /** Exclusive. 08:00 is OUTSIDE the window; 07:59 is not. */
    public const END_HOUR = 8;

    /**
     * Does this message class carry the single DEC-0040 exemption?
     *
     * The exemption lives HERE, next to the window it exempts, rather than in each
     * caller — a caller that decides for itself whether quiet hours apply is a
     * caller that will eventually decide wrongly.
     */
    public static function isExemptClassification(?string $securityClassification): bool
    {
        return MessageSecurityClassification::isQuietHoursExempt($securityClassification);
    }

    /**
     * Should this message be deferred right now?
     *
     * The one predicate every send path asks. An exempt class is never deferred,
     * whatever the hour; everything else defers whenever the window is open.
     */
    public static function shouldDefer(Outlet $outlet, Carbon $instant, ?string $securityClassification = null): bool
    {
        if (self::isExemptClassification($securityClassification)) {
            return false;
        }

        return self::isQuiet($outlet, $instant);
    }

    /**
     * Is the given instant inside quiet hours for this outlet?
     *
     * FAILS CLOSED on a bad timezone. An outlet whose timezone is missing or
     * unparseable is treated as INSIDE quiet hours, so the message defers and a
     * human notices the misconfiguration — rather than being sent at an unknown
     * local hour, which is the exact harm quiet hours exist to prevent.
     */
    public static function isQuiet(Outlet $outlet, Carbon $instant): bool
    {
        $zone = self::zoneFor($outlet);

        if ($zone === null) {
            return true;
        }

        $hour = (int) $instant->copy()->setTimezone($zone)->format('G');

        return $hour >= self::START_HOUR || $hour < self::END_HOUR;
    }

    /**
     * The next instant at which sending is permitted, in UTC.
     *
     * Returns the given instant unchanged when it is already permitted — the caller
     * can therefore always assign the result to `scheduled_for` without branching.
     *
     * On an unusable timezone the message is deferred by a fixed, conservative
     * amount rather than to a computed local 08.00 that cannot be computed. It
     * still defers; it is still never dropped.
     */
    public static function nextPermitted(Outlet $outlet, Carbon $instant): Carbon
    {
        $zone = self::zoneFor($outlet);

        if ($zone === null) {
            return $instant->copy()->addHours(12);
        }

        $local = $instant->copy()->setTimezone($zone);
        $hour = (int) $local->format('G');

        if ($hour >= self::END_HOUR && $hour < self::START_HOUR) {
            return $instant->copy();
        }

        // Before 08.00 → this morning. At or after 20.00 → tomorrow morning.
        $target = $hour < self::END_HOUR
            ? $local->copy()->setTime(self::END_HOUR, 0, 0)
            : $local->copy()->addDay()->setTime(self::END_HOUR, 0, 0);

        return $target->setTimezone('UTC');
    }

    /**
     * Resolve the outlet's timezone, or null when it cannot be trusted.
     *
     * Null is the fail-closed signal. `CarbonTimeZone::create()` returns false on an
     * unknown identifier in some versions and throws in others, so both are handled
     * — a timezone check that throws inside the dispatcher would turn a
     * misconfiguration into a messaging outage.
     */
    private static function zoneFor(Outlet $outlet): ?CarbonTimeZone
    {
        $name = trim((string) ($outlet->timezone ?? ''));

        if ($name === '') {
            return null;
        }

        try {
            $zone = CarbonTimeZone::create($name);
        } catch (Throwable) {
            return null;
        }

        return $zone instanceof CarbonTimeZone ? $zone : null;
    }
}
