<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Services\TrackingTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 — THE SCHEMA GUARANTEES, ASSERTED AGAINST LIVE POSTGRESQL (Rule 43).
 *
 * These are the claims that must hold no matter what the application code does,
 * so they are tested against the DATABASE rather than against a service. A future
 * migration that adds a plausible-looking `token` column, or a future import that
 * rewrites a revocation record, fails here — which is the point: the application
 * layer cannot see those paths, and the database can.
 */
final class TrackingSchemaTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->pluck('column_name')
            ->map(static fn ($c): string => (string) $c)
            ->all();
    }

    /**
     * FR-086 / TRK-002 — the guarantee that matters most on this surface.
     *
     * Asserted structurally rather than by reading the service, because the claim
     * is about what CAN be stored, not about what one code path happens to store.
     */
    public function test_no_table_carries_a_plaintext_token_or_otp_column(): void
    {
        // An ALLOWLIST of the columns permitted to carry a secret-adjacent name,
        // each for a stated reason. An allowlist rather than a substring
        // deny-list because "contains the word code" would flag
        // `revoke_reason_code` — a reason code is not a credential — while
        // still missing a column innocuously named `value`.
        $permitted = [
            // Digests, not secrets. The whole point of the design.
            'token_hash' => 'SHA-256 of the tracking token; the plaintext is never stored.',
            'code_hash' => 'SHA-256 of the OTP; the plaintext is never stored.',
            // Identifiers and reason vocabulary, not credentials.
            'tracking_token_id' => 'a UUID foreign key, not token material.',
            'revoke_reason_code' => 'a reason vocabulary entry (lifecycle §9), not a credential.',
        ];

        $suspicious = ['token', 'plaintext', 'secret', 'otp', 'code', 'password', 'credential'];

        foreach (['tracking_tokens', 'tracking_access_events', 'tracking_otp_challenges'] as $table) {
            foreach ($this->columns($table) as $column) {
                if (array_key_exists($column, $permitted)) {
                    continue;
                }

                foreach ($suspicious as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $column,
                        "Column {$table}.{$column} looks like it could hold a plaintext "
                        .'tracking token or OTP. Both are SECRET (Rule 21) and only their '
                        .'hashes are ever stored (TRK-002, TRK-019, NOT-016). If this column '
                        .'is legitimate, add it to the allowlist above WITH ITS REASON — an '
                        .'unexplained exemption is how this guarantee decays.'
                    );
                }
            }
        }
    }

    public function test_notification_tables_carry_no_credential_or_body_column(): void
    {
        // A stored rendered body would be a second copy of what was said to a
        // customer, and a stored credential would be a committed secret in a
        // different disguise (Rule 03, Rule 46 hard rule 2).
        foreach (['notification_intents', 'notification_attempts'] as $table) {
            foreach ($this->columns($table) as $column) {
                foreach (['password', 'access_token', 'credential', 'api_key', 'body', 'message_text'] as $needle) {
                    $this->assertStringNotContainsString($needle, $column,
                        "Column {$table}.{$column} must not exist: notification rows carry no "
                        .'credential and no rendered message body.');
                }
            }
        }
    }

    public function test_every_step_7_business_table_carries_tenant_id(): void
    {
        foreach ([
            'tracking_tokens', 'tracking_access_events', 'tracking_otp_challenges',
            'notification_intents', 'notification_attempts',
        ] as $table) {
            $this->assertContains('tenant_id', $this->columns($table),
                "{$table} must carry tenant_id from its introducing migration (Rule 02 hard rule 7).");
        }
    }

    public function test_no_step_7_table_carries_a_floating_point_column(): void
    {
        // Step 7 introduces no money, but a float anywhere near an amount-bearing
        // path would be inherited by whatever reads it (Rule 04 hard rule 2).
        $floats = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', [
                'tracking_tokens', 'tracking_access_events', 'tracking_otp_challenges',
                'notification_intents', 'notification_attempts',
            ])
            ->whereIn('data_type', ['double precision', 'real', 'float'])
            ->pluck('column_name')
            ->all();

        $this->assertSame([], $floats, 'No Step 7 column may be a floating-point type (Rule 04).');
    }

    public function test_tracking_access_events_refuses_update_at_the_database(): void
    {
        $s = $this->trackingScenario('schema-append-only');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $eventId = TrackingAccessEvent::query()
            ->forTenant($s['context']->tenantId())
            ->where('tracking_token_id', $issued->token->id)
            ->value('id');

        $this->expectException(QueryException::class);
        DB::table('tracking_access_events')->where('id', $eventId)->update(['type' => 'Dipalsukan']);
    }

    public function test_tracking_access_events_refuses_deletion_at_the_database(): void
    {
        $s = $this->trackingScenario('schema-no-delete');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $this->expectException(QueryException::class);
        DB::table('tracking_access_events')
            ->where('tracking_token_id', $issued->token->id)
            ->delete();
    }

    public function test_notification_attempts_refuses_mutation_at_the_database(): void
    {
        $s = $this->trackingScenario('schema-attempt-append-only');

        $intentId = (string) Str::uuid();
        DB::table('notification_intents')->insert([
            'id' => $intentId,
            'tenant_id' => $s['context']->tenantId(),
            'outlet_id' => $s['outlet_id'],
            'order_id' => $s['order']->id,
            'event_type' => 'uji',
            'template_key' => 'order_received',
            'template_version' => 1,
            'category' => 'transactional',
            'channel' => 'whatsapp',
            'recipient_normalized' => '6281200000000',
            'dedup_key' => hash('sha256', 'uji-'.$intentId),
            'state' => 'PENDING',
            'scheduled_for' => now(),
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attemptId = (string) Str::uuid();
        DB::table('notification_attempts')->insert([
            'id' => $attemptId,
            'tenant_id' => $s['context']->tenantId(),
            'intent_id' => $intentId,
            'attempt_number' => 1,
            'provider_key' => 'fake_provider',
            'outcome' => 'timeout',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('notification_attempts')->where('id', $attemptId)->update(['outcome' => 'accepted']);
    }

    /**
     * The database refuses to hold a fabricated delivery claim.
     *
     * This is the CHECK constraint doing work no amount of code review can: even a
     * direct SQL write cannot mark a message SENT without an acceptance timestamp.
     */
    public function test_a_sent_intent_cannot_exist_without_an_acceptance_timestamp(): void
    {
        $s = $this->trackingScenario('schema-no-fake-send');

        $this->expectException(QueryException::class);
        DB::table('notification_intents')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['context']->tenantId(),
            'outlet_id' => $s['outlet_id'],
            'order_id' => $s['order']->id,
            'event_type' => 'uji',
            'template_key' => 'order_received',
            'template_version' => 1,
            'category' => 'transactional',
            'channel' => 'whatsapp',
            'recipient_normalized' => '6281200000000',
            'dedup_key' => hash('sha256', 'palsu'),
            'state' => 'SENT',
            // accepted_at deliberately absent — the CHECK must refuse this row.
            'scheduled_for' => now(),
            'attempt_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_suppressed_intent_must_state_its_reason(): void
    {
        $s = $this->trackingScenario('schema-suppression-reason');

        $this->expectException(QueryException::class);
        DB::table('notification_intents')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['context']->tenantId(),
            'outlet_id' => $s['outlet_id'],
            'order_id' => $s['order']->id,
            'event_type' => 'uji',
            'template_key' => 'marketing_promotion',
            'template_version' => 1,
            'category' => 'marketing',
            'channel' => 'whatsapp',
            'recipient_normalized' => '6281200000000',
            'dedup_key' => hash('sha256', 'tanpa-alasan'),
            'state' => 'SUPPRESSED',
            // suppression_reason absent — a tenant seeing "not sent" with no
            // reason cannot act on it, so the CHECK refuses the row.
            'scheduled_for' => now(),
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_tracking_token_cannot_reference_an_order_in_another_tenant(): void
    {
        $a = $this->trackingScenario('schema-xtenant-a');
        $b = $this->trackingScenario('schema-xtenant-b');

        // The composite foreign key (tenant_id, order_id) -> orders(tenant_id, id)
        // makes this structurally impossible, regardless of application code.
        $this->expectException(QueryException::class);
        DB::table('tracking_tokens')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $a['context']->tenantId(),
            'order_id' => $b['order']->id,
            'outlet_id' => $a['outlet_id'],
            'token_hash' => hash('sha256', 'palsu'),
            'state' => 'ISSUED',
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_tracking_token_must_carry_an_expiry(): void
    {
        $s = $this->trackingScenario('schema-bounded-expiry');

        // There is no path in this schema to an unbounded tracking link
        // (TRK-005, TRACKING_ACCESS_LIFECYCLE §4.3).
        $this->expectException(QueryException::class);
        DB::table('tracking_tokens')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['context']->tenantId(),
            'order_id' => $s['order']->id,
            'outlet_id' => $s['outlet_id'],
            'token_hash' => hash('sha256', 'tanpa-kedaluwarsa'),
            'state' => 'ISSUED',
            'issued_at' => now(),
            'expires_at' => null,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_revoked_token_must_carry_a_reason_code(): void
    {
        $s = $this->trackingScenario('schema-revoke-reason');

        $this->expectException(QueryException::class);
        DB::table('tracking_tokens')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['context']->tenantId(),
            'order_id' => $s['order']->id,
            'outlet_id' => $s['outlet_id'],
            'token_hash' => hash('sha256', 'dicabut-tanpa-alasan'),
            'state' => 'REVOKED',
            'revoked_at' => now(),
            // revoke_reason_code absent — knowing WHY a link was revoked is what
            // distinguishes a lost link from a leaked one (lifecycle §9).
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
