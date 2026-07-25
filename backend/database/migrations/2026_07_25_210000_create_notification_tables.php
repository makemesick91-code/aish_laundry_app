<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 7 — NOTIFICATION AND WHATSAPP · the transactional outbox (FR-093 … FR-099).
 *
 * Authorised by the canonical roadmap (Master Source §24); scope opened by DEC-0039.
 *
 * WHY AN OUTBOX AND NOT A DIRECT SEND
 * -----------------------------------
 * FR-099: "a messaging failure shall never cancel, block, or alter an order's
 * state." That is a STRUCTURAL requirement, and the structure is this table. A
 * business transaction commits; only then is an intent enqueued (DB::afterCommit);
 * dispatch happens outside that transaction entirely. A provider being down, a
 * queue being dead, or these tables being unreachable therefore cannot roll back an
 * order, a payment, or a production transition — there is no code path from a
 * notification failure back into business state (NOT-001, NOT-027, NOT-029).
 *
 * WHY DEDUP IS A DATABASE CONSTRAINT AND NOT A CHECK
 * --------------------------------------------------
 * FR-098 requires exactly-once "including across retries, queue replays, and
 * scheduler restarts". A check-then-insert loses that race by definition under
 * concurrent workers. UNIQUE (tenant_id, dedup_key) makes it structural: the second
 * insert is refused by PostgreSQL and the caller returns the original intent. The
 * key is a digest over the identity NOT-002 names — recipient + event + order +
 * intended send window.
 *
 * NAMING — DELIBERATE, NOT INCIDENTAL
 * -----------------------------------
 * The attempt table is `notification_attempts`, never `notification_deliveries`.
 * `deliveries` is a Step 8 token and the DEC-0039 label audit rejects it, correctly:
 * a guard cannot tell a message-delivery record from a laundry-delivery record by
 * name. Nothing here is named `reminder_*` — the H+1/H+3/H+7/H+14 LADDER is Step 9
 * (DEC-0039 §4). This step sends a message; it never decides when to chase.
 *
 * NO MONEY, NO SECRETS
 * --------------------
 * No money column (Rule 04). No provider credential, no OTP plaintext, no tracking
 * token plaintext, and no full address is ever written to these tables (NOT-015,
 * NOT-016, Rule 46 hard rule 2). `NotificationSchemaTest` asserts the absence.
 */
