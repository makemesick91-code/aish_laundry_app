<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant's SERVICE CATALOGUE (FR-031 … FR-033).
 *
 * WHAT A CATALOGUE ENTRY IS AND IS NOT
 * ------------------------------------
 * These tables describe what a laundry OFFERS. They do not describe anything a
 * customer has ordered. There is no order, no order line, and no reference to
 * one anywhere here — orders are FR-048+ in Step 5, and DEC-0030 keeps them
 * structurally forbidden.
 *
 * `unit_kind` IS AN ENUM WITH A CHECK CONSTRAINT, NOT FREE TEXT
 * ------------------------------------------------------------
 * FR-031 fixes exactly two service shapes: kiloan priced by weight, satuan
 * priced per item. A free-text unit would let a tenant invent a third shape that
 * no pricing, quoting, or reporting code knows how to handle — and the defect
 * would surface in Step 5, far from where it was introduced (Rule 19's
 * principle: a value outside the canonical set breaks everything downstream at
 * once).
 *
 * The minimum-quantity rule differs by shape, so it is expressed as a check
 * constraint per shape rather than a nullable column nobody validates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- categories -----------------------------------------------------
        Schema::create('service_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'service_categories_tenant_code_unique');
            $table->index('tenant_id', 'service_categories_tenant_id_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE service_categories
            ADD CONSTRAINT service_categories_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE service_categories
            ADD CONSTRAINT service_categories_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        // --- services -------------------------------------------------------
        Schema::create('service_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('service_category_id')->nullable();

            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();

            // kiloan | satuan (FR-031).
            $table->string('unit_kind');

            // Weight in GRAMS for kiloan, item count for satuan. Integers, so
            // there is no floating-point weight to disagree about at the scale.
            $table->integer('minimum_quantity')->nullable();

            // Turnaround metadata, in hours. Descriptive only: Step 4 makes no
            // promise about completion time, and nothing enforces it (Rule 01 —
            // no capability claimed that the product does not provide).
            $table->integer('turnaround_hours')->nullable();

            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->integer('display_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'service_catalog_tenant_code_unique');
            $table->index('tenant_id', 'service_catalog_tenant_id_index');
            $table->index(['tenant_id', 'is_active'], 'service_catalog_tenant_active_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE service_catalog
            ADD CONSTRAINT service_catalog_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // A service may only sit in a category of the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE service_catalog
            ADD CONSTRAINT service_catalog_tenant_category_foreign
            FOREIGN KEY (tenant_id, service_category_id)
            REFERENCES service_categories (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE service_catalog
            ADD CONSTRAINT service_catalog_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        // FR-031: exactly two shapes, enforced by the engine.
        DB::statement(<<<'SQL'
            ALTER TABLE service_catalog
            ADD CONSTRAINT service_catalog_unit_kind_check
            CHECK (unit_kind IN ('kiloan', 'satuan'))
        SQL);

        // A minimum of zero or less is not a minimum.
        DB::statement(<<<'SQL'
            ALTER TABLE service_catalog
            ADD CONSTRAINT service_catalog_minimum_positive
            CHECK (minimum_quantity IS NULL OR minimum_quantity > 0)
        SQL);

        // An effective window that ends before it starts can never be true.
        DB::statement(<<<'SQL'
            ALTER TABLE service_catalog
            ADD CONSTRAINT service_catalog_effective_window_check
            CHECK (
                effective_from IS NULL
                OR effective_until IS NULL
                OR effective_until >= effective_from
            )
        SQL);

        // --- packages (FR-032) ---------------------------------------------
        Schema::create('service_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'service_packages_tenant_code_unique');
            $table->index('tenant_id', 'service_packages_tenant_id_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE service_packages
            ADD CONSTRAINT service_packages_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE service_packages
            ADD CONSTRAINT service_packages_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        // --- package composition -------------------------------------------
        Schema::create('service_package_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('service_package_id');
            $table->uuid('service_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'service_package_id', 'service_id'],
                'service_package_items_unique'
            );
            $table->index('tenant_id', 'service_package_items_tenant_id_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE service_package_items
            ADD CONSTRAINT service_package_items_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // Both sides bound to the SAME tenant (invariant S5). A package cannot
        // compose another tenant's service, and PostgreSQL is what says so.
        DB::statement(<<<'SQL'
            ALTER TABLE service_package_items
            ADD CONSTRAINT service_package_items_tenant_package_foreign
            FOREIGN KEY (tenant_id, service_package_id)
            REFERENCES service_packages (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE service_package_items
            ADD CONSTRAINT service_package_items_tenant_service_foreign
            FOREIGN KEY (tenant_id, service_id)
            REFERENCES service_catalog (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE service_package_items
            ADD CONSTRAINT service_package_items_quantity_positive
            CHECK (quantity > 0)
        SQL);

        // --- add-ons (FR-033) ----------------------------------------------
        //
        // CATALOGUE ENTRIES ONLY. Applying an add-on to an order line is Step 5
        // (DEC-0031 B). There is deliberately no linkage to anything orderable.
        Schema::create('service_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'service_addons_tenant_code_unique');
            $table->index('tenant_id', 'service_addons_tenant_id_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE service_addons
            ADD CONSTRAINT service_addons_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE service_addons
            ADD CONSTRAINT service_addons_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('service_addons');
        Schema::dropIfExists('service_catalog');
        Schema::dropIfExists('service_categories');
    }
};
