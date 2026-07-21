<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A LOCAL WALL-CLOCK TIME — deliberately not an instant (FR-041, FR-047).
 *
 * An outlet that opens at 08.00 opens at 08.00. Not at 01:00Z, not at "whatever
 * 08.00 was on the day the row was written". Rule 43 puts timezone conversion at
 * the application layer and keeps it out of the schema, and this class is where
 * that conversion happens.
 *
 * WHY THIS TYPE EXISTS AT ALL
 * ---------------------------
 * PHP will happily let `"08:00"` be a string everywhere, and the bug that
 * follows is always the same: somebody compares an opening time to a UTC
 * timestamp, it works in Asia/Jakarta because the developer's machine is in
 * Asia/Jakarta, and it is seven hours wrong in Asia/Jayapura. Making the
 * wall-clock a type forces the timezone to be named at the moment of conversion.
 *
 * INDONESIA SPANS THREE TIMEZONES — WIB, WITA, WIT — so this is not a
 * hypothetical edge case for this product. It is Tuesday.
 */
final class WallClockTime
{
    private function __construct(
        public readonly int $hour,
        public readonly int $minute,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the value is not `HH:MM` 24-hour
     */
    public static function parse(string $value): self
    {
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Waktu "%s" tidak valid. Gunakan format 24 jam HH:MM.',
                $value
            ));
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function toString(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    /** Minutes since local midnight. The comparable form. */
    public function minutesFromMidnight(): int
    {
        return ($this->hour * 60) + $this->minute;
    }

    /**
     * Resolve this wall-clock time to an actual instant on a given local date.
     *
     * The timezone is a REQUIRED argument. There is no default and no fallback to
     * the application timezone: a default here would be the exact bug this class
     * exists to prevent, and it would be invisible in Jakarta.
     */
    public function onDate(string $localDate, string $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%s %s:00', $localDate, $this->toString()),
            new DateTimeZone($timezone)
        );
    }

    /**
     * Is `$instant` inside the window [$start, $end) in the given timezone?
     *
     * A window whose end is at or before its start CROSSES MIDNIGHT, and the
     * membership test inverts accordingly. This is the quiet-hours shape:
     * 20.00–08.00 is one window spanning two dates, not two windows, and reading
     * it as `start <= t < end` would make it match nothing at all.
     */
    public static function windowContains(
        self $start,
        self $end,
        DateTimeImmutable $instant,
        string $timezone,
    ): bool {
        $local = $instant->setTimezone(new DateTimeZone($timezone));
        $minutes = ((int) $local->format('G') * 60) + (int) $local->format('i');

        $from = $start->minutesFromMidnight();
        $to = $end->minutesFromMidnight();

        if ($from === $to) {
            // A zero-length window is empty, never "the whole day". Treating it
            // as everything would silence every message in the product.
            return false;
        }

        return $from < $to
            ? ($minutes >= $from && $minutes < $to)
            : ($minutes >= $from || $minutes < $to);
    }
}
