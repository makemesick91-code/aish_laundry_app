<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 7 — CUSTOMER TRACKING · the tracking-access aggregate (FR-086 … FR-092).
 *
 * Authorised by the canonical roadmap (Master Source §24); the runtime-scope guard
 * transition that lets a `tracking_tokens` table legally exist is DEC-0039. A
 * permitted label is not an implemented feature (DEC-0039 §10) — what makes this
 * schema trustworthy is the constraints below, not the permission to create it.
 *
 * THREE TABLES, AND WHY EACH EXISTS
 * ---------------------------------
 *   tracking_tokens         the aggregate. One live access per order.
 *   tracking_access_events  the immutable lifecycle audit (issued/viewed/revoked/…).
 *   tracking_otp_challenges the FR-091 OTP gate for sensitive actions.
 *
 * WHAT IS DELIBERATELY ABSENT
 * ---------------------------
 * There is NO plaintext-token column and NO plaintext-OTP column, here or anywhere.
 * The token is `SECRET` (Rule 21): only `token_hash` is stored, and the plaintext
 * exists solely inside the link handed to the customer (TRK-002, TRK-019). The same
 * holds for the OTP: only `code_hash`. `TrackingSchemaTest` asserts this against
 * `information_schema.columns` rather than trusting this comment, so a future
 * migration adding a plausible-looking column fails the suite.
 *
 * There is also no money column. Step 7 records no money and mutates none (Rule 04);
 * the portal READS amount-due from the Step 5 payment state.
 *
 * TENANT BINDING IS STRUCTURAL, NOT REMEMBERED
 * --------------------------------------------
 * Every table carries `tenant_id` from this, its introducing migration, and every
 * child is composite-FK'd to its parent's `(tenant_id, id)`. A tracking token in
 * tenant A therefore cannot reference an order in tenant B — PostgreSQL refuses it
 * regardless of what the application code does (Rule 02, Rule 39, Rule 48).
 *
 * NOTHING HERE BELONGS TO STEP 8 OR 9. No pickup, no delivery, no courier, no
 * reminder ladder, no aging clock. The projection READS the immutable first
 * READY_FOR_PICKUP fact Step 6 recorded; it never writes or restarts it (Rule 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // tracking_tokens — the TrackingAccess aggregate.
        // -------------------------------------------------------------------
        Schema::create('tracking_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');
            $table->uuid('outlet_id');

            // SHA-256 hex of the plaintext. Deterministic on purpose: the token is
            // 256 bits of CSPRNG material, not a low-entropy human secret, so there
            // is nothing for a slow hash to defend and a deterministic digest is
            // what makes UNIQUE + O(1) lookup possible on the most exposed surface
            // in the product. A bcrypt-style hash would force a table scan per
            // public request — an availability defect, not a hardening measure.
            $table->char('token_hash', 64);

            // ISSUED | REVOKED | EXPIRED | SUPERSEDED. THROTTLED is deliberately
            // NOT a stored state: persisting it would let an attacker lock a
            // victim's link out by hammering it. Throttling is a transient Redis
            // condition keyed on the hashed token and hashed IP.
            $table->string('state', 24);

            $table->timestampTz('issued_at');

            // NOT NULL, always. There is no path in this schema to an unbounded
            // tracking link (TRK-005 — canonical default 30 days after completion).
            $table->timestampTz('expires_at');

            $table->timestampTz('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);

            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('revoked_by_membership_id')->nullable();
            $table->string('revoke_reason_code', 64)->nullable();
            $table->text('revoke_reason')->nullable();

            $table->timestampTz('superseded_at')->nullable();
            $table->uuid('superseded_by_id')->nullable();

            $table->uuid('issued_by_membership_id')->nullable();
            $table->uuid('client_reference')->nullable();

            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz();

            $table->index('tenant_id', 'tracking_tokens_tenant_id_index');
            $table->index(['tenant_id', 'order_id'], 'tracking_tokens_tenant_order_index');
            $table->index(['state', 'expires_at'], 'tracking_tokens_state_expiry_index');
        });

        DB::statement('ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_tenant_order_foreign FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_tenant_outlet_foreign FOREIGN KEY (tenant_id, outlet_id) REFERENCES outlets (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_tenant_id_id_unique UNIQUE (tenant_id, id)');

        // GLOBALLY unique, not per tenant. The public resolver receives a token and
        // NO tenant (a visitor never supplies a tenant, TRK-021), so the hash alone
        // must identify at most one row; a per-tenant unique would permit a
        // collision the resolver could not adjudicate.
        DB::statement('CREATE UNIQUE INDEX tracking_tokens_token_hash_unique ON tracking_tokens (token_hash)');

        // At most ONE live access per order. Rotation supersedes the old row inside
        // the same transaction, so this never blocks a legitimate rotate.
        DB::statement("CREATE UNIQUE INDEX tracking_tokens_one_live_per_order ON tracking_tokens (tenant_id, order_id) WHERE state = 'ISSUED'");

        // Server-side idempotency for the issue/rotate commands (Rule 07/20).
        DB::statement('CREATE UNIQUE INDEX tracking_tokens_tenant_client_ref_unique ON tracking_tokens (tenant_id, client_reference) WHERE client_reference IS NOT NULL');

        DB::statement("ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_state_check CHECK (state IN ('ISSUED','REVOKED','EXPIRED','SUPERSEDED'))");

        // A terminal state must carry its timestamp, and an ISSUED row must carry
        // none. Enforced here rather than trusted to the service, because a
        // half-written revocation is a link that looks revoked and still resolves.
        DB::statement("ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_revoked_shape_check CHECK ((state = 'REVOKED') = (revoked_at IS NOT NULL))");
        DB::statement("ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_superseded_shape_check CHECK ((state = 'SUPERSEDED') = (superseded_at IS NOT NULL))");
        // Revocation always carries a reason code (TRACKING_ACCESS_LIFECYCLE §9).
        DB::statement("ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_revoke_reason_check CHECK (state <> 'REVOKED' OR revoke_reason_code IS NOT NULL)");
        DB::statement('ALTER TABLE tracking_tokens ADD CONSTRAINT tracking_tokens_expiry_after_issue_check CHECK (expires_at > issued_at)');

        // -------------------------------------------------------------------
        // tracking_access_events — immutable lifecycle audit (TRK-024).
        //
        // Append-only at the DATABASE, so a path this application cannot see — an
        // import, a psql session, a future migration — cannot rewrite the record of
        // who revoked a link and why.
        // -------------------------------------------------------------------
        Schema::create('tracking_access_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('tracking_token_id');
            $table->string('type', 64);
            $table->uuid('actor_membership_id')->nullable();

            // NEVER carries the token plaintext or an OTP value (TRK-019, NOT-016).
            // What it may carry: state transitions, reason codes, a hashed client
            // fingerprint. TrackingSchemaTest asserts no row ever holds a value
            // shaped like a token.
            $table->jsonb('payload')->default('{}');

            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'tracking_token_id'], 'tracking_access_events_tenant_token_index');
        });

        DB::statement('ALTER TABLE tracking_access_events ADD CONSTRAINT tracking_access_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_access_events ADD CONSTRAINT tracking_access_events_tenant_token_foreign FOREIGN KEY (tenant_id, tracking_token_id) REFERENCES tracking_tokens (tenant_id, id) ON DELETE RESTRICT');

        $fn = 'tracking_access_events_refuse_mutation';
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION {$fn}()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                RAISE EXCEPTION 'tracking_access_events is append-only: % is refused — the record of who revoked a tracking link, and why, is never rewritten (TRK-022, TRK-024).', TG_OP
                    USING ERRCODE = 'restrict_violation';
            END; \$\$
        SQL);
        DB::statement("CREATE TRIGGER tracking_access_events_refuse_update BEFORE UPDATE ON tracking_access_events FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER tracking_access_events_refuse_delete BEFORE DELETE ON tracking_access_events FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER tracking_access_events_refuse_truncate BEFORE TRUNCATE ON tracking_access_events FOR EACH STATEMENT EXECUTE FUNCTION {$fn}()");

        // -------------------------------------------------------------------
        // tracking_otp_challenges — the FR-091 gate.
        //
        // Bound to (token, order, action) so a challenge minted for one action can
        // never verify another, and a challenge for one token never verifies
        // another's. Only the HASH of the code is stored.
        // -------------------------------------------------------------------
        Schema::create('tracking_otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('tracking_token_id');
            $table->uuid('order_id');

            // change_delivery_address | request_schedule_change — exactly the two
            // sensitive actions FR-091 names. No other action is invented here.
            $table->string('action', 48);

            $table->char('code_hash', 64);
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'tracking_token_id'], 'tracking_otp_challenges_tenant_token_index');
        });

        DB::statement('ALTER TABLE tracking_otp_challenges ADD CONSTRAINT tracking_otp_challenges_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_otp_challenges ADD CONSTRAINT tracking_otp_challenges_tenant_token_foreign FOREIGN KEY (tenant_id, tracking_token_id) REFERENCES tracking_tokens (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_otp_challenges ADD CONSTRAINT tracking_otp_challenges_tenant_order_foreign FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE tracking_otp_challenges ADD CONSTRAINT tracking_otp_challenges_tenant_id_id_unique UNIQUE (tenant_id, id)');
        DB::statement("ALTER TABLE tracking_otp_challenges ADD CONSTRAINT tracking_otp_challenges_action_check CHECK (action IN ('change_delivery_address','request_schedule_change'))");
        DB::statement('ALTER TABLE tracking_otp_challenges ADD CONSTRAINT tracking_otp_challenges_expiry_check CHECK (expires_at > issued_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_otp_challenges');

        DB::statement('DROP TRIGGER IF EXISTS tracking_access_events_refuse_update ON tracking_access_events');
        DB::statement('DROP TRIGGER IF EXISTS tracking_access_events_refuse_delete ON tracking_access_events');
        DB::statement('DROP TRIGGER IF EXISTS tracking_access_events_refuse_truncate ON tracking_access_events');
        DB::statement('DROP FUNCTION IF EXISTS tracking_access_events_refuse_mutation()');
        Schema::dropIfExists('tracking_access_events');

        Schema::dropIfExists('tracking_tokens');
    }
};
