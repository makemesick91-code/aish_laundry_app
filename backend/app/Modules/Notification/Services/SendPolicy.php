<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Templates\NotificationTemplate;

/**
 * MAY THIS MESSAGE BE SENT AT ALL? (FR-096, NOT-005, NOT-011, NOT-024)
 *
 * One place, so consent cannot be honoured on one code path and forgotten on
 * another. Evaluated at SEND time, not at queue time and not at campaign-build
 * time — a customer who opts out after a campaign is built has still opted out
 * (NOT-005).
 *
 * ABSENCE OF A REFUSAL IS NOT CONSENT (NOT-011)
 * ---------------------------------------------
 * The default for marketing with no consent row is BLOCKED, not allowed. This is
 * the difference between a consent model and a consent-shaped decoration: a
 * customer who has never been asked has not agreed.
 *
 * CONSENT IS READ FROM STEP 4's APPEND-ONLY TABLE
 * -----------------------------------------------
 * `customer_consents` (FR-027/FR-028) is the one source. Step 7 introduces no
 * second consent store — a second store would eventually disagree with the first,
 * and the disagreement would be discovered by sending a message to someone who had
 * opted out. Granting appends a row, withdrawing appends another, and the current
 * state is the latest row by `recorded_at`.
 *
 * TRANSACTIONAL IS NOT EXEMPT FROM EVERYTHING
 * -------------------------------------------
 * A transactional message about the customer's own order does not require marketing
 * consent (NOTIFICATION_DOMAIN §6) — but it still requires a reachable destination,
 * and it is still subject to quiet hours, which are evaluated separately in
 * `QuietHours`. "Transactional" is not a bypass keyword.
 */
final class SendPolicy
{
    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public static function evaluate(string $templateKey, ?Customer $customer, string $destination): array
    {
        if (trim($destination) === '') {
            return ['allowed' => false, 'reason' => NotificationIntent::SUPPRESSED_NO_DESTINATION];
        }

        if (! NotificationTemplate::isMarketing($templateKey)) {
            // Transactional: the customer's own order. No marketing consent needed.
            return ['allowed' => true, 'reason' => null];
        }

        if ($customer === null) {
            // A marketing message with no identifiable customer cannot have consent,
            // by definition. Blocked, not defaulted.
            return ['allowed' => false, 'reason' => NotificationIntent::SUPPRESSED_MARKETING_NO_CONSENT];
        }

        $state = self::currentMarketingConsent($customer);

        return match ($state) {
            CustomerConsent::STATE_GRANTED => ['allowed' => true, 'reason' => null],
            CustomerConsent::STATE_WITHDRAWN => [
                'allowed' => false,
                'reason' => NotificationIntent::SUPPRESSED_MARKETING_OPTED_OUT,
            ],
            // No row at all. Never asked, never agreed.
            default => [
                'allowed' => false,
                'reason' => NotificationIntent::SUPPRESSED_MARKETING_NO_CONSENT,
            ],
        };
    }

    /**
     * The CURRENT marketing-WhatsApp consent state, or null when never recorded.
     *
     * The latest row wins, ordered by `recorded_at` then `id`. The tiebreak on `id`
     * matters: a grant and a withdrawal recorded in the same second would otherwise
     * be ordered arbitrarily, and "arbitrarily" here means sometimes messaging
     * someone who opted out.
     *
     * Scoped to the customer's own tenant. A customer opted out with one tenant has
     * said nothing about another, because the two profiles are unrelated (TEN-011).
     */
    public static function currentMarketingConsent(Customer $customer): ?string
    {
        $latest = CustomerConsent::query()
            ->forTenant($customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where('consent_type', CustomerConsent::TYPE_MARKETING_WHATSAPP)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        return $latest?->state;
    }
}
