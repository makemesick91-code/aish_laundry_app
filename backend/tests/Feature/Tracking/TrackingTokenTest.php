<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingToken;
use App\Modules\Tracking\Services\TrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT A — THE TRACKING-TOKEN LIFECYCLE (FR-086, FR-087, FR-088).
 *
 * Against live PostgreSQL (Rule 43). Every value is fictional (Rule 23).
 *
 * The negative cases are the point of this file. A token lifecycle that only
 * proves "issuing works" proves almost nothing: what protects a customer is that
 * a revoked link stops resolving, that a rotated link stops resolving, that the
 * order number is not a credential, and that none of those failures is
 * distinguishable from any other.
 */
final class TrackingTokenTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    private TrackingTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = app(TrackingTokenService::class);
    }

    // =====================================================================
    // FR-086 — entropy, CSPRNG, hash-only persistence
    // =====================================================================

    public function test_the_token_is_high_entropy_and_url_safe(): void
    {
        $s = $this->trackingScenario('tok-entropy');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $plaintext = $issued->plaintext();

        // 32 CSPRNG bytes, base64url, unpadded → 43 characters, 256 bits.
        $this->assertSame(43, strlen($plaintext));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $plaintext,
            'The token travels in a URL path and must need no encoding.');
    }

    public function test_a_thousand_tokens_are_all_distinct(): void
    {
        // A weak generator shows up as collisions long before it shows up as a
        // breach. Cheap to check; catastrophic to miss.
        $generated = [];
        for ($i = 0; $i < 1000; $i++) {
            $generated[] = TrackingTokenService::generatePlaintext();
        }

        $this->assertCount(1000, array_unique($generated));
    }

    public function test_only_the_hash_is_persisted_and_it_matches(): void
    {
        $s = $this->trackingScenario('tok-hash-only');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $row = (array) DB::table('tracking_tokens')->where('id', $issued->token->id)->first();

        $this->assertSame(TrackingTokenService::hash($issued->plaintext()), $row['token_hash']);

        // The plaintext appears in NO column of the row, under any name.
        foreach ($row as $column => $value) {
            if (! is_string($value)) {
                continue;
            }
            $this->assertStringNotContainsString($issued->plaintext(), $value,
                "The plaintext token leaked into tracking_tokens.{$column} (TRK-002).");
        }
    }

    public function test_the_plaintext_never_appears_in_a_lifecycle_event_payload(): void
    {
        $s = $this->trackingScenario('tok-no-event-leak');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());
        $this->tokens->revoke($s['context'], $issued->token->id, null, 'over_shared', 'Tautan tersebar.');

        $events = TrackingAccessEvent::query()
            ->forTenant($s['context']->tenantId())
            ->where('tracking_token_id', $issued->token->id)
            ->get();

        $this->assertGreaterThan(1, $events->count());

        foreach ($events as $event) {
            $encoded = json_encode($event->payload ?? []);
            $this->assertStringNotContainsString($issued->plaintext(), (string) $encoded,
                'A tracking-access event carried the token plaintext (TRK-019).');
            $this->assertStringNotContainsString($issued->token->token_hash, (string) $encoded,
                'A tracking-access event carried the token hash; even the hash has no business here.');
        }
    }

    // =====================================================================
    // FR-087 — independence from the order number
    // =====================================================================

    public function test_the_token_is_not_derived_from_the_order_number(): void
    {
        $s = $this->trackingScenario('tok-independence');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $plaintext = $issued->plaintext();
        $orderNumber = $s['order']->order_number;

        $this->assertNotSame($orderNumber, $plaintext);
        $this->assertStringNotContainsString($orderNumber, $plaintext);

        // No shared run of six characters. A token DERIVED from the order number
        // — encoded, reversed, salted with a constant — would almost certainly
        // share one; a CSPRNG token essentially never does.
        for ($i = 0; $i + 6 <= strlen($orderNumber); $i++) {
            $this->assertStringNotContainsString(substr($orderNumber, $i, 6), $plaintext,
                'The token shares a substring with the order number, which is printed on '
                .'the nota and read aloud over the phone (FR-087, TRK-003).');
        }
    }

    public function test_two_orders_with_similar_numbers_get_unrelated_tokens(): void
    {
        $a = $this->trackingScenario('tok-unrelated-a');
        $b = $this->trackingScenario('tok-unrelated-b');

        $tokenA = $this->tokens->issue($a['context'], $a['order'], $this->ref())->plaintext();
        $tokenB = $this->tokens->issue($b['context'], $b['order'], $this->ref())->plaintext();

        // Both fixtures use the SAME order number on purpose. If the token were
        // derived from it, these two would be equal.
        $this->assertSame($a['order']->order_number, $b['order']->order_number);
        $this->assertNotSame($tokenA, $tokenB);
    }

    // =====================================================================
    // FR-088 — revocation, expiry, rotation
    // =====================================================================

    public function test_issuing_records_the_lifecycle_event_and_a_bounded_expiry(): void
    {
        $s = $this->trackingScenario('tok-issue');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $this->assertSame(TrackingToken::STATE_ISSUED, $issued->token->state);
        $this->assertNotNull($issued->token->expires_at);
        $this->assertTrue($issued->token->expires_at->isFuture());
        $this->assertTrue($issued->token->isLive());

        $this->assertSame(1, TrackingAccessEvent::query()
            ->forTenant($s['context']->tenantId())
            ->where('tracking_token_id', $issued->token->id)
            ->where('type', TrackingAccessEvent::TYPE_ISSUED)
            ->count());
    }

    public function test_a_second_live_link_cannot_be_issued_for_the_same_order(): void
    {
        $s = $this->trackingScenario('tok-one-live');
        $this->tokens->issue($s['context'], $s['order'], $this->ref());

        // Two live links for one order is the state the partial unique index
        // forbids, and silently superseding one somebody is holding would be a
        // surprise rather than a convenience.
        $this->expectException(ApiException::class);
        $this->tokens->issue($s['context'], $s['order'], $this->ref());
    }

    public function test_a_draft_order_cannot_be_given_a_tracking_link(): void
    {
        $s = $this->trackingScenario('tok-draft');
        DB::table('orders')->where('id', $s['order']->id)->update(['status' => 'DRAFT']);
        $order = $s['order']->fresh();

        $this->expectException(ApiException::class);
        $this->tokens->issue($s['context'], $order, $this->ref());
    }

    public function test_revocation_is_terminal_immediate_and_reason_bearing(): void
    {
        $s = $this->trackingScenario('tok-revoke');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $revoked = $this->tokens->revoke(
            $s['context'], $issued->token->id, null, 'shared_publicly', 'Diposting di grup.'
        );

        $this->assertSame(TrackingToken::STATE_REVOKED, $revoked->state);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame('shared_publicly', $revoked->revoke_reason_code);
        $this->assertSame($s['context']->membershipId(), $revoked->revoked_by_membership_id);
        $this->assertFalse($revoked->isLive());
        $this->assertTrue($revoked->isTerminal());
    }

    public function test_a_revoked_link_cannot_be_reactivated(): void
    {
        $s = $this->trackingScenario('tok-no-reactivate');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());
        $this->tokens->revoke($s['context'], $issued->token->id, null, 'lost', null);

        // There is no reactivation method to call, by design — a revocation that
        // whoever performed it could undo is not a security control. Rotation of a
        // terminal record is refused; recovery is a NEW issuance.
        $this->expectException(ApiException::class);
        $this->tokens->rotate($s['context'], $issued->token->id, null, $this->ref(), 'lost', null);
    }

    public function test_revoking_twice_is_idempotent_rather_than_an_error(): void
    {
        $s = $this->trackingScenario('tok-revoke-twice');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $first = $this->tokens->revoke($s['context'], $issued->token->id, null, 'lost', null);
        $second = $this->tokens->revoke($s['context'], $issued->token->id, null, 'lost', null);

        // Already revoked is the state the caller wanted. The original actor,
        // timestamp, and reason are preserved — the second call rewrites nothing.
        $this->assertSame(TrackingToken::STATE_REVOKED, $second->state);
        $this->assertEquals($first->revoked_at, $second->revoked_at);
    }

    public function test_rotation_mints_a_new_token_and_supersedes_the_old_one(): void
    {
        $s = $this->trackingScenario('tok-rotate');
        $first = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        $second = $this->tokens->rotate(
            $s['context'], $first->token->id, null, $this->ref(), 'over_shared', 'Terkirim ke nomor salah.'
        );

        $this->assertNotSame($first->plaintext(), $second->plaintext());
        $this->assertNotSame($first->token->id, $second->token->id);

        $old = TrackingToken::query()->find($first->token->id);
        $this->assertSame(TrackingToken::STATE_SUPERSEDED, $old->state);
        $this->assertNotNull($old->superseded_at);
        $this->assertSame($second->token->id, $old->superseded_by_id);

        $this->assertSame(TrackingToken::STATE_ISSUED, $second->token->state);
        $this->assertTrue($second->token->isLive());
    }

    public function test_a_rotated_link_gets_its_own_expiry_and_never_inherits_the_old_one(): void
    {
        $s = $this->trackingScenario('tok-rotate-expiry');
        $first = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        DB::table('tracking_tokens')->where('id', $first->token->id)
            ->update(['expires_at' => now()->addDay()]);

        $second = $this->tokens->rotate(
            $s['context'], $first->token->id, null, $this->ref(), 'lost', null
        );

        // A reissued access is a NEW record with a NEW expiry; it never inherits,
        // extends, or reuses the prior one (lifecycle §4.4).
        $this->assertTrue($second->token->expires_at->greaterThan(now()->addDays(2)));
    }

    public function test_a_stale_expected_version_refuses_the_write(): void
    {
        $s = $this->trackingScenario('tok-optimistic');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        // Two staff revoking simultaneously: the second is rejected as a version
        // conflict, so the link is revoked once with one recorded actor and reason.
        $this->expectException(ApiException::class);
        $this->tokens->revoke($s['context'], $issued->token->id, 999, 'lost', null);
    }

    public function test_a_replayed_issue_command_does_not_mint_a_second_link(): void
    {
        $s = $this->trackingScenario('tok-replay');
        $reference = $this->ref();
        $this->tokens->issue($s['context'], $s['order'], $reference);

        $before = TrackingToken::query()->forTenant($s['context']->tenantId())->count();

        try {
            $this->tokens->issue($s['context'], $s['order'], $reference);
            $this->fail('A replayed issue command must not silently mint a second live link.');
        } catch (ApiException $e) {
            // Reported rather than silently returning the original, because the
            // original PLAINTEXT is unrecoverable — the honest recovery is rotation.
        }

        $this->assertSame($before, TrackingToken::query()->forTenant($s['context']->tenantId())->count());
    }

    // =====================================================================
    // Expiry (TRK-005) and tenant isolation
    // =====================================================================

    public function test_an_expired_link_is_not_live_even_while_the_row_still_reads_issued(): void
    {
        $s = $this->trackingScenario('tok-expiry-read');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        // Aged BOTH timestamps: the `expires_at > issued_at` CHECK correctly
        // refuses a row whose expiry precedes its issuance, so a realistic
        // "issued two days ago, expired yesterday" fixture is the honest one.
        DB::table('tracking_tokens')->where('id', $issued->token->id)->update([
            'issued_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        // Expiry is decided by the SERVER on read. It does not wait for a sweep,
        // and a client clock never extends an access (lifecycle §4.3).
        $this->assertFalse(TrackingToken::query()->find($issued->token->id)->isLive());
    }

    public function test_completion_expiry_only_ever_tightens(): void
    {
        $s = $this->trackingScenario('tok-completion-expiry');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());
        $originalExpiry = $issued->token->expires_at;

        // Completion 30 days ago → ceiling is in the past relative to the
        // 60-day issue horizon, so the expiry tightens.
        $this->tokens->applyCompletionExpiry($s['context'], $s['order'], now()->subDays(5));
        $tightened = TrackingToken::query()->find($issued->token->id);
        $this->assertTrue($tightened->expires_at->lessThan($originalExpiry));

        // A LATER completion anchor must not push the expiry back out. Extending
        // an expiry in place is forbidden outright (lifecycle §5).
        $afterTightening = $tightened->expires_at;
        $this->tokens->applyCompletionExpiry($s['context'], $s['order'], now()->addDays(100));
        $this->assertEquals(
            $afterTightening,
            TrackingToken::query()->find($issued->token->id)->expires_at,
            'applyCompletionExpiry must never lengthen a tracking link\'s life.'
        );
    }

    public function test_a_token_in_another_tenant_is_not_addressable(): void
    {
        $a = $this->trackingScenario('tok-iso-a');
        $b = $this->trackingScenario('tok-iso-b');

        $issuedB = $this->tokens->issue($b['context'], $b['order'], $this->ref());

        // Tenant A revoking tenant B's link 404s exactly as an absent one would.
        $this->expectException(ApiException::class);
        $this->tokens->revoke($a['context'], $issuedB->token->id, null, 'lost', null);
    }

    public function test_the_issued_link_object_refuses_to_serialise(): void
    {
        $s = $this->trackingScenario('tok-no-serialise');
        $issued = $this->tokens->issue($s['context'], $s['order'], $this->ref());

        // A SECRET that serialises conveniently is a SECRET that lands in a cache
        // entry, a session, or a queued job payload by accident.
        $this->expectException(\LogicException::class);
        serialize($issued);
    }
}
