<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http;

use App\Modules\Notification\Contracts\MessageSecurityClassification;
use App\Modules\Notification\Models\NotificationAttempt;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Tracking\Support\PublicMask;

/**
 * THE OPERATOR-FACING VIEW OF A NOTIFICATION.
 *
 * TWO HONESTY RULES ARE ENCODED HERE
 * ----------------------------------
 * 1. The recipient is MASKED, even for staff. An operator needs to confirm which
 *    customer a message went to, which the last four digits answer; a full phone
 *    number on a message-history screen is a personal datum on display all day on
 *    a counter terminal (Rule 32 hard rule 4).
 *
 * 2. `state_label` never says "terkirim ke pelanggan". `SENT` means the provider
 *    ACCEPTED the message and the label says exactly that — "diterima penyedia". We
 *    hold no delivery receipt, and a UI that claimed delivery would be making a
 *    claim the system cannot support (Rule 01).
 *
 * The dedup key, the provider access token, and the rendered body are all absent.
 * The first is an internal digest, the second is a credential, and the third is a
 * copy of personal data that does not need a second home.
 */
final class NotificationProjection
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(NotificationIntent $intent): array
    {
        return [
            'id' => $intent->id,
            'order_id' => $intent->order_id,
            'event_type' => $intent->event_type,
            'template_key' => $intent->template_key,
            'category' => $intent->category,
            'channel' => $intent->channel,
            'recipient_masked' => PublicMask::phone((string) $intent->recipient_normalized),
            'state' => $intent->state,
            'state_label' => self::stateLabel((string) $intent->state),
            'suppression_reason' => $intent->suppression_reason,
            'suppression_label' => self::suppressionLabel($intent->suppression_reason),
            'scheduled_for' => $intent->scheduled_for?->toIso8601String(),
            'deferred_for_quiet_hours' => (bool) $intent->deferred_for_quiet_hours,
            // DEC-0040. Surfaced so an operator looking at a message sent at 02.00
            // can see WHY it was not held until 08.00, rather than having to take
            // the exemption on trust. Null for every ordinary message.
            'security_classification' => $intent->security_classification,
            'security_classification_label' => self::securityClassificationLabel($intent->security_classification),
            'attempt_count' => (int) $intent->attempt_count,
            'max_attempts' => NotificationIntent::MAX_ATTEMPTS,
            'last_attempted_at' => $intent->last_attempted_at?->toIso8601String(),
            'accepted_at' => $intent->accepted_at?->toIso8601String(),
            'provider_key' => $intent->provider_key,
            'failure_code' => $intent->failure_code,
            'can_retry' => $intent->canRetry(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function attempts(NotificationIntent $intent): array
    {
        return NotificationAttempt::query()
            ->forTenant($intent->tenant_id)
            ->where('intent_id', $intent->id)
            ->orderBy('occurred_at')
            ->get()
            ->map(static fn (NotificationAttempt $a): array => [
                'attempt_number' => (int) $a->attempt_number,
                'provider_key' => $a->provider_key,
                'outcome' => $a->outcome,
                'failure_code' => $a->failure_code,
                'detail' => $a->detail,
                'occurred_at' => $a->occurred_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Bahasa Indonesia labels (Rule 30). Every one states what happened AND, where
     * there is one, what to do next (Rule 29 hard rule 9).
     */
    public static function stateLabel(string $state): string
    {
        return match ($state) {
            NotificationIntent::STATE_PENDING => 'Menunggu dikirim',
            NotificationIntent::STATE_DEFERRED => 'Ditunda sampai di luar jam tenang',
            NotificationIntent::STATE_SENDING => 'Sedang dikirim',
            // NOT "terkirim ke pelanggan". The provider accepted it; we have no
            // delivery receipt and do not claim one.
            NotificationIntent::STATE_SENT => 'Diterima penyedia pesan',
            NotificationIntent::STATE_FAILED_RETRYABLE => 'Gagal — akan dicoba lagi otomatis',
            NotificationIntent::STATE_FAILED_PERMANENT => 'Gagal permanen — kirim manual lewat WhatsApp',
            NotificationIntent::STATE_SUPPRESSED => 'Tidak dikirim',
            NotificationIntent::STATE_MANUAL_FALLBACK_PREPARED => 'Tautan manual disiapkan — staf perlu mengirimnya',
            default => 'Status tidak dikenal',
        };
    }

    public static function suppressionLabel(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        return match ($reason) {
            NotificationIntent::SUPPRESSED_MARKETING_NO_CONSENT => 'Pelanggan belum menyetujui pesan promosi',
            NotificationIntent::SUPPRESSED_MARKETING_OPTED_OUT => 'Pelanggan menolak menerima pesan promosi',
            NotificationIntent::SUPPRESSED_NO_DESTINATION => 'Nomor tujuan pelanggan belum tercatat',
            NotificationIntent::SUPPRESSED_DUPLICATE => 'Pesan serupa sudah dikirim untuk peristiwa ini',
            NotificationIntent::SUPPRESSED_OTP_NOT_CUSTOMER_INITIATED => 'Kode verifikasi tidak dikirim karena tidak diminta pelanggan',
            NotificationIntent::SUPPRESSED_OTP_TEMPLATE_NOT_DISPATCHABLE => 'Kode verifikasi hanya dikirim atas permintaan pelanggan, bukan dari antrean',
            default => 'Tidak dikirim karena kebijakan pengiriman',
        };
    }

    /**
     * The DEC-0040 classification, in Bahasa Indonesia (Rule 30).
     *
     * Stated positively — it explains why a message was permitted at an unusual
     * hour. A label that merely said "dikecualikan" would leave an operator to guess
     * what was excepted and on whose authority.
     */
    public static function securityClassificationLabel(?string $classification): ?string
    {
        if ($classification === null) {
            return null;
        }

        return match ($classification) {
            MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION => 'Transaksi keamanan atas permintaan pelanggan — tidak ditunda oleh jam tenang',
            default => 'Klasifikasi keamanan tidak dikenal',
        };
    }
}
