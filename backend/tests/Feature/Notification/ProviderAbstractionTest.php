<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OutboundMessage;
use App\Modules\Notification\Providers\FakeNotificationProvider;
use App\Modules\Notification\Providers\NullNotificationProvider;
use App\Modules\Notification\Providers\OfficialWhatsAppBusinessProvider;
use App\Modules\Notification\Services\ManualWhatsAppLinkBuilder;
use App\Modules\Notification\Templates\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * STEP 7 · UNIT G — THE PROVIDER ABSTRACTION (FR-093, FR-094, FR-095).
 *
 * The structural assertions here are what keep FR-093 true over time. "No vendor
 * SDK, payload, or identifier leaks into business logic" is a property of the whole
 * module rather than of any one class, so it is checked by reading the source tree:
 * a future contributor importing a vendor client into a service fails this test
 * rather than passing review.
 */
final class ProviderAbstractionTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function phpFilesUnder(string $relative): array
    {
        $root = app_path($relative);
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function message(string $body = 'Pesan uji fiktif.'): OutboundMessage
    {
        return new OutboundMessage(
            destination: '6281200000000',
            body: $body,
            templateKey: NotificationTemplate::ORDER_RECEIVED,
            category: 'transactional',
            correlationId: 'uji-korelasi',
        );
    }

    // =====================================================================
    // FR-093 — no vendor leakage
    // =====================================================================

    public function test_no_notification_file_outside_providers_names_a_vendor_or_an_http_client(): void
    {
        // A vendor name or an HTTP call anywhere but an adapter means the
        // abstraction has already been bypassed.
        $needles = ['whatsapp.com', 'graph.facebook', 'twilio', 'Http::', 'GuzzleHttp'];

        foreach ($this->phpFilesUnder('Modules/Notification') as $path) {
            if (str_contains($path, DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR)) {
                continue; // adapters are exactly where vendor detail belongs
            }

            $source = (string) file_get_contents($path);

            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, $source,
                    basename($path).' references a vendor or an HTTP client outside an adapter. '
                    .'Swapping providers must be an adapter + configuration change, never a '
                    .'product rewrite (FR-093, NOT-009).');
            }
        }
    }

    public function test_no_module_outside_notification_depends_on_a_provider_class(): void
    {
        foreach (['Modules/Tracking', 'Modules/Ordering', 'Modules/Payments', 'Modules/Production'] as $module) {
            foreach ($this->phpFilesUnder($module) as $path) {
                $source = (string) file_get_contents($path);

                $this->assertStringNotContainsString('Notification\\Providers\\', $source,
                    basename($path).' reaches into a notification ADAPTER. Business logic talks '
                    .'to the interface, never to a vendor implementation (FR-093).');
            }
        }
    }

    public function test_the_interface_exchanges_only_first_party_types(): void
    {
        $reflection = new \ReflectionMethod(NotificationProvider::class, 'send');

        $this->assertSame(
            OutboundMessage::class,
            (string) $reflection->getParameters()[0]->getType()
        );
        $this->assertSame(
            \App\Modules\Notification\Contracts\ProviderResult::class,
            (string) $reflection->getReturnType()
        );
    }

    public function test_the_outbound_message_carries_no_internal_identifier(): void
    {
        $properties = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(OutboundMessage::class))->getProperties()
        );
        sort($properties);

        // No customer id, no order id, no tenant id, no token. An adapter that
        // received the customer could log the customer.
        $this->assertSame(
            ['body', 'category', 'correlationId', 'destination', 'templateKey'],
            $properties
        );
    }

    // =====================================================================
    // FR-094 — the official adapter fails closed
    // =====================================================================

    public function test_the_official_adapter_is_unavailable_without_credentials(): void
    {
        config()->set('aish.notification.whatsapp', [
            'enabled' => true,
            'base_url' => '',
            'phone_number_id' => '',
            'access_token' => '',
        ]);

        $this->assertFalse((new OfficialWhatsAppBusinessProvider())->isAvailable());
    }

    public function test_a_partially_configured_official_adapter_is_still_unavailable(): void
    {
        // Treating a half-configured provider as available would produce a
        // confusing authentication error instead of a clear "not configured".
        config()->set('aish.notification.whatsapp', [
            'enabled' => true,
            'base_url' => 'https://contoh.invalid/v1',
            'phone_number_id' => '',
            'access_token' => 'placeholder-bukan-kredensial-asli',
        ]);

        $this->assertFalse((new OfficialWhatsAppBusinessProvider())->isAvailable());
    }

    public function test_configured_but_not_enabled_is_still_unavailable(): void
    {
        // Configuring credentials is not the same act as authorising live sends to
        // real customers, so the two are separate switches.
        config()->set('aish.notification.whatsapp', [
            'enabled' => false,
            'base_url' => 'https://contoh.invalid/v1',
            'phone_number_id' => '000000000000000',
            'access_token' => 'placeholder-bukan-kredensial-asli',
        ]);

        $this->assertFalse((new OfficialWhatsAppBusinessProvider())->isAvailable());
    }

    public function test_an_unconfigured_official_adapter_makes_no_request_and_fabricates_nothing(): void
    {
        config()->set('aish.notification.whatsapp', ['enabled' => false]);

        $result = (new OfficialWhatsAppBusinessProvider())->send($this->message());

        $this->assertFalse($result->isAccepted());
        $this->assertSame('unavailable', $result->outcome);
        $this->assertSame('provider_not_configured', $result->failureCode,
            'A misconfiguration must be named as one, not reported as a network outage.');
    }

    public function test_there_is_no_unofficial_or_browser_automation_adapter(): void
    {
        $adapters = array_map('basename', $this->phpFilesUnder('Modules/Notification/Providers'));
        sort($adapters);

        // WhatsApp Web automation, browser automation, and reverse-engineered
        // clients are forbidden outright. The absence of any such class IS the
        // enforcement — adding an adapter file fails this test.
        $this->assertSame([
            'FakeNotificationProvider.php',
            'NullNotificationProvider.php',
            'OfficialWhatsAppBusinessProvider.php',
        ], $adapters);

        foreach ($this->phpFilesUnder('Modules/Notification/Providers') as $path) {
            $source = (string) file_get_contents($path);
            foreach (['puppeteer', 'selenium', 'webdriver', 'web.whatsapp', 'chromedriver'] as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $source);
            }
        }
    }

    public function test_the_default_resolution_is_the_null_provider_when_unconfigured(): void
    {
        config()->set('aish.notification.whatsapp', ['enabled' => false]);

        // Resolve the way NotificationServiceProvider does for a non-testing env.
        $official = new OfficialWhatsAppBusinessProvider();
        $resolved = $official->isAvailable() ? $official : new NullNotificationProvider();

        $this->assertInstanceOf(NullNotificationProvider::class, $resolved,
            'An unconfigured system reports "no automated channel" honestly rather '
            .'than attempting sends that cannot succeed.');
    }

    public function test_the_fake_provider_is_bound_here_and_carries_no_transport(): void
    {
        $this->assertInstanceOf(FakeNotificationProvider::class, app(NotificationProvider::class),
            'The testing environment must resolve the fake provider, never a real one.');

        $source = (string) file_get_contents(
            app_path('Modules/Notification/Providers/FakeNotificationProvider.php')
        );

        // It contains no transport at all, so binding it anywhere could never
        // actually message anybody.
        $this->assertStringNotContainsString('Http::', $source);
        $this->assertStringNotContainsString('curl', $source);
    }

    public function test_the_null_provider_never_claims_a_send(): void
    {
        $result = (new NullNotificationProvider())->send($this->message());

        $this->assertFalse($result->isAccepted());
        $this->assertFalse((new NullNotificationProvider())->isAvailable());
    }

    // =====================================================================
    // FR-095 — the manual deep link
    // =====================================================================

    public function test_the_manual_link_is_a_wa_me_url_with_an_encoded_body(): void
    {
        $url = ManualWhatsAppLinkBuilder::build(
            '+62 812-0000-0000',
            "Halo Budi F.\nPesanan ALS-2026-000042 siap diambil."
        );

        $this->assertStringStartsWith('https://wa.me/6281200000000?text=', $url);

        // A newline or an ampersand in a template must not terminate the query
        // parameter and truncate the message.
        $this->assertStringNotContainsString("\n", $url);
        $this->assertStringContainsString('%0A', $url);
    }

    public function test_the_manual_link_carries_no_credential_token_or_address(): void
    {
        $body = NotificationTemplate::render(NotificationTemplate::ORDER_READY_FOR_PICKUP, [
            'customer_name' => 'Budi F.',
            'order_number' => 'ALS-2026-000042',
            'outlet_name' => 'Outlet Fiktif',
        ]);

        $decoded = urldecode(ManualWhatsAppLinkBuilder::build('6281200000000', $body));

        foreach (['Jalan', 'access_token', 'Bearer', 'token_hash'] as $needle) {
            $this->assertStringNotContainsString($needle, $decoded);
        }
    }

    // =====================================================================
    // Content rules (NOT-014, TRK-029, NOT-015, NOT-008)
    // =====================================================================

    public function test_no_template_combines_an_otp_with_a_tracking_link(): void
    {
        foreach (NotificationTemplate::keys() as $key) {
            $this->assertFalse(
                NotificationTemplate::carriesOtp($key) && NotificationTemplate::carriesTrackingLink($key),
                "Template {$key} would carry an OTP value and a tracking link in one message. "
                .'That is one-message account takeover (TRK-029, NOT-014, Master Source §14.3).'
            );

            // The runtime guard agrees with the declaration.
            NotificationTemplate::assertContentSafety($key);
        }
    }

    public function test_the_otp_template_carries_no_link_at_all(): void
    {
        $body = NotificationTemplate::render(NotificationTemplate::TRACKING_OTP, [
            'otp_code' => '123456',
            'customer_name' => 'Budi F.',
        ]);

        $this->assertStringContainsString('123456', $body);
        $this->assertStringNotContainsString('http', $body);
        $this->assertStringNotContainsString('lacak', $body);
    }

    public function test_no_template_exposes_an_address_or_a_phone_placeholder(): void
    {
        foreach (NotificationTemplate::keys() as $key) {
            $body = NotificationTemplate::render($key, [
                'customer_name' => 'Budi F.',
                'order_number' => 'ALS-2026-000042',
                'outlet_name' => 'Outlet Fiktif',
                'otp_code' => '123456',
                'tracking_url' => 'https://contoh.invalid/lacak/abc',
            ]);

            // NOT-015 — a message never contains a full address.
            foreach ([':address', ':alamat', ':phone', ':customer_phone'] as $needle) {
                $this->assertStringNotContainsString($needle, $body);
            }
        }
    }

    public function test_rendering_leaves_no_unresolved_placeholder(): void
    {
        // Shipping a literal `:customer_name` to a customer is a defect; leaving
        // one that happens to look like a value is worse.
        $body = NotificationTemplate::render(NotificationTemplate::ORDER_RECEIVED, [
            'customer_name' => 'Budi F.',
        ]);

        $this->assertDoesNotMatchRegularExpression('/:[a-z_]{3,}/', $body);
    }

    public function test_every_template_body_is_in_bahasa_indonesia(): void
    {
        foreach (NotificationTemplate::keys() as $key) {
            $body = NotificationTemplate::render($key, [
                'customer_name' => 'Budi F.',
                'order_number' => 'ALS-2026-000042',
                'outlet_name' => 'Outlet Fiktif',
                'otp_code' => '123456',
            ]);

            // Rule 30: English is permitted only for technical identifiers.
            foreach (['Hello', 'Your order', 'Dear customer', 'Thank you'] as $english) {
                $this->assertStringNotContainsString($english, $body);
            }
        }
    }

    public function test_nothing_in_the_module_promises_unlimited_whatsapp(): void
    {
        // NOT-008 / NOT-030, Rule 14 guardrail 8: message volume has a real
        // per-message cost and the product never claims otherwise.
        foreach ($this->phpFilesUnder('Modules/Notification') as $path) {
            $source = mb_strtolower((string) file_get_contents($path));

            foreach (['unlimited whatsapp', 'whatsapp tanpa batas', 'pesan tanpa batas'] as $claim) {
                $this->assertStringNotContainsString($claim, $source);
            }
        }
    }

    public function test_no_surface_describes_the_manual_fallback_as_automation(): void
    {
        // FR-095: the fallback is explicit and visible, and is never presented or
        // sold as automation. The state name itself carries the word "prepared".
        $this->assertSame(
            'MANUAL_FALLBACK_PREPARED',
            \App\Modules\Notification\Models\NotificationIntent::STATE_MANUAL_FALLBACK_PREPARED
        );

        $label = \App\Modules\Notification\Http\NotificationProjection::stateLabel(
            \App\Modules\Notification\Models\NotificationIntent::STATE_MANUAL_FALLBACK_PREPARED
        );

        $this->assertStringContainsString('staf perlu mengirimnya', $label);
        $this->assertStringNotContainsString('terkirim', mb_strtolower($label));
    }

    public function test_a_sent_intent_is_never_labelled_as_delivered_to_the_customer(): void
    {
        $label = \App\Modules\Notification\Http\NotificationProjection::stateLabel(
            \App\Modules\Notification\Models\NotificationIntent::STATE_SENT
        );

        // We hold no delivery receipt. "Diterima penyedia" is what actually
        // happened; "terkirim ke pelanggan" would be a claim the system cannot
        // support (Rule 01).
        $this->assertSame('Diterima penyedia pesan', $label);
        $this->assertStringNotContainsString('pelanggan', $label);
    }
}
