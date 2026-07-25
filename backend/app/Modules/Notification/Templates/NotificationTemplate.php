<?php

declare(strict_types=1);

namespace App\Modules\Notification\Templates;

use App\Modules\Notification\Models\NotificationIntent;
use InvalidArgumentException;

/**
 * THE CANONICAL TEMPLATE CATALOGUE — and the single source of message CATEGORY.
 *
 * WHY CATEGORY LIVES HERE AND NOWHERE ELSE
 * ----------------------------------------
 * NOT-024 and FR-096: "a marketing message shall never be routed through a
 * transactional path." If a caller could pass a category string, that sentence
 * would be a convention someone must honour. Because the category is a property of
 * the TEMPLATE, and `NotificationIntentService` reads it from here rather than from
 * its arguments, relabelling is not something a caller can do at all — the attempt
 * is rejected before an intent exists.
 *
 * Transactional templates concern the customer's own order and require no marketing
 * consent (NOTIFICATION_DOMAIN §6). Marketing templates require an explicitly
 * granted consent, evaluated at SEND time.
 *
 * THE ACCOUNT-TAKEOVER RULE, ENFORCED STRUCTURALLY
 * ------------------------------------------------
 * TRK-029 / NOT-014: a message never carries an OTP value and a tracking link
 * together — that combination is one-message account takeover. Each template
 * declares `carries_otp` and `carries_tracking_link`, and `assertContentSafety()`
 * refuses any template where both are true. `OtpAndLinkNeverCombinedTest` asserts
 * it across the whole catalogue, so adding an unsafe template fails the build
 * rather than shipping.
 *
 * BODIES ARE BAHASA INDONESIA (Rule 30) and every example value is fictional
 * (Rule 23). No body contains a full address (NOT-015), a full phone, an internal
 * note, an internal identifier, or a price the customer has not already been told.
 */
final class NotificationTemplate
{
    // --- Transactional: the customer's own order (Master Source §14.2). ---
    public const ORDER_RECEIVED = 'order_received';

    public const ORDER_IN_PRODUCTION = 'order_in_production';

    public const ORDER_READY_FOR_PICKUP = 'order_ready_for_pickup';

    public const PAYMENT_RECEIVED = 'payment_received';

    /** The FR-091 OTP carrier. Deliberately carries NO tracking link. */
    public const TRACKING_OTP = 'tracking_otp';

    // --- Marketing: requires explicit granted consent. ---
    public const MARKETING_PROMOTION = 'marketing_promotion';

    /**
     * @var array<string, array{category: string, version: int, carries_otp: bool,
     *      carries_tracking_link: bool, body: string}>
     */
    private const CATALOGUE = [
        self::ORDER_RECEIVED => [
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'version' => 1,
            'carries_otp' => false,
            'carries_tracking_link' => true,
            'body' => "Halo :customer_name, pesanan :order_number di :outlet_name sudah kami terima. "
                ."Pantau status cucian Anda di :tracking_url",
        ],
        self::ORDER_IN_PRODUCTION => [
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'version' => 1,
            'carries_otp' => false,
            'carries_tracking_link' => true,
            'body' => "Halo :customer_name, pesanan :order_number sedang dikerjakan. "
                ."Pantau status cucian Anda di :tracking_url",
        ],
        self::ORDER_READY_FOR_PICKUP => [
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'version' => 1,
            'carries_otp' => false,
            'carries_tracking_link' => true,
            'body' => "Halo :customer_name, pesanan :order_number sudah siap diambil di :outlet_name. "
                ."Rincian pesanan: :tracking_url",
        ],
        self::PAYMENT_RECEIVED => [
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'version' => 1,
            'carries_otp' => false,
            'carries_tracking_link' => true,
            'body' => "Halo :customer_name, pembayaran untuk pesanan :order_number sudah kami catat. "
                ."Rincian pesanan: :tracking_url",
        ],
        self::TRACKING_OTP => [
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'version' => 1,
            'carries_otp' => true,
            // FALSE, and this is the whole point. A message carrying an OTP never
            // also carries the link that OTP unlocks (TRK-029, NOT-014).
            'carries_tracking_link' => false,
            'body' => "Kode verifikasi Anda adalah :otp_code. Berlaku 5 menit. "
                ."Jangan bagikan kode ini kepada siapa pun, termasuk yang mengaku petugas laundry.",
        ],
        self::MARKETING_PROMOTION => [
            'category' => NotificationIntent::CATEGORY_MARKETING,
            'version' => 1,
            'carries_otp' => false,
            'carries_tracking_link' => false,
            'body' => "Halo :customer_name, ada penawaran dari :outlet_name. "
                ."Balas BERHENTI untuk tidak menerima pesan promosi lagi.",
        ],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::CATALOGUE[$key]);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }

    /**
     * THE ONLY source of a message's category (FR-096, NOT-024).
     *
     * @throws InvalidArgumentException on an unknown template — an intent for a
     *         template that does not exist is refused rather than defaulted, because
     *         defaulting would mean guessing whether it needs consent.
     */
    public static function categoryFor(string $key): string
    {
        self::assertKnown($key);

        return self::CATALOGUE[$key]['category'];
    }

    public static function versionFor(string $key): int
    {
        self::assertKnown($key);

        return self::CATALOGUE[$key]['version'];
    }

    public static function isMarketing(string $key): bool
    {
        return self::categoryFor($key) === NotificationIntent::CATEGORY_MARKETING;
    }

    public static function carriesOtp(string $key): bool
    {
        self::assertKnown($key);

        return self::CATALOGUE[$key]['carries_otp'];
    }

    public static function carriesTrackingLink(string $key): bool
    {
        self::assertKnown($key);

        return self::CATALOGUE[$key]['carries_tracking_link'];
    }

    /**
     * Render a body from fictional-safe, already-redacted variables.
     *
     * Any placeholder the caller does not supply is replaced with an empty string
     * rather than left in the text: shipping a literal `:customer_name` to a
     * customer is a defect, and leaving an unresolved placeholder that happens to
     * look like a value is worse.
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $key, array $variables): string
    {
        self::assertKnown($key);
        self::assertContentSafety($key);

        $body = self::CATALOGUE[$key]['body'];

        // Longest key first, so `:order_number` is never partially replaced by a
        // shorter `:order` placeholder that a future template might add.
        $keys = array_keys($variables);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($keys as $name) {
            $body = str_replace(':'.$name, $variables[$name], $body);
        }

        return trim(preg_replace('/:[a-z_]+/', '', $body) ?? $body);
    }

    /**
     * Refuse a template that would combine an OTP with a tracking link.
     *
     * Called on every render, not merely asserted in a test, so the guarantee holds
     * at runtime for a template added after the test was written.
     */
    public static function assertContentSafety(string $key): void
    {
        if (self::carriesOtp($key) && self::carriesTrackingLink($key)) {
            throw new InvalidArgumentException(sprintf(
                'Template "%s" would carry an OTP value and a tracking link in one message. '
                .'That combination is one-message account takeover and is forbidden '
                .'(TRK-029, NOT-014, Master Source §14.3).',
                $key
            ));
        }
    }

    private static function assertKnown(string $key): void
    {
        if (! isset(self::CATALOGUE[$key])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown notification template "%s". Templates are a platform-managed '
                .'catalogue; an intent whose category cannot be determined is refused '
                .'rather than defaulted (FR-096).',
                $key
            ));
        }
    }
}
