<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OutboundMessage;
use App\Modules\Notification\Contracts\ProviderResult;

/**
 * THE DEFAULT ADAPTER: no automated channel is configured.
 *
 * This is what a tenant has before they contract an official WhatsApp Business
 * provider, and it is what this repository has today. It exists so that "no
 * provider" is a first-class, honest state rather than a null reference or a
 * silently swallowed send.
 *
 * IT NEVER CLAIMS A SEND. `send()` returns `unavailable`, the intent records
 * `provider_unavailable`, the operator UI shows the provider as not configured, and
 * the manual deep-link fallback (FR-095) is offered. Nothing anywhere says a
 * message went out, because none did (Rule 01).
 *
 * This adapter is also why the notification subsystem is fully testable without
 * credentials: every policy — consent, category, quiet hours, dedup, bounded retry,
 * order-state decoupling — is exercised against a provider that is honestly absent.
 */
final class NullNotificationProvider implements NotificationProvider
{
    public const KEY = 'null_provider';

    public function key(): string
    {
        return self::KEY;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function send(OutboundMessage $message): ProviderResult
    {
        return ProviderResult::unavailable(
            'provider_unavailable',
            'Tidak ada penyedia pesan otomatis yang aktif. Gunakan tautan WhatsApp manual.',
        );
    }
}
