<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Services\TrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT C — THE PUBLIC TRACKING API (FR-089 … FR-092).
 *
 * THE CENTRAL ASSERTION IN THIS FILE is response equivalence: an unknown token, a
 * malformed one, an expired one, a revoked one, a superseded one, and a throttled
 * request must all produce ONE byte-identical response. Any difference — a distinct
 * code, a distinct message, a distinct length — turns the most exposed surface in
 * the product into an oracle answering "does this order exist?" (TRK-007, AC-07-02,
 * Rule 48 hard rule 5).
 */
final class PublicTrackingApiTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    private function issueFor(string $slug): array
    {
        $s = $this->trackingScenario($slug);
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        return ['scenario' => $s, 'issued' => $issued];
    }

    // =====================================================================
    // The happy path
    // =====================================================================

    public function test_a_live_token_resolves_to_the_safe_projection(): void
    {
        $f = $this->issueFor('api-happy');

        $this->getJson('/api/v1/public/tracking/'.$f['issued']->plaintext())
            ->assertOk()
            ->assertJsonPath('data.tracking.order_number', $f['scenario']['order']->order_number)
            ->assertJsonPath('data.tracking.customer.masked_name', 'Budi F.');
    }

    public function test_resolution_does_not_change_the_token_state_so_a_shared_link_keeps_working(): void
    {
        $f = $this->issueFor('api-shared');
        $token = $f['issued']->plaintext();

        // Sharing is a FEATURE: the link is forwarded to the family member
        // collecting the laundry, and it must keep working for them (TRK-014).
        $this->getJson('/api/v1/public/tracking/'.$token)->assertOk();
        $this->getJson('/api/v1/public/tracking/'.$token)->assertOk();
        $this->getJson('/api/v1/public/tracking/'.$token)->assertOk();

        $row = DB::table('tracking_tokens')->where('id', $f['issued']->token->id)->first();
        $this->assertSame('ISSUED', $row->state);
        $this->assertSame(3, (int) $row->view_count);
    }

    // =====================================================================
    // FR-092 — transport headers
    // =====================================================================

    public function test_every_public_response_carries_the_no_index_and_no_store_contract(): void
    {
        $f = $this->issueFor('api-headers');
        $response = $this->getJson('/api/v1/public/tracking/'.$f['issued']->plaintext());

        $response->assertOk();
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // The token is in the URL PATH, so any outbound referrer would hand a
        // third party a working credential. This header is load-bearing.
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp,
            'The CSP is what makes "no remote font, no analytics, no third-party embed" '
            .'structural rather than a promise (Rule 31 hard rule 10, Rule 32 hard rule 26).');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_the_not_available_response_also_carries_the_headers(): void
    {
        // A dead link is still a token-bearing URL: it must not be indexed or
        // cached either, and the headers must not depend on the outcome.
        $response = $this->getJson('/api/v1/public/tracking/tidakadatokenyangseperti123456789');

        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    // =====================================================================
    // TRK-007 — no existence oracle. THE test of this file.
    // =====================================================================

    public function test_every_invalid_token_produces_a_byte_identical_response(): void
    {
        // Six genuinely different internal causes.
        $unknown = $this->issueFor('api-eq-unknown');
        $expired = $this->issueFor('api-eq-expired');
        $revoked = $this->issueFor('api-eq-revoked');
        $superseded = $this->issueFor('api-eq-superseded');

        DB::table('tracking_tokens')->where('id', $expired['issued']->token->id)->update([
            'issued_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        app(TrackingTokenService::class)->revoke(
            $revoked['scenario']['context'], $revoked['issued']->token->id, null, 'lost', null
        );

        app(TrackingTokenService::class)->rotate(
            $superseded['scenario']['context'], $superseded['issued']->token->id,
            null, $this->ref(), 'over_shared', null
        );

        $bodies = [
            'unknown' => $this->getJson('/api/v1/public/tracking/'.TrackingTokenService::generatePlaintext())->getContent(),
            'malformed' => $this->getJson('/api/v1/public/tracking/tidak-valid')->getContent(),
            'expired' => $this->getJson('/api/v1/public/tracking/'.$expired['issued']->plaintext())->getContent(),
            'revoked' => $this->getJson('/api/v1/public/tracking/'.$revoked['issued']->plaintext())->getContent(),
            'superseded' => $this->getJson('/api/v1/public/tracking/'.$superseded['issued']->plaintext())->getContent(),
            // The order number, which is printed on the nota and read aloud.
            'order_number' => $this->getJson('/api/v1/public/tracking/'.$unknown['scenario']['order']->order_number)->getContent(),
        ];

        $distinct = array_unique(array_values($bodies));

        $this->assertCount(
            1,
            $distinct,
            'Every invalid-token response must be byte-identical. A caller that could '
            ."distinguish these cases would hold an existence oracle (TRK-007, AC-07-02).\n"
            .print_r($bodies, true)
        );
    }

    public function test_every_invalid_token_returns_the_same_status_and_code(): void
    {
        // Single path segments only. A payload containing a slash never reaches
        // this route at all — the router rejects it first — so including one here
        // would assert the framework's behaviour rather than the resolver's.
        foreach ([
            TrackingTokenService::generatePlaintext(),
            'tidak-valid',
            'ALS-2026-000042',
            '..%5c..%5cwindows',
            str_repeat('A', 400),
        ] as $candidate) {
            $this->getJson('/api/v1/public/tracking/'.$candidate)
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'TRACKING_LINK_NOT_AVAILABLE');
        }
    }

    public function test_the_order_number_is_never_a_credential(): void
    {
        $f = $this->issueFor('api-order-number');

        // FR-087 / TRK-003: the order number is short, sequential, printed, and
        // read aloud — guessable by design, and precisely because it is guessable
        // it grants access to nothing.
        $this->getJson('/api/v1/public/tracking/'.$f['scenario']['order']->order_number)
            ->assertStatus(404);
    }

    // =====================================================================
    // Rate limiting and enumeration budget
    // =====================================================================

    public function test_repeated_failed_lookups_are_throttled_indistinguishably(): void
    {
        $notAvailable = $this->getJson('/api/v1/public/tracking/'.TrackingTokenService::generatePlaintext())
            ->getContent();

        // Burn well past the per-IP budget with distinct unknown tokens.
        for ($i = 0; $i < 70; $i++) {
            $this->getJson('/api/v1/public/tracking/'.TrackingTokenService::generatePlaintext());
        }

        // A VALID token now also returns the generic response, because the source
        // is throttled. That is correct: the throttle must not be a side channel
        // that says "this one was real".
        $f = $this->issueFor('api-throttle');
        $throttled = $this->getJson('/api/v1/public/tracking/'.$f['issued']->plaintext());

        $this->assertSame(404, $throttled->getStatusCode());
        $this->assertSame($notAvailable, $throttled->getContent(),
            'A throttled response must be indistinguishable from a not-found response.');
    }

    // =====================================================================
    // Injection and traversal
    // =====================================================================

    public function test_a_script_payload_in_a_token_is_never_reflected(): void
    {
        // Deliberately slash-free. A payload containing `/` — even encoded — is
        // rejected by the router before the resolver sees it, so asserting on it
        // would test the framework rather than this surface. An `<img onerror>`
        // payload is the same reflection test without that confound.
        $payload = '<img src=x onerror=alert(1)>';

        $response = $this->get('/lacak/'.rawurlencode($payload));

        $response->assertStatus(200); // the generic unavailable PAGE
        $this->assertStringNotContainsString($payload, $response->getContent(),
            'A token value must never be reflected into the page (T7-15).');
        $this->assertStringNotContainsString('onerror=', $response->getContent());
    }

    public function test_tenant_supplied_content_is_escaped_on_the_portal_page(): void
    {
        $s = $this->trackingScenario('api-xss-outlet');

        // A tenant names their outlet with markup. Blade escapes by default and
        // there is no raw output on this surface — the page must render it as TEXT.
        DB::table('outlets')->where('id', $s['outlet_id'])
            ->update(['name' => 'Outlet <script>alert("xss")</script>']);

        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());
        $response = $this->get('/lacak/'.$issued->plaintext());

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;', $response->getContent());
    }

    public function test_the_portal_page_carries_the_robots_meta_tag_as_well_as_the_header(): void
    {
        $f = $this->issueFor('api-meta-robots');
        $response = $this->get('/lacak/'.$f['issued']->plaintext());

        $response->assertOk();
        // A header can be stripped by a proxy; the meta tag travels in the
        // document. The token is in the URL, so an indexed page is a permanent
        // public leak of a working credential (FR-092, TRK-006).
        $this->assertStringContainsString('name="robots"', $response->getContent());
        $this->assertStringContainsString('noindex', $response->getContent());
    }

    public function test_the_portal_page_loads_no_remote_asset_and_runs_no_script(): void
    {
        $f = $this->issueFor('api-selfcontained');
        $html = $this->get('/lacak/'.$f['issued']->plaintext())->getContent();

        // The portal is opened once, on an unknown device, over an unknown
        // network. It is self-contained by construction (Rule 31 hard rule 10).
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('https://', $html);
        $this->assertStringNotContainsString('//fonts.', $html);
    }

    public function test_the_portal_page_never_prompts_an_app_install(): void
    {
        $f = $this->issueFor('api-no-install');
        $html = $this->get('/lacak/'.$f['issued']->plaintext())->getContent();

        // DEC-0006 and DEC-0014: the portal is never degraded into "install the
        // app first", and the Customer Android app never replaces it.
        foreach (['play.google.com', 'unduh aplikasi', 'install aplikasi', 'pasang aplikasi'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $html);
        }
    }

    public function test_the_unavailable_page_states_the_recovery_step(): void
    {
        $html = $this->get('/lacak/'.TrackingTokenService::generatePlaintext())->getContent();

        // An error that only names a failure, with no recovery action, is rejected
        // by Rule 29 hard rule 9.
        $this->assertStringContainsString('minta tautan baru', mb_strtolower($html));
    }

    public function test_there_is_no_public_write_route_beyond_the_two_otp_endpoints(): void
    {
        $publicWrites = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/public/')) {
                continue;
            }
            if (array_intersect($route->methods(), ['POST', 'PATCH', 'PUT', 'DELETE']) === []) {
                continue;
            }
            $publicWrites[] = $route->getName();
        }

        sort($publicWrites);

        // FR-086 … FR-099 define no other customer-initiated portal write.
        // Requesting a pickup or a delivery from the portal is Step 8 (DEC-0039 §5).
        $this->assertSame([
            'api.v1.public.tracking.otp.request',
            'api.v1.public.tracking.otp.verify',
        ], $publicWrites);
    }
}
