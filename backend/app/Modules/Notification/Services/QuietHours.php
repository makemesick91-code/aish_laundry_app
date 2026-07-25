<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

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
 * THERE IS NO EXCEPTION PATH, AND THAT IS DELIBERATE
 * --------------------------------------------------
 * NOT-022 permits a quiet-hours exception ONLY where the Master Source or an
 * accepted decision record explicitly grants one. None does. So OTP messages defer
 * like everything else: a customer requesting a code at 02.00 is told it will be
 * sent at 08.00 rather than being messaged anyway. Building an "urgent" bypass
 * without a record would be inventing a product decision (Rule 00 hard rule 6), and
 * the bypass would then be the route every future message quietly took.
 */
final class QuietHours
{
    /** Inclusive. 20:00 is INSIDE the window; 19:59 is not. */
    public const START_HOUR = 20;

    /** Exclusive. 08:00 is OUTSIDE the window; 07:59 is not. */
    public const END_HOUR = 8;

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
