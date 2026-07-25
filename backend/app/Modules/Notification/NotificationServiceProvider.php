<?php

declare(strict_types=1);

namespace App\Modules\Notification;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Providers\FakeNotificationProvider;
use App\Modules\Notification\Providers\NullNotificationProvider;
use App\Modules\Notification\Providers\OfficialWhatsAppBusinessProvider;
use Illuminate\Support\ServiceProvider;

/**
 * WIRES THE ONE ACTIVE NOTIFICATION PROVIDER (FR-093, FR-094).
 *
 * THE RESOLUTION ORDER, AND WHY IT ENDS WHERE IT DOES
 * ---------------------------------------------------
 *   1. Testing environment      → FakeNotificationProvider (deterministic, sends
 *                                 nothing, never registered anywhere else).
 *   2. Official adapter, IF and ONLY IF it is fully configured and enabled.
 *   3. Otherwise                → NullNotificationProvider.
 *
 * The default is the NULL provider, not the official one. That is the fail-closed
 * choice: an unconfigured system honestly reports "no automated channel" and offers
 * the manual fallback (FR-095), rather than attempting sends that cannot work and
 * reporting misconfiguration as an outage.
 *
 * There is deliberately NO unofficial adapter to fall back to. WhatsApp Web
 * automation, browser automation, and reverse-engineered clients are forbidden
 * outright; the absence of any such class is the enforcement.
 *
 * The binding is a SINGLETON so a test can reach the same Fake instance the
 * dispatcher used and assert on what it was asked to send.
 */
final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationProvider::class, function ($app): NotificationProvider {
            if ($app->environment('testing')) {
                return new FakeNotificationProvider();
            }

            $official = new OfficialWhatsAppBusinessProvider();

            return $official->isAvailable() ? $official : new NullNotificationProvider();
        });
    }
}
