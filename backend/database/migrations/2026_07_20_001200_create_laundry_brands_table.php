<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A LAUNDRY BRAND is a commercial brand owned by a tenant. A tenant may have
 * multiple brands (Rule 02, hard rule 3).
 *
 * Brand assets are tenant-uploaded and therefore UNTRUSTED: an SVG is never
 * inlined from a tenant upload (Rule 32). No such asset column exists yet.
 *
 * LOGO STATUS: NOT APPROVED — no platform logo may be fabricated (Rule 25).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_brands', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->timestamps();
            $table->softDeletes();

            // A slug is unique WITHIN a tenant, never globally. Two unrelated
            // tenants may legitimately run a brand of the same name, and
            // forcing global uniqueness would leak the existence of another
            // tenant's brand through a collision error (Rule 02, Rule 32).
            $table->unique(['tenant_id', 'slug'], 'laundry_brands_tenant_slug_unique');

            $table->index('tenant_id', 'laundry_brands_tenant_id_index');
        });

        // Referenced by the composite foreign key on `outlets`, which is what
        // makes a cross-tenant brand/outlet pairing structurally impossible
        // rather than merely discouraged by convention.
        DB::statement(<<<'SQL'
            ALTER TABLE laundry_brands
            ADD CONSTRAINT laundry_brands_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_brands');
    }
};
