<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PER-BRAND PRICE LISTS (FR-034 … FR-038).
 *
 * MONEY IS INTEGER RUPIAH. THIS IS THE HARD GATE (Rule 04, FR-037).
 * -----------------------------------------------------------------
 * `amount_rupiah` is `bigInteger`. Not `decimal`, not `numeric`, not `float`,
 * not `double`. The smallest unit is one Rupiah and there is nothing below it,
 * so there is no fraction to lose. A floating-point money column here would be
 * inherited by every Step 5 order, invoice, and reconciliation built on top of
 * it — which is why the check runs against the LIVE schema in test rather than
 * by reading this file.
 *
 * `bigInteger` rather than `integer`: a 32-bit signed integer tops out near
 * 2.1 billion, and Rp2.1 billion is an ordinary monthly figure for a laundry
 * chain. An overflow in a money column is a financial-integrity failure, and
 * choosing the narrow type to save four bytes would be trading correctness for
 * nothing.
 *
 * OVERLAP IS PREVENTED BY THE DATABASE, NOT BY A VALIDATION QUERY (FR-035).
 * ------------------------------------------------------------------------
 * Two ACTIVE price lists for one brand must not overlap in their effective
 * period. The obvious implementation — SELECT to check, then INSERT — is a
 * lost-update race: two concurrent publishes each see no overlap and both
 * commit, and the tenant now has two active prices for the same day with no
 * defined winner.
 *
 * A PostgreSQL EXCLUDE constraint makes the second writer fail at the engine.
 * Correctness stops depending on application timing (invariant P4, threat T-10).
 *
 * IMMUTABILITY AFTER PUBLISH (FR-035, FR-036).
 * --------------------------------------------
 * A published version is frozen. Superseding creates a NEW row and leaves the
 * prior one byte-identical, so a reprinted nota in Step 5 can always resolve the
 * price that actually applied. Step 4 delivers the immutable, addressable
 * version; proving an ORDER honours it needs an order and is Step 5's
 * obligation (DEC-0031 B).
 */
return new class extends Migration
{
    public function up(): void
    {
        // `btree_gist` lets an EXCLUDE constraint mix an equality test on a UUID
        // with an overlap test on a range. Without it, PostgreSQL can exclude on
        // ranges but cannot also scope the exclusion to one brand.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // FR-034: a price list belongs to a BRAND. Multi-brand operators
            // price by brand, not by tenant.
            $table->uuid('laundry_brand_id');

            $table->string('code');
            $table->string('name');

            // Rupiah only. The column exists so the value is explicit in the
            // data rather than assumed by every reader (Master Source §1.6).
            $table->string('currency')->default('IDR');

            // draft | active | superseded | archived
            $table->string('status')->default('draft');

            $table->date('effective_from');

            // NULL means open-ended: this price list applies until something
            // supersedes it.
            $table->date('effective_until')->nullable();

            $table->boolean('is_default')->default(false);

            // Set when publishing. Both are evidence, so both are server-side.
            $table->timestampTz('published_at')->nullable();
            $table->uuid('published_by_membership_id')->nullable();

            // FR-035: the version chain. Insert-only.
            $table->uuid('supersedes_price_list_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'price_lists_tenant_code_unique');
            $table->index('tenant_id', 'price_lists_tenant_id_index');
            $table->index(['tenant_id', 'laundry_brand_id'], 'price_lists_tenant_brand_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE (invariant P1). A price list may only
        // reference a brand in the SAME tenant — the Step 3 pattern, unchanged.
        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_tenant_brand_foreign
            FOREIGN KEY (tenant_id, laundry_brand_id)
            REFERENCES laundry_brands (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_status_check
            CHECK (status IN ('draft', 'active', 'superseded', 'archived'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_currency_check
            CHECK (currency = 'IDR')
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_effective_window_check
            CHECK (effective_until IS NULL OR effective_until >= effective_from)
        SQL);

        // INVARIANT P4 — no two ACTIVE price lists for one brand may overlap.
        //
        // `daterange(effective_from, effective_until, '[]')` is inclusive at both
        // ends, so two lists that merely touch on the same day DO overlap, which
        // is the intent: on that day both would apply and neither wins.
        //
        // Scoped to status='active' so drafts may be prepared freely and
        // superseded history may sit in the past without tripping it.
        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_no_overlapping_active
            EXCLUDE USING gist (
                laundry_brand_id WITH =,
                daterange(effective_from, effective_until, '[]') WITH &&
            )
            WHERE (status = 'active' AND deleted_at IS NULL)
        SQL);

        // INVARIANT P7 — at most one default active price list per brand.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX price_lists_one_default_per_brand
            ON price_lists (tenant_id, laundry_brand_id)
            WHERE is_default AND status = 'active' AND deleted_at IS NULL
        SQL);

        // --- items ----------------------------------------------------------
        Schema::create('price_list_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('price_list_id');

            // Exactly one of these three is set — see the check constraint below.
            $table->uuid('service_id')->nullable();
            $table->uuid('service_package_id')->nullable();
            $table->uuid('service_addon_id')->nullable();

            // FR-037. INTEGER RUPIAH. See the class docblock.
            $table->bigInteger('amount_rupiah');

            $table->timestamps();

            $table->index('tenant_id', 'price_list_items_tenant_id_index');
            $table->index(['tenant_id', 'price_list_id'], 'price_list_items_tenant_list_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
            ADD CONSTRAINT price_list_items_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
            ADD CONSTRAINT price_list_items_tenant_list_foreign
            FOREIGN KEY (tenant_id, price_list_id)
            REFERENCES price_lists (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // Every priceable target is bound to the same tenant (invariant P8).
        foreach ([
            ['service_id', 'service_catalog', 'service'],
            ['service_package_id', 'service_packages', 'package'],
            ['service_addon_id', 'service_addons', 'addon'],
        ] as [$column, $table, $label]) {
            DB::statement(<<<SQL
                ALTER TABLE price_list_items
                ADD CONSTRAINT price_list_items_tenant_{$label}_foreign
                FOREIGN KEY (tenant_id, {$column})
                REFERENCES {$table} (tenant_id, id)
                ON UPDATE CASCADE ON DELETE RESTRICT
            SQL);
        }

        // INVARIANT P3 — a negative price is not a discount, it is a defect.
        // A discount is a separate concept and belongs to Step 5.
        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
            ADD CONSTRAINT price_list_items_amount_non_negative
            CHECK (amount_rupiah >= 0)
        SQL);

        // EXACTLY ONE target. A row priced against both a service and a package
        // has no defined meaning, and a row priced against nothing is unusable.
        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
            ADD CONSTRAINT price_list_items_exactly_one_target
            CHECK (
                (service_id IS NOT NULL)::int
                + (service_package_id IS NOT NULL)::int
                + (service_addon_id IS NOT NULL)::int
                = 1
            )
        SQL);

        // One price per target per list. Two prices for one service in one list
        // is an ambiguity nothing downstream could resolve.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX price_list_items_one_price_per_service
            ON price_list_items (tenant_id, price_list_id, service_id)
            WHERE service_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX price_list_items_one_price_per_package
            ON price_list_items (tenant_id, price_list_id, service_package_id)
            WHERE service_package_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX price_list_items_one_price_per_addon
            ON price_list_items (tenant_id, price_list_id, service_addon_id)
            WHERE service_addon_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
        // btree_gist is deliberately NOT dropped: another migration or extension
        // may rely on it, and dropping a shared extension on rollback would
        // break things this migration never created.
    }
};
