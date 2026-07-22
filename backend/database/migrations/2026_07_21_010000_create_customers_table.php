<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A CUSTOMER is the tenant-scoped anchor of order history and consent (FR-021).
 *
 * THE INVARIANT THIS TABLE EXISTS TO ENFORCE
 * ------------------------------------------
 * FR-022: "The same phone number appearing in two tenants shall produce two
 * separate, unrelated customer profiles that are never merged or
 * cross-referenced." Competing laundries share customers, so this is the normal
 * case, not an edge case (Rule 02 hard rule 11, Rule 18 invariant 9).
 *
 * The mechanism is deliberately structural rather than procedural. There is NO
 * global index on `phone_normalized`. Uniqueness is `(tenant_id,
 * phone_normalized)` only. A cross-tenant phone lookup therefore has no index to
 * use AND no code path that would want one — the absence of the global index is
 * the enforcement, not a comment asking developers to remember.
 *
 * WHY THE PHONE IS STORED TWICE
 * -----------------------------
 * `phone` preserves what the operator typed, because a customer recognises
 * their own number in the form they gave it. `phone_normalized` is the
 * digits-only form used for matching, computed SERVER-SIDE — a client never
 * supplies it, because a client-supplied match key is a client-supplied
 * identity claim.
 *
 * The normalized phone is NEVER an authorization key. It identifies a customer
 * within an already-authorised tenant scope and nothing more (Rule 02, hard
 * rule 9).
 *
 * NO HARD DELETE
 * --------------
 * `softDeletes()` only. A customer referenced by a future order must stay
 * resolvable, and a hard delete would orphan history that Step 5 will depend on
 * (T-18 in the Step 4 threat model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Human-usable within the tenant. Never a credential, never a
            // cross-tenant identifier.
            $table->string('code');

            $table->string('name');

            // As entered, and the server-computed match form.
            $table->string('phone');
            $table->string('phone_normalized');

            $table->string('email')->nullable();

            // CONFIDENTIAL free text. Excluded from every public projection by
            // allow-list, never by a denylist (FR-030, Rule 32 hard rule 7).
            $table->text('internal_notes')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Tenant-scoped, never global. A global collision would disclose
            // that another tenant holds that code (Rule 32).
            $table->unique(['tenant_id', 'code'], 'customers_tenant_code_unique');

            // FR-022 lives here. Scoped to the tenant, so the SAME number in a
            // different tenant is a different, unrelated customer.
            $table->unique(
                ['tenant_id', 'phone_normalized'],
                'customers_tenant_phone_unique'
            );

            $table->index('tenant_id', 'customers_tenant_id_index');
            $table->index(['tenant_id', 'name'], 'customers_tenant_name_index');
            $table->index(['tenant_id', 'status'], 'customers_tenant_status_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // Composite-FK target for every child table, so a child can never
        // reference a customer in a different tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        // Status is a closed set. A free-text status is how a lifecycle rule
        // becomes unenforceable (Rule 19's principle, applied to master data).
        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_status_check
            CHECK (status IN ('active', 'archived'))
        SQL);

        // A normalized phone with no digits cannot match anything and would
        // silently defeat the uniqueness constraint above.
        DB::statement(<<<'SQL'
            ALTER TABLE customers
            ADD CONSTRAINT customers_phone_normalized_not_blank
            CHECK (char_length(btrim(phone_normalized)) > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
