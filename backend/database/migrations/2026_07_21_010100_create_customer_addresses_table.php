<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved addresses for a customer (FR-024).
 *
 * A customer holds several: home and office pickup are both normal.
 *
 * DATA CLASS: RESTRICTED. An address is the most sensitive routine field the
 * product holds (Rule 21, §data classification). It is masked by viewing
 * context, it is never rendered in a list row, and it NEVER reaches the public
 * tracking portal in any form (FR-025, Rule 32 hard rules 4 and 8).
 *
 * That masking is enforced at the serializer boundary rather than here — a
 * column cannot mask itself — but the column comment exists so nobody adds this
 * field to a projection without meeting it.
 *
 * `is_pickup_suitable` / `is_delivery_suitable` are operational facts about the
 * address (a third-floor walk-up may be fine to deliver to and impractical to
 * collect from), not permissions. They gate nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Carried directly, exactly as `outlets` carries it: every
            // tenant-scoped query filters ONE column without a join. A scope
            // that has to join to be correct is a scope somebody forgets
            // (Rule 02, hard rule 8).
            $table->uuid('tenant_id');
            $table->uuid('customer_id');

            // "Rumah", "Kantor" — operator-chosen, shown to staff.
            $table->string('label');

            $table->text('address_line');
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();

            // Free-text landmark. Couriers navigate by landmark in Indonesia
            // far more than by postal code.
            $table->text('notes')->nullable();

            $table->boolean('is_pickup_suitable')->default(true);
            $table->boolean('is_delivery_suitable')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'customer_addresses_tenant_id_index');
            $table->index(
                ['tenant_id', 'customer_id'],
                'customer_addresses_tenant_customer_index'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customer_addresses
            ADD CONSTRAINT customer_addresses_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE (invariant C4).
        // An address may only reference a customer in the SAME tenant.
        // PostgreSQL rejects the pairing; no application code is trusted with it.
        DB::statement(<<<'SQL'
            ALTER TABLE customer_addresses
            ADD CONSTRAINT customer_addresses_tenant_customer_foreign
            FOREIGN KEY (tenant_id, customer_id)
            REFERENCES customers (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // At most ONE primary address per customer, and only among the live
        // ones. A partial unique index expresses "at most one" without
        // forbidding the many non-primary rows.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX customer_addresses_one_primary
            ON customer_addresses (tenant_id, customer_id)
            WHERE is_primary AND deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE customer_addresses
            ADD CONSTRAINT customer_addresses_line_not_blank
            CHECK (char_length(btrim(address_line)) > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
