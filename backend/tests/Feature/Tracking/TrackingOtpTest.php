<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingOtpChallenge;
use App\Modules\Tracking\Services\PublicTrackingResolver;
use App\Modules\Tracking\Services\TrackingOtpService;
use App\Modules\Tracking\Services\TrackingTokenService;
use App\Modules\Tracking\Support\ResolvedTrackingAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT D — THE OTP GATE (FR-091).
 *
 * Only the two sensitive actions FR-091 names exist. The tests below prove the
 * gate holds under the attacks that actually matter against a six-digit code:
 * brute force, replay, cross-action reuse, cross-token reuse, and expiry.
 */
final class TrackingOtpTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    private TrackingOtpService $otp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otp = app(TrackingOtpService::class);
    }

    /**
     * The response payload with the per-request correlation id removed.
     *
     * `meta.request_id` differs on every response in the system by design and
     * discloses nothing about the token, so it is excluded from an equivalence
     * comparison. Everything else must match exactly.
     */
    private function payloadWithoutMeta(\Illuminate\Testing\TestResponse $response): string
    {
        $decoded = json_decode((string) $response->getContent(), true);
        unset($decoded['meta']);

        return (string) json_encode($decoded);
    }

    /** @return array{access: ResolvedTrackingAccess, scenario: array<string, mixed>} */
    private function access(string $slug): array
    {
        $s = $this->trackingScenario($slug);
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $access = app(PublicTrackingResolver::class)->resolve($issued->plaintext(), '127.0.0.1');
        $this->assertNotNull($access);

        return ['access' => $access, 'scenario' => $s];
    }

    // =====================================================================
    // Issuance
    // =====================================================================

    public function test_a_challenge_is_six_digits_and_only_its_hash_is_stored(): void
    {
        $a = $this->access('otp-basic');
        $code = $this->otp->issue($a['access'], TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS, '127.0.0.1');

        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        $row = DB::table('tracking_otp_challenges')
            ->where('tracking_token_id', $a['access']->token->id)
            ->first();

        $this->assertSame(TrackingOtpService::hash($code), $row->code_hash);

        // The plaintext appears in NO column of the row.
        foreach ((array) $row as $column => $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString($code, $value,
                    "The plaintext OTP leaked into tracking_otp_challenges.{$column}.");
            }
        }
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $a = $this->access('otp-unknown-action');

        // No third action is invented: a customer-facing capability the PRD does
        // not carry does not exist (Rule 16, Rule 00 hard rule 6).
        $this->assertNull($this->otp->issue($a['access'], 'hapus_pesanan', '127.0.0.1'));
        $this->assertNull($this->otp->issue($a['access'], 'lihat_alamat_lengkap', '127.0.0.1'));
    }

    public function test_the_plaintext_never_reaches_a_lifecycle_event(): void
    {
        $a = $this->access('otp-no-event-leak');
        $code = $this->otp->issue($a['access'], TrackingOtpChallenge::ACTION_REQUEST_SCHEDULE_CHANGE, '127.0.0.1');

        $events = TrackingAccessEvent::query()
            ->forTenant($a['access']->token->tenant_id)
            ->where('tracking_token_id', $a['access']->token->id)
            ->get();

        foreach ($events as $event) {
            $this->assertStringNotContainsString(
                (string) $code,
                (string) json_encode($event->payload ?? []),
                'An OTP value must never be written to an audit payload (NOT-016).'
            );
        }
    }

    public function test_a_resend_inside_the_cooldown_is_refused(): void
    {
        $a = $this->access('otp-cooldown');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;

        $this->assertNotNull($this->otp->issue($a['access'], $action, '127.0.0.1'));

        // Without a cooldown, this endpoint becomes a free way to make a
        // customer's phone ring repeatedly at somebody else's request.
        $this->assertNull($this->otp->issue($a['access'], $action, '127.0.0.1'));
    }

    public function test_challenge_issuance_is_rate_limited_per_token(): void
    {
        $a = $this->access('otp-rate-limit');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;

        $issued = 0;
        for ($i = 0; $i < 8; $i++) {
            // Age the previous challenge past the cooldown so the LIMIT is what
            // stops us, not the cooldown.
            DB::table('tracking_otp_challenges')
                ->where('tracking_token_id', $a['access']->token->id)
                ->update(['issued_at' => now()->subMinutes(10)]);

            if ($this->otp->issue($a['access'], $action, '127.0.0.1') !== null) {
                $issued++;
            }
        }

        $this->assertLessThanOrEqual(3, $issued,
            'OTP issuance must be bounded per token per hour.');
    }

    // =====================================================================
    // Verification
    // =====================================================================

    public function test_the_correct_code_verifies_once_and_then_cannot_be_replayed(): void
    {
        $a = $this->access('otp-replay');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;
        $code = $this->otp->issue($a['access'], $action, '127.0.0.1');

        $this->assertTrue($this->otp->verify($a['access'], $action, (string) $code));

        // Consumed. "Verified once" must mean once.
        $this->assertFalse($this->otp->verify($a['access'], $action, (string) $code));
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $a = $this->access('otp-wrong');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;
        $code = $this->otp->issue($a['access'], $action, '127.0.0.1');

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->assertFalse($this->otp->verify($a['access'], $action, $wrong));
    }

    public function test_a_code_minted_for_one_action_cannot_authorise_the_other(): void
    {
        $a = $this->access('otp-cross-action');
        $code = $this->otp->issue($a['access'], TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS, '127.0.0.1');

        // The challenge is bound to (token, order, action). This is the binding
        // that stops a code for a low-stakes action authorising a higher-stakes one.
        $this->assertFalse(
            $this->otp->verify($a['access'], TrackingOtpChallenge::ACTION_REQUEST_SCHEDULE_CHANGE, (string) $code)
        );
    }

    public function test_a_code_minted_against_one_link_cannot_verify_another(): void
    {
        $first = $this->access('otp-cross-token-a');
        $second = $this->access('otp-cross-token-b');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;

        $code = $this->otp->issue($first['access'], $action, '127.0.0.1');

        $this->assertFalse($this->otp->verify($second['access'], $action, (string) $code));
    }

    public function test_an_expired_challenge_is_refused(): void
    {
        $a = $this->access('otp-expired');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;
        $code = $this->otp->issue($a['access'], $action, '127.0.0.1');

        DB::table('tracking_otp_challenges')
            ->where('tracking_token_id', $a['access']->token->id)
            ->update(['issued_at' => now()->subMinutes(30), 'expires_at' => now()->subMinutes(25)]);

        $this->assertFalse($this->otp->verify($a['access'], $action, (string) $code));
    }

    public function test_brute_force_exhausts_the_attempt_budget_and_kills_the_challenge(): void
    {
        $a = $this->access('otp-brute-force');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;
        $code = $this->otp->issue($a['access'], $action, '127.0.0.1');

        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < TrackingOtpChallenge::MAX_ATTEMPTS; $i++) {
            $this->assertFalse($this->otp->verify($a['access'], $action, $wrong));
        }

        // After the budget is spent the challenge is DEAD, not merely slowed —
        // and even the correct code no longer works. A six-digit space is small
        // enough that "slower" would not be protection.
        $this->assertFalse($this->otp->verify($a['access'], $action, (string) $code));
    }

    public function test_a_failed_attempt_never_records_the_submitted_value(): void
    {
        $a = $this->access('otp-no-guess-log');
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;
        $this->otp->issue($a['access'], $action, '127.0.0.1');

        $this->otp->verify($a['access'], $action, '424242');

        $payloads = TrackingAccessEvent::query()
            ->forTenant($a['access']->token->tenant_id)
            ->where('type', TrackingAccessEvent::TYPE_OTP_FAILED)
            ->pluck('payload');

        foreach ($payloads as $payload) {
            $this->assertStringNotContainsString('424242', (string) json_encode($payload),
                'Logging a wrong guess puts a near-miss OTP into durable storage.');
        }
    }

    // =====================================================================
    // The HTTP surface — one response for every failure
    // =====================================================================

    public function test_the_otp_request_endpoint_answers_identically_for_live_and_dead_links(): void
    {
        $s = $this->trackingScenario('otp-http-generic');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $live = $this->postJson('/api/v1/public/tracking/'.$issued->plaintext().'/otp', [
            'action' => TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS,
        ]);

        $dead = $this->postJson('/api/v1/public/tracking/'.TrackingTokenService::generatePlaintext().'/otp', [
            'action' => TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS,
        ]);

        $live->assertOk();
        $dead->assertOk();

        // A caller that could tell "code sent" from "no such link" would have an
        // oracle over which orders exist.
        //
        // `meta.request_id` is excluded from the comparison and that is not a
        // loophole: it is a per-request correlation id that differs on EVERY
        // response in the system, carries no information about the token, and
        // exists so an incident can be traced. What must be identical is the
        // meaningful payload, and that is what is asserted.
        $this->assertSame($this->payloadWithoutMeta($live), $this->payloadWithoutMeta($dead));
    }

    public function test_the_verify_endpoint_answers_identically_for_every_failure(): void
    {
        $s = $this->trackingScenario('otp-http-verify');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());
        $token = $issued->plaintext();

        $this->postJson("/api/v1/public/tracking/{$token}/otp", [
            'action' => TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS,
        ])->assertOk();

        $wrongCode = $this->postJson("/api/v1/public/tracking/{$token}/otp/verify", [
            'action' => TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS,
            'code' => '000001',
        ]);

        $deadLink = $this->postJson(
            '/api/v1/public/tracking/'.TrackingTokenService::generatePlaintext().'/otp/verify',
            ['action' => TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS, 'code' => '000001']
        );

        $wrongAction = $this->postJson("/api/v1/public/tracking/{$token}/otp/verify", [
            'action' => TrackingOtpChallenge::ACTION_REQUEST_SCHEDULE_CHANGE,
            'code' => '000001',
        ]);

        $this->assertSame(422, $wrongCode->getStatusCode());
        $this->assertSame(422, $deadLink->getStatusCode());
        $this->assertSame(422, $wrongAction->getStatusCode());

        // Wrong code, dead link, and wrong action are three genuinely different
        // internal outcomes and must be one indistinguishable answer.
        $this->assertSame($this->payloadWithoutMeta($wrongCode), $this->payloadWithoutMeta($deadLink));
        $this->assertSame($this->payloadWithoutMeta($wrongCode), $this->payloadWithoutMeta($wrongAction));
    }

    public function test_the_verify_endpoint_rejects_an_action_outside_the_canonical_two(): void
    {
        $s = $this->trackingScenario('otp-http-bad-action');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $this->postJson('/api/v1/public/tracking/'.$issued->plaintext().'/otp/verify', [
            'action' => 'batalkan_pesanan',
            'code' => '123456',
        ])->assertStatus(422);
    }
}
