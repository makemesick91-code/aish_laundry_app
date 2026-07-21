<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing-consent history for a customer, per tenant (FR-027, FR-028).
 *
 * APPEND-ONLY, AND THAT IS THE WHOLE DESIGN
 * -----------------------------------------
 * FR-028: "A recorded opt-out shall never be reset by a data import, a bulk
 * update, or a migration." A mutable `marketing_consent` boolean on `customers`
 * would satisfy FR-027 and fail FR-028, because any bulk update could flip it
 * back and nothing would show that it had ever been withdrawn.
 *
 * This table has no update path. Granting appends a row; withdrawing appends
 * another. The current state is the LATEST row for a (customer, type). An
 * opt-out cannot be reset because there is no row to overwrite — the guarantee
 * is structural rather than a rule somebody must remember (invariants C5-C7).
 *
 * A DB rule makes it structural at the engine too: UPDATE and DELETE on this
 * table are rejected. Even a migration, an import, or a direct `psql` session
 * cannot rewrite consent history.
 *
 * `recorded_at` is set SERVER-SIDE and never accepted from a client. A consent
 * timestamp a client could choose is a consent record an operator could
 * backdate (T-07 in the Step 4 threat model).
 *
 * `source` records HOW consent was obtained — at the counter, in the app, by
 * written form. Consent with no provenance is consent that cannot be defended.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id');

            // What was consented to. A closed set: an unrecognised consent type
            // is a consent nobody can honour at send time (Rule 08).
            $table->string('consent_type');

            // granted | withdrawn. Withdrawal is a first-class recorded state,
            // not the absence of a grant.
            $table->string('state');

            $table->string('source');

            // WHO recorded it. Nullable because a customer may withdraw through
            // a self-service path where no staff actor exists — not because the
            // actor is optional when there is one (Rule 46 hard rule 1).
            $table->uuid('recorded_by_membership_id')->nullable();

            $table->timestampTz('recorded_at');

            // Free-text context. Never contains the customer's own contact
            // details; those are on `customers` (Rule 46 hard rule 2).
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index('tenant_id', 'customer_consents_tenant_id_index');

            // The lookup the "current state" query uses, in its own order.
            $table->index(
                ['tenant_id', 'customer_id', 'consent_type', 'recorded_at'],
                'customer_consents_current_state_index'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customer_consents
            ADD CONSTRAINT customer_consents_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE (invariant C4).
        DB::statement(<<<'SQL'
            ALTER TABLE customer_consents
            ADD CONSTRAINT customer_consents_tenant_customer_foreign
            FOREIGN KEY (tenant_id, customer_id)
            REFERENCES customers (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE customer_consents
            ADD CONSTRAINT customer_consents_state_check
            CHECK (state IN ('granted', 'withdrawn'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE customer_consents
            ADD CONSTRAINT customer_consents_type_check
            CHECK (consent_type IN ('marketing_whatsapp', 'marketing_email', 'marketing_sms'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE customer_consents
            ADD CONSTRAINT customer_consents_source_check
            CHECK (source IN ('counter', 'customer_app', 'written_form', 'phone', 'import'))
        SQL);

        // APPEND-ONLY, ENFORCED BY THE ENGINE.
        //
        // The application has no update path, but "the application has no path"
        // is only as strong as the application. FR-028 names a MIGRATION as one
        // of the things that must not reset an opt-out, and a migration runs
        // outside the application entirely. These rules mean an UPDATE or
        // DELETE against consent history silently affects nothing, from any
        // client, including psql.
        DB::statement(<<<'SQL'
            CREATE RULE customer_consents_no_update AS
            ON UPDATE TO customer_consents DO INSTEAD NOTHING
        SQL);

        DB::statement(<<<'SQL'
            CREATE RULE customer_consents_no_delete AS
            ON DELETE TO customer_consents DO INSTEAD NOTHING
        SQL);
    }

    public function down(): void
    {
        // The rules are dropped with the table.
        Schema::dropIfExists('customer_consents');
    }
};