return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------------
        // notification_intents — the outbox.
        // -------------------------------------------------------------------
        Schema::create('notification_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');

            // Nullable: an intent may concern an order (almost always) but the
            // column is not the identity of the intent, the dedup key is.
            $table->uuid('order_id')->nullable();
            $table->uuid('customer_id')->nullable();

            // What happened, in domain terms — never a vendor event name.
            $table->string('event_type', 64);

            // Template identity and version. The CATEGORY is derived from the
            // template and never from a caller-supplied string, which is what makes
            // "a marketing message is never routed through a transactional path"
            // (NOT-024) structural rather than a rule someone must remember.
            $table->string('template_key', 96);
            $table->unsignedSmallInteger('template_version')->default(1);
            $table->string('category', 24);

            $table->string('channel', 24)->default('whatsapp');

            // E.164 digits, normalised. Fictional in every fixture (Rule 23/45).
            $table->string('recipient_normalized', 32);

            // The digest over recipient + event + order + send window (FR-098).
            $table->char('dedup_key', 64);

            $table->string('state', 32);

            // Why nothing was sent, when nothing was sent. A tenant seeing
            // "SUPPRESSED" with no reason cannot act on it.
            $table->string('suppression_reason', 48)->nullable();

            // When the message becomes eligible. Quiet-hours deferral writes the
            // next permitted window here; it never drops the message (NOT-021).
            $table->timestampTz('scheduled_for');
            $table->boolean('deferred_for_quiet_hours')->default(false);

            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempted_at')->nullable();

            // Set when a provider ACCEPTED the message. It is never rendered as
            // "delivered to the customer": we hold no delivery receipt and claiming
            // one would be a false claim (Rule 01).
            $table->timestampTz('accepted_at')->nullable();
            $table->string('provider_key', 48)->nullable();

            // The provider's own reference, when it returns one. An opaque string,
            // never a credential.
            $table->string('provider_reference', 128)->nullable();

            $table->string('failure_code', 64)->nullable();
            $table->uuid('client_reference')->nullable();
            $table->timestampsTz();

            $table->index('tenant_id', 'notification_intents_tenant_id_index');
            $table->index(['tenant_id', 'order_id'], 'notification_intents_tenant_order_index');
            $table->index(['state', 'scheduled_for'], 'notification_intents_dispatch_index');
        });

        DB::statement('ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_tenant_outlet_foreign FOREIGN KEY (tenant_id, outlet_id) REFERENCES outlets (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_tenant_order_foreign FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_tenant_customer_foreign FOREIGN KEY (tenant_id, customer_id) REFERENCES customers (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_tenant_id_id_unique UNIQUE (tenant_id, id)');

        // FR-098, structurally. Not a check — a constraint.
        DB::statement('CREATE UNIQUE INDEX notification_intents_tenant_dedup_unique ON notification_intents (tenant_id, dedup_key)');
        DB::statement('CREATE UNIQUE INDEX notification_intents_tenant_client_ref_unique ON notification_intents (tenant_id, client_reference) WHERE client_reference IS NOT NULL');

        DB::statement("ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_category_check CHECK (category IN ('transactional','marketing'))");
        DB::statement("ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_state_check CHECK (state IN ('PENDING','DEFERRED','SENDING','SENT','FAILED_RETRYABLE','FAILED_PERMANENT','SUPPRESSED','MANUAL_FALLBACK_PREPARED'))");
        // A suppressed intent must say why; anything else must not pretend to.
        DB::statement("ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_suppression_shape_check CHECK ((state = 'SUPPRESSED') = (suppression_reason IS NOT NULL))");
        // Only a provider-accepted send may carry an acceptance timestamp. This is
        // the database refusing to hold a fabricated delivery claim.
        DB::statement("ALTER TABLE notification_intents ADD CONSTRAINT notification_intents_accepted_shape_check CHECK ((state = 'SENT') = (accepted_at IS NOT NULL))");

        // -------------------------------------------------------------------
        // notification_attempts — append-only attempt history.
        //
        // Append-only at the database because "the failure is visible and retried
        // under a bounded policy" (FR-099) is only true if the history of attempts
        // cannot be quietly tidied away.
        // -------------------------------------------------------------------
        Schema::create('notification_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('intent_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->string('provider_key', 48);

            // accepted | rejected | unavailable | timeout | malformed | error
            $table->string('outcome', 32);

            // A first-party, mapped code. NEVER the vendor's raw error payload —
            // that would leak vendor shape into stored business data, which is the
            // coupling FR-093 forbids.
            $table->string('failure_code', 64)->nullable();

            // Human-readable, redacted, first-party. Never a credential, never an
            // OTP, never a token, never a full address (NOT-016, Rule 46).
            $table->text('detail')->nullable();

            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'intent_id'], 'notification_attempts_tenant_intent_index');
        });

        DB::statement('ALTER TABLE notification_attempts ADD CONSTRAINT notification_attempts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE notification_attempts ADD CONSTRAINT notification_attempts_tenant_intent_foreign FOREIGN KEY (tenant_id, intent_id) REFERENCES notification_intents (tenant_id, id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE notification_attempts ADD CONSTRAINT notification_attempts_outcome_check CHECK (outcome IN ('accepted','rejected','unavailable','timeout','malformed','error','manual_link_prepared'))");

        $fn = 'notification_attempts_refuse_mutation';
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION {$fn}()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                RAISE EXCEPTION 'notification_attempts is append-only: % is refused — a failed send stays visible and is never tidied away (FR-099, NOT-018).', TG_OP
                    USING ERRCODE = 'restrict_violation';
            END; \$\$
        SQL);
        DB::statement("CREATE TRIGGER notification_attempts_refuse_update BEFORE UPDATE ON notification_attempts FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER notification_attempts_refuse_delete BEFORE DELETE ON notification_attempts FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER notification_attempts_refuse_truncate BEFORE TRUNCATE ON notification_attempts FOR EACH STATEMENT EXECUTE FUNCTION {$fn}()");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS notification_attempts_refuse_update ON notification_attempts');
        DB::statement('DROP TRIGGER IF EXISTS notification_attempts_refuse_delete ON notification_attempts');
        DB::statement('DROP TRIGGER IF EXISTS notification_attempts_refuse_truncate ON notification_attempts');
        DB::statement('DROP FUNCTION IF EXISTS notification_attempts_refuse_mutation()');
        Schema::dropIfExists('notification_attempts');

        Schema::dropIfExists('notification_intents');
    }
};
