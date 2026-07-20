<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The TENANT is the isolation boundary and the billing boundary (Rule 02).
 *
 * It is the root of the hierarchy:
 *   User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet
 *
 * `tenants` is the one business table that legitimately carries no `tenant_id`,
 * because its own `id` IS the tenant dimension.
 *
 * No subscription, plan, entitlement, or billing column exists here. Pricing is
 * a locked owner decision (Rule 14) and subscription is Step 12.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');

            // Stable, human-usable handle. Globally unique because it may appear
            // in an operator-facing context; it is never authorization proof.
            $table->string('slug')->unique();

            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamps();
            $table->softDeletes();
        });

        // A tenant is never resurrected by reusing a slug: uniqueness is global
        // and permanent, so a citation of a tenant slug cannot silently change
        // meaning.
        DB::statement(<<<'SQL'
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_slug_format_check
            CHECK (slug ~ '^[a-z0-9]([a-z0-9-]*[a-z0-9])?$')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
