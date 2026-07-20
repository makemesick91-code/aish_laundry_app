<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An OUTLET is a physical location belonging to a brand (Rule 02, hard rule 4).
 *
 * WHY `outlets` CARRIES BOTH `tenant_id` AND `laundry_brand_id`
 * ------------------------------------------------------------
 * An outlet already reaches its tenant through its brand, so `tenant_id` looks
 * redundant. It is not, for two reasons:
 *
 *   1. Every tenant-scoped query filters on ONE column without a join. A scope
 *      that has to join to be correct is a scope somebody will eventually
 *      forget (Rule 02, hard rule 8).
 *   2. Combined with the COMPOSITE foreign key below, it makes a cross-tenant
 *      pairing STRUCTURALLY IMPOSSIBLE. PostgreSQL will reject an outlet whose
 *      `tenant_id` disagrees with its brand's `tenant_id`. The isolation
 *      property is enforced by the database, not remembered by a developer.
 *
 * The plain single-column FK to `laundry_brands(id)` is deliberately NOT used;
 * the composite FK to `laundry_brands(tenant_id, id)` replaces it and is
 * strictly stronger.
 *
 * `timezone` is outlet-local time, which governs quiet hours (Rule 08) and
 * aging display (Rule 10) in later Steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');
            $table->uuid('laundry_brand_id');

            $table->string('name');
            $table->string('code');
            $table->string('timezone')->default('Asia/Jakarta');

            $table->timestamps();
            $table->softDeletes();

            // Unique within the tenant, never globally — a global collision
            // would disclose that another tenant holds that code (Rule 32).
            $table->unique(['tenant_id', 'code'], 'outlets_tenant_code_unique');

            $table->index('tenant_id', 'outlets_tenant_id_index');
            $table->index(['tenant_id', 'laundry_brand_id'], 'outlets_tenant_brand_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE outlets
            ADD CONSTRAINT outlets_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE.
        // An outlet may only reference a brand that belongs to the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE outlets
            ADD CONSTRAINT outlets_tenant_brand_foreign
            FOREIGN KEY (tenant_id, laundry_brand_id)
            REFERENCES laundry_brands (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // Referenced by the composite foreign key on `audit_entries`, so an
        // audit entry can never point at an outlet in a different tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE outlets
            ADD CONSTRAINT outlets_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
