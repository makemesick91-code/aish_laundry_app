<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OutboundMessage;
use App\Modules\Notification\Contracts\ProviderResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * THE OFFICIAL AUTOMATED PATH (FR-094) — AND IT FAILS CLOSED.
 *
 * "Automated, unattended sending shall go through an official WhatsApp Business
 * API provider."
 *
 * WHAT FAIL-CLOSED MEANS HERE, PRECISELY
 * --------------------------------------
 * With any credential missing, `isAvailable()` returns false and `send()` returns
 * `unavailable` WITHOUT making a request. It does not:
 *
 *   - retry against a different endpoint;
 *   - fall back to an unofficial channel;
 *   - fall back to browser or WhatsApp Web automation;
 *   - fabricate an accepted result;
 *   - report a misconfiguration as a network outage.
 *
 * Every one of those is explicitly forbidden. The honest outcome of "no credentials"
 * is "nothing was sent", and the manual deep-link fallback (FR-095) is what a tenant
 * without a provider uses instead — visibly, and never described as automation.
 *
 * NO LIVE SEND HAS OCCURRED IN THIS STEP
 * --------------------------------------
 * Official WhatsApp Business credentials, an approved sender identity, and approved
 * production templates are not available to this repository. This adapter is
 * therefore UNVERIFIED AGAINST A LIVE PROVIDER, and that is stated rather than
 * papered over (Rule 01). What IS verified is its fail-closed behaviour, its error
 * mapping, and the fact that nothing else in the system depends on it — all of
 * which are testable without credentials and are tested.
 *
 * CREDENTIALS COME FROM CONFIGURATION ONLY
 * ----------------------------------------
 * Read from `config('aish.notification.whatsapp.*')`, which reads the environment.
 * No credential is committed, defaulted to a real value, or logged (Rule 03, Rule 45).
 */
final class OfficialWhatsAppBusinessProvider implements NotificationProvider
{
    public const KEY = 'official_whatsapp_business';

    /** Short. A messaging call must never hold a worker for long. */
    private const TIMEOUT_SECONDS = 10;

    public function key(): string
    {
        return self::KEY;
    }

    public function isAvailable(): bool
    {
        $config = $this->credentials();

        // EVERY field, not "some". A partially configured provider is a
        // misconfiguration, and treating it as available would produce a confusing
        // authentication error instead of a clear "not configured".
        foreach (['base_url', 'phone_number_id', 'access_token'] as $required) {
            if (trim((string) ($config[$required] ?? '')) === '') {
                return false;
            }
        }

        return (bool) ($config['enabled'] ?? false);
    }

    public function send(OutboundMessage $message): ProviderResult
    {
        if (! $this->isAvailable()) {
            // Named distinctly from a network failure on purpose: one is a setting a
            // tenant can fix, the other is an outage they cannot.
            return ProviderResult::unavailable(
                'provider_not_configured',
                'Penyedia WhatsApp resmi belum dikonfigurasi untuk tenant ini.',
            );
        }

        $config = $this->credentials();

        try {
            $response = Http::withToken((string) $config['access_token'])
                ->timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->post(
                    rtrim((string) $config['base_url'], '/').'/'.$config['phone_number_id'].'/messages',
                    [
                        'messaging_product' => 'whatsapp',
                        'to' => $message->destination,
                        'type' => 'text',
                        'text' => ['body' => $message->body],
                    ],
                );
        } catch (ConnectionException $e) {
            return ProviderResult::timeout('Tidak dapat menghubungi penyedia pesan.');
        } catch (Throwable $e) {
            // The exception CLASS, never its message: a client exception message can
            // contain the URL, and the URL contains the phone number id.
            return ProviderResult::error('provider_transport_error', $e::class);
        }

        if ($response->serverError()) {
            return ProviderResult::error('provider_server_error', 'Penyedia mengembalikan galat server.');
        }

        if ($response->clientError()) {
            // The provider understood and refused. NOT retryable — repeating it
            // produces the same refusal and burns the tenant's message allowance.
            return ProviderResult::rejected('provider_rejected', 'Penyedia menolak pesan ini.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return ProviderResult::malformed('Respons penyedia tidak dapat dibaca.');
        }

        // The vendor's response shape STOPS HERE. What leaves this method is an
        // opaque reference string and a first-party outcome — nothing downstream
        // ever sees a vendor field name (FR-093, NOT-009).
        $reference = null;
        if (isset($payload['messages'][0]['id']) && is_string($payload['messages'][0]['id'])) {
            $reference = $payload['messages'][0]['id'];
        }

        return ProviderResult::accepted($reference);
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $config = config('aish.notification.whatsapp');

        return is_array($config) ? $config : [];
    }
}
