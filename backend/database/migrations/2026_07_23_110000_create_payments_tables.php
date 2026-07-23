<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENTS AND THE FINANCIAL LEDGER (FR-061 … FR-070).
 *
 * MONEY IS INTEGER RUPIAH (Rule 04). Every amount is `bigInteger` `*_rupiah`.
 *
 * THE LEDGER IS APPEND-ONLY, ENFORCED AT THE ENGINE (FR-066, FR-067).
 * ------------------------------------------------------------------
 * A payment is never hard-deleted and never soft-deleted — there is no
 * `deleted_at` column at all. A correction is a NEW row: a `reversal` that
 * references the payment it reverses. The order's paid amount is the sum of
 * `payment` rows minus the sum of `reversal` rows, so history is added to, never
 * rewritten. A `BEFORE DELETE ... ENABLE ALWAYS` trigger refuses a hard delete
 * even via a raw query-builder call — the Step 4 lesson (a model event is not
 * enough; `session_replication_role = 'replica'` skips an ordinary trigger, so
 * ENABLE ALWAYS is required).
 *
 * A REVERSAL IS A POSITIVE AMOUNT WITH A DIRECTION, NEVER A NEGATIVE MULTIPLIER
 * (Rule 04 hard rule 8). `amount_rupiah` is always > 0; `kind` decides whether it
 * adds to or subtracts from the paid total. A negative amount would be a money
 * value that could silently flip a total.
 *
 * PAID STATE IS NEVER A CLIENT CLAIM (FR-064). `status` starts `pending` for a
 * gateway payment and becomes `succeeded` only from a server-verified event
 * (Unit D); a cash payment recorded by authenticated staff is `succeeded` at
 * record time. The column's domain is fixed by a CHECK; the transitions are
 * enforced in the service.
 *
 * TENANT ISOLATION IS STRUCTURAL (Rule 48): the order a payment settles, and the
 * payment a reversal reverses, are bound to the same tenant by composite foreign
 * keys `(tenant_id, x_id)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');

            // Human-usable reconciliation reference; grants no access (FR-053 principle).
            $table->string('payment_number');

            // FR-062 idempotency key. Client-generated once, reused on retry.
            $table->string('client_reference');

            // payment | reversal
            $table->string('kind')->default('payment');

            // cash | bank_transfer | qris (FR-061). QRIS is a method/state only —
            // no external provider is integrated in this step.
            $table->string('method');

            // pending | succeeded | failed | reversed
            $table->string('status')->default('pending');

            $table->string('currency')->default('IDR');

            // Always > 0. Direction comes from `kind`, never from a sign (Rule 04 h8).
            $table->bigInteger('amount_rupiah');

            // For matching a verified gateway callback (Unit D). Never a secret.
            $table->string('gateway_reference')->nullable();

            // A reversal references the payment it reverses (FR-067).
            $table->uuid('reverses_payment_id')->nullable();
            $table->text('reversal_reason')->nullable();

            // Server-side timestamp of the money event; a client value here would
            // be a backdated payment.
            $table->timestampTz('received_at')->nullable();
            $table->uuid('recorded_by_membership_id')->nullable();
            $table->uuid('created_by_membership_id')->nullable();

            $table->integer('version')->default(1);

            // NO softDeletes: the ledger is append-only (FR-066).
            $table->timestamps();

            $table->unique(['tenant_id', 'payment_number'], 'payments_tenant_number_unique');
            $table->unique(['tenant_id', 'client_reference'], 'payments_tenant_client_ref_unique');
            $table->index('tenant_id', 'payments_tenant_id_index');
            $table->index(['tenant_id', 'order_id'], 'payments_tenant_order_index');
            $table->index(['tenant_id', 'status'], 'payments_tenant_status_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // Self composite-unique so a reversal can bind to (tenant_id, payment_id).
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        // STRUCTURAL TENANT GUARANTEE: a payment settles an order in the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_tenant_order_foreign
            FOREIGN KEY (tenant_id, order_id)
            REFERENCES orders (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // A reversal reverses a payment in the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_tenant_reverses_foreign
            FOREIGN KEY (tenant_id, reverses_payment_id)
            REFERENCES payments (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_kind_check CHECK (kind IN ('payment', 'reversal'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_method_check
            CHECK (method IN ('cash', 'bank_transfer', 'qris'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN ('pending', 'succeeded', 'failed', 'reversed'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_currency_check CHECK (currency = 'IDR')
        SQL);

        // Positive amount only. A reversal reduces via `kind`, never a sign.
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_amount_positive CHECK (amount_rupiah > 0)
        SQL);

        // A reversal references its original and carries a reason; a payment does
        // neither. The two move together or the row is refused.
        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ADD CONSTRAINT payments_reversal_consistent
            CHECK (
                (kind = 'reversal') = (reverses_payment_id IS NOT NULL)
                AND (kind <> 'reversal' OR reversal_reason IS NOT NULL)
            )
        SQL);

        // --- append-only enforcement (FR-066) -------------------------------
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION payments_refuse_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION
                    'A financial transaction is never deleted (FR-066, Rule 04). '
                    'Correct it with a reversal or adjustment entry instead.'
                    USING ERRCODE = 'restrict_violation';
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER payments_refuse_delete_trigger
            BEFORE DELETE ON payments
            FOR EACH ROW
            EXECUTE FUNCTION payments_refuse_delete()
        SQL);

        // ENABLE ALWAYS, not the default ENABLE ORIGIN: a replication-role session
        // would skip an ordinary trigger, which is exactly the bypass FR-066 must
        // not have.
        DB::statement('ALTER TABLE payments ENABLE ALWAYS TRIGGER payments_refuse_delete_trigger');
    }

    public function down(): void
    {
        // The trigger and function are owned by this table; dropping the table
        // via the fresh-migration path removes them. Drop the trigger explicitly
        // first so a targeted rollback is clean.
        DB::statement('DROP TRIGGER IF EXISTS payments_refuse_delete_trigger ON payments');
        Schema::dropIfExists('payments');
        DB::statement('DROP FUNCTION IF EXISTS payments_refuse_delete()');
    }
};
