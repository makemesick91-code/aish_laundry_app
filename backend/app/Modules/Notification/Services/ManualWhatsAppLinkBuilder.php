<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Notification\Models\NotificationAttempt;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tracking\Support\PublicMask;
use Illuminate\Support\Str;

/**
 * THE MANUAL DEEP-LINK FALLBACK (FR-095) — AND IT IS NEVER AUTOMATION.
 *
 * "A prepared deep link that a staff member sends manually shall be available as an
 * explicit, visible fallback, and shall never be presented or sold as automation."
 *
 * That last clause is a product-honesty requirement, and it is enforced in the data
 * model rather than in copy review. Preparing a link moves the intent to
 * `MANUAL_FALLBACK_PREPARED` — a state whose name contains the word "prepared" and
 * which the database CHECK excludes from carrying `accepted_at`. There is no code
 * path from here to `SENT`. The operator UI says a staff member must send it, and
 * the API returns the same word.
 *
 * THE FALLBACK IS NOT A BYPASS
 * ----------------------------
 * Category, consent, and opt-out are enforced here exactly as they are for an
 * automated send. A fallback that let a tenant hand-send marketing to someone who
 * opted out would make opt-out theatre — the customer's answer does not depend on
 * which mechanism the tenant used to ignore it (NOT-005).
 *
 * Quiet hours are NOT enforced on link PREPARATION, and that distinction is
 * deliberate: preparing a link does not message anybody. What the operator does with
 * it is a human act at a human's discretion, and the product does not pretend to
 * control a staff member's phone. The UI states the quiet-hours status so the
 * decision is informed.
 *
 * WHAT THE LINK MAY CARRY
 * -----------------------
 * A masked customer name, the public order number, the outlet name. Never a full
 * address (NOT-015), never a credential, never a raw token hash, never an internal
 * identifier, never an internal note.
 */
class ManualWhatsAppLinkBuilder
{
    private const BASE = 'https://wa.me/';

    /**
     * Prepare a manual link for an intent, recording the preparation.
     *
     * @return array{url: string, state: string, prepared_at: string}
     */
    public function prepare(NotificationIntent $intent): array
    {
        $customer = $intent->customer_id === null
            ? null
            : Customer::query()->forTenant($intent->tenant_id)->find($intent->customer_id);

        // The same policy an automated send would face. No bypass.
        $policy = SendPolicy::evaluate(
            (string) $intent->template_key,
            $customer,
            (string) $intent->recipient_normalized,
        );

        if (! $policy['allowed']) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Pesan ini tidak dapat disiapkan: penerima menolak atau belum menyetujui pesan kategori ini.',
                ['suppression_reason' => [$policy['reason']]],
            );
        }

        $order = $intent->order_id === null
            ? null
            : Order::query()->forTenant($intent->tenant_id)->find($intent->order_id);
        $outlet = Outlet::query()->forTenant($intent->tenant_id)->find($intent->outlet_id);

        if ($order === null || $outlet === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
        }

        $body = NotificationTemplate::render((string) $intent->template_key, [
            'customer_name' => PublicMask::name($customer?->name),
            'order_number' => (string) $order->order_number,
            'outlet_name' => (string) $outlet->name,
        ]);

        $url = self::build((string) $intent->recipient_normalized, $body);

        $intent->forceFill([
            // "PREPARED". Not sent, not delivered, and `accepted_at` stays null —
            // the database CHECK would refuse it otherwise.
            'state' => NotificationIntent::STATE_MANUAL_FALLBACK_PREPARED,
        ])->save();

        NotificationAttempt::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $intent->tenant_id,
            'intent_id' => $intent->id,
            'attempt_number' => max((int) $intent->attempt_count, 1),
            'provider_key' => 'manual_whatsapp_link',
            'outcome' => 'manual_link_prepared',
            'failure_code' => null,
            'detail' => 'Tautan WhatsApp manual disiapkan untuk dikirim oleh staf.',
            'occurred_at' => now(),
        ]);

        return [
            'url' => $url,
            'state' => NotificationIntent::STATE_MANUAL_FALLBACK_PREPARED,
            'prepared_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Build a `wa.me` deep link.
     *
     * The destination is reduced to digits — `wa.me` accepts no `+`, no spaces, no
     * punctuation — and the body is URL-encoded with `rawurlencode` so a newline or
     * an ampersand in a template cannot terminate the query parameter and truncate
     * the message.
     */
    public static function build(string $destination, string $body): string
    {
        $digits = preg_replace('/\D+/', '', $destination) ?? '';

        return self::BASE.$digits.'?text='.rawurlencode($body);
    }
}
