<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use InvalidArgumentException;

/**
 * AN OUTLET'S WEEKLY OPENING PATTERN, IN OUTLET LOCAL TIME (FR-041).
 *
 * Stored as a `jsonb` object keyed by weekday — never an array. A positional
 * array would make "Wednesday" an index, and a single off-by-one would move an
 * outlet's opening hours to the wrong day without changing a single value.
 *
 * Shape:
 *
 *   {
 *     "monday": {"is_open": true,  "opens_at": "08:00", "closes_at": "20:00"},
 *     "sunday": {"is_open": false}
 *   }
 *
 * A CLOSED DAY IS EXPLICIT, NOT ABSENT. `{"is_open": false}` says the outlet is
 * shut on Sunday; a missing key says nobody has configured Sunday yet. Those are
 * different facts and the UI has to be able to tell them apart (Rule 29 — an
 * empty state states what would appear here and why).
 */
final class OperatingHours
{
    /** @var list<string> */
    public const WEEKDAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /** @param array<string, array{is_open: bool, opens_at?: string, closes_at?: string}> $days */
    private function __construct(private readonly array $days)
    {
    }

    /**
     * Validate and normalise a client-supplied weekly pattern.
     *
     * @param  array<mixed>  $input
     *
     * @throws InvalidArgumentException with a message naming the offending day
     */
    public static function fromArray(array $input): self
    {
        $days = [];

        foreach ($input as $day => $entry) {
            if (! in_array($day, self::WEEKDAYS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Hari "%s" tidak dikenal. Gunakan salah satu dari: %s.',
                    is_string($day) ? $day : gettype($day),
                    implode(', ', self::WEEKDAYS)
                ));
            }

            if (! is_array($entry) || ! array_key_exists('is_open', $entry) || ! is_bool($entry['is_open'])) {
                throw new InvalidArgumentException(sprintf(
                    'Jam operasional hari "%s" harus menyatakan is_open secara eksplisit.',
                    $day
                ));
            }

            if ($entry['is_open'] === false) {
                // A closed day carries no times. Keeping stale times on a closed
                // day is how a reopened outlet inherits last year's hours.
                $days[$day] = ['is_open' => false];

                continue;
            }

            foreach (['opens_at', 'closes_at'] as $field) {
                if (! isset($entry[$field]) || ! is_string($entry[$field])) {
                    throw new InvalidArgumentException(sprintf(
                        'Hari "%s" dinyatakan buka, sehingga %s wajib diisi.',
                        $day,
                        $field
                    ));
                }
            }

            $opens = WallClockTime::parse($entry['opens_at']);
            $closes = WallClockTime::parse($entry['closes_at']);

            // Equal times are rejected: a zero-length opening window means the
            // outlet is shut, and `is_open: false` already says that honestly.
            if ($opens->minutesFromMidnight() === $closes->minutesFromMidnight()) {
                throw new InvalidArgumentException(sprintf(
                    'Hari "%s" memiliki jam buka dan tutup yang sama. Gunakan '
                    .'is_open: false bila outlet tidak beroperasi.',
                    $day
                ));
            }

            $days[$day] = [
                'is_open' => true,
                'opens_at' => $opens->toString(),
                'closes_at' => $closes->toString(),
            ];
        }

        return new self($days);
    }

    /** @return array<string, array{is_open: bool, opens_at?: string, closes_at?: string}> */
    public function toArray(): array
    {
        // Emitted in canonical weekday order regardless of input order, so two
        // equivalent patterns serialise identically.
        $ordered = [];

        foreach (self::WEEKDAYS as $day) {
            if (isset($this->days[$day])) {
                $ordered[$day] = $this->days[$day];
            }
        }

        return $ordered;
    }

    /**
     * Is the outlet open at this local wall-clock time on this weekday?
     *
     * Returns false for an unconfigured day. An outlet whose hours nobody has
     * set is not open by default — defaulting to open would have the product
     * asserting availability the tenant never stated.
     */
    public function isOpenAt(string $weekday, WallClockTime $time): bool
    {
        $entry = $this->days[$weekday] ?? null;

        if ($entry === null || $entry['is_open'] === false) {
            return false;
        }

        $opens = WallClockTime::parse($entry['opens_at']);
        $closes = WallClockTime::parse($entry['closes_at']);
        $minutes = $time->minutesFromMidnight();

        // A closing time at or before the opening time crosses midnight — a
        // laundry open 22.00–02.00 is ordinary, not a data error.
        return $opens->minutesFromMidnight() < $closes->minutesFromMidnight()
            ? ($minutes >= $opens->minutesFromMidnight() && $minutes < $closes->minutesFromMidnight())
            : ($minutes >= $opens->minutesFromMidnight() || $minutes < $closes->minutesFromMidnight());
    }
}
