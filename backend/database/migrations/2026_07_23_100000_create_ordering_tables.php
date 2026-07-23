<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ORDER INTAKE AND ORDER LINES (FR-048 … FR-060; FR-036 end-to-end).
 *
 * MONEY IS INTEGER RUPIAH — THE HARD GATE (Rule 04, FR-051).
 * ---------------------------------------------------------
 * Every money column is `bigInteger` named `*_rupiah`. Not `decimal`, not
 * `numeric`, not `float`, not `double`. The Step 4 price list stored
 * `amount_rupiah` this way and an order is where those prices are first charged;
 * a float column here would be inherited by every payment, reversal, and
 * reconciliation Step 5 builds on top. `bigInteger` rather than `integer`: a
 * chain's daily takings exceed the 32-bit ceiling, and an overflow in a money
 * column is a financial-integrity failure.
 *
 * WEIGHT IS INTEGER TOO. A kiloan line carries a fractional weight (2.5 kg), and
 * a float weight multiplied by a price is exactly the fractional-Rupiah path
 * Rule 04 forbids. `quantity_milli` is the quantity in THOUSANDTHS — 2.5 kg is
 * 2500, one piece is 1000 — so weight, count, and every intermediate stay integer
 * and the single rounding point (RupiahRounding) is the only place a fraction is
 * ever resolved.
 *
 * THE HISTORICAL-PRICE SNAPSHOT (FR-036, invariant 11).
 * -----------------------------------------------------
 * A line captures `unit_price_rupiah` at intake and records which price-list
 * version it came from. A later price-list supersession creates a NEW version
 * (Step 4 made published versions immutable and insert-only) and never touches
 * this row, so a reprinted nota always resolves the price that actually applied.
 * Step 4 delivered the immutable version; proving an ORDER honours it is Step 5's
 * obligation (DEC-0031 B), and this snapshot is where that proof lives.
 *
 * TENANT ISOLATION IS STRUCTURAL, NOT A VALIDATION QUERY (Rule 48, invariant P1).
 * ------------------------------------------------------------------------------
 * Every reference an order makes — to its outlet, its customer, a priced service,
 * a price list — is a COMPOSITE foreign key `(tenant_id, x_id)` targeting the
 * parent's `(tenant_id, id)`. A row in tenant A cannot reference a row in tenant B
 * because the pair does not exist in the parent. The application scope is defence
 * in depth; the engine is the guarantee.
 *
 * THE TOTAL IS AUTHORITATIVE AT THE ENGINE (FR-051).
 * --------------------------------------------------
 * `total_rupiah = subtotal_rupiah - discount_rupiah` is a CHECK constraint, and
 * the discount may not exceed the subtotal. A client-supplied total that
 * disagreed with the parts would be rejected by the database, not merely by a
 * validator that a future code path might forget to call.
 */
return new class extends Migration
{
    /** The fifteen canonical order statuses (Rule 19). Step 5 only performs the
     *  intake transitions (DRAFT → RECEIVED, → CANCELLED); the production stages
     *  are Step 6. The COLUMN DOMAIN is the full canonical set — a status value
     *  outside it is a defect regardless of which step writes it. */
    private const ORDER_STATUSES = [
        'DRAFT', 'RECEIVED', 'AWAITING_PROCESS', 'SORTING', 'WASHING', 'DRYING',
        'FINISHING', 'QUALITY_CONTROL', 'REWORK', 'READY_FOR_PICKUP',
        'SCHEDULED_FOR_DELIVERY', 'OUT_FOR_DELIVERY', 'COMPLETED', 'CANCELLED',
        'ISSUE',
    ];

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // An order is taken AT an outlet, FOR a customer. Both are Step 4
            // master data in the same tenant (composite FKs below).
            $table->uuid('outlet_id');
            $table->uuid('customer_id');

            // FR-053: a human-usable order number that grants no access. Unique
            // per tenant, never the primary key, never an authorisation token.
            $table->string('order_number');

            // FR-059 / FR-062: the idempotency key. The client generates it once
            // and reuses it on every retry; the UNIQUE (tenant_id, client_reference)
            // constraint makes a replay a no-op instead of a second order.
            $table->string('client_reference');

            $table->string('status')->default('DRAFT');
            $table->string('currency')->default('IDR');

            // Server-authoritative money (FR-051). subtotal = Σ line subtotals;
            // total = subtotal − order-level discount. Both enforced by CHECK.
            $table->bigInteger('subtotal_rupiah')->default(0);
            $table->bigInteger('discount_rupiah')->default(0);
            $table->bigInteger('total_rupiah')->default(0);

            // FR-055: instructions visible to production before a stage.
            $table->text('special_instructions')->nullable();

            // Set on DRAFT → RECEIVED. Evidence, so server-side.
            $table->timestampTz('placed_at')->nullable();
            $table->uuid('placed_by_membership_id')->nullable();

            // FR-058: cancellation carries a reason and an actor.
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('cancelled_by_membership_id')->nullable();

            // FR-060: audit fields.
            $table->uuid('created_by_membership_id')->nullable();
            $table->uuid('updated_by_membership_id')->nullable();

            // Stale-write detection (HasOptimisticVersion). Server-owned.
            $table->integer('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'order_number'], 'orders_tenant_number_unique');
            $table->unique(['tenant_id', 'client_reference'], 'orders_tenant_client_ref_unique');
            $table->index('tenant_id', 'orders_tenant_id_index');
            $table->index(['tenant_id', 'outlet_id'], 'orders_tenant_outlet_index');
            $table->index(['tenant_id', 'customer_id'], 'orders_tenant_customer_index');
            $table->index(['tenant_id', 'status'], 'orders_tenant_status_index');
            $table->index(['tenant_id', 'created_at'], 'orders_tenant_created_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // Self composite-unique so order_lines can bind to (tenant_id, order_id).
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);

        // STRUCTURAL TENANT GUARANTEE (invariant P1): outlet and customer must be
        // in the SAME tenant as the order.
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_tenant_outlet_foreign
            FOREIGN KEY (tenant_id, outlet_id)
            REFERENCES outlets (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_tenant_customer_foreign
            FOREIGN KEY (tenant_id, customer_id)
            REFERENCES customers (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        $statusList = "'" . implode("', '", self::ORDER_STATUSES) . "'";
        DB::statement(<<<SQL
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN ({$statusList}))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_currency_check CHECK (currency = 'IDR')
        SQL);

        // Money invariants at the engine (Rule 04, FR-051).
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_money_non_negative
            CHECK (subtotal_rupiah >= 0 AND discount_rupiah >= 0 AND total_rupiah >= 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_discount_within_subtotal
            CHECK (discount_rupiah <= subtotal_rupiah)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_total_is_subtotal_minus_discount
            CHECK (total_rupiah = subtotal_rupiah - discount_rupiah)
        SQL);

        // FR-058: a cancelled order has a timestamp AND a reason; a non-cancelled
        // order has neither dangling. The cancelled state and its evidence move
        // together or not at all.
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_cancellation_consistent
            CHECK (
                (status = 'CANCELLED') = (cancelled_at IS NOT NULL)
                AND (cancelled_at IS NULL OR cancellation_reason IS NOT NULL)
            )
        SQL);

        // --- order lines ----------------------------------------------------
        Schema::create('order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');

            $table->integer('line_number');

            // Exactly one priceable target — see the CHECK below. Mirrors
            // price_list_items so a line prices the same things a list prices.
            $table->uuid('service_id')->nullable();
            $table->uuid('service_package_id')->nullable();
            $table->uuid('service_addon_id')->nullable();

            // Snapshot of the human name at capture, so a reprint reads correctly
            // even if the catalogue entry is later renamed.
            $table->string('service_name');

            // kilogram | piece | package | addon
            $table->string('unit');

            // Quantity in THOUSANDTHS (2.5 kg = 2500, 1 pcs = 1000). Integer, so
            // no float ever enters a money computation.
            $table->bigInteger('quantity_milli');

            // FR-036 SNAPSHOT: the price that applied at intake, and where it came
            // from. Frozen here; a later price-list change never rewrites it.
            $table->bigInteger('unit_price_rupiah');
            $table->uuid('price_list_id')->nullable();
            $table->uuid('price_list_item_id')->nullable();

            $table->bigInteger('discount_rupiah')->default(0);

            // Server-computed = round(unit_price × quantity) − line discount.
            $table->bigInteger('subtotal_rupiah');

            $table->timestamps();

            $table->unique(['tenant_id', 'order_id', 'line_number'], 'order_lines_tenant_order_line_unique');
            $table->index(['tenant_id', 'order_id'], 'order_lines_tenant_order_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // A line cannot outlive its order; deleting the order row (hard delete,
        // e.g. a rollback) takes its lines with it. Orders soft-delete in normal
        // operation, so this cascade is the rollback/admin path only.
        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_tenant_order_foreign
            FOREIGN KEY (tenant_id, order_id)
            REFERENCES orders (tenant_id, id)
            ON UPDATE CASCADE ON DELETE CASCADE
        SQL);

        // Every priceable target is bound to the SAME tenant (invariant P8).
        foreach ([
            ['service_id', 'service_catalog', 'service'],
            ['service_package_id', 'service_packages', 'package'],
            ['service_addon_id', 'service_addons', 'addon'],
        ] as [$column, $parent, $label]) {
            DB::statement(<<<SQL
                ALTER TABLE order_lines
                ADD CONSTRAINT order_lines_tenant_{$label}_foreign
                FOREIGN KEY (tenant_id, {$column})
                REFERENCES {$parent} (tenant_id, id)
                ON UPDATE CASCADE ON DELETE RESTRICT
            SQL);
        }

        // The price list the snapshot came from, in the same tenant. Nullable: a
        // manual price override (FR-039) may not resolve to a list item.
        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_tenant_price_list_foreign
            FOREIGN KEY (tenant_id, price_list_id)
            REFERENCES price_lists (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_exactly_one_target
            CHECK (
                (service_id IS NOT NULL)::int
                + (service_package_id IS NOT NULL)::int
                + (service_addon_id IS NOT NULL)::int
                = 1
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_unit_check
            CHECK (unit IN ('kilogram', 'piece', 'package', 'addon'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_quantity_positive
            CHECK (quantity_milli > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_money_non_negative
            CHECK (
                unit_price_rupiah >= 0
                AND discount_rupiah >= 0
                AND subtotal_rupiah >= 0
            )
        SQL);

        // A price-list item id may only be present alongside the list it belongs
        // to; a snapshot pointing at an item with no list is unresolvable.
        DB::statement(<<<'SQL'
            ALTER TABLE order_lines
            ADD CONSTRAINT order_lines_item_requires_list
            CHECK (price_list_item_id IS NULL OR price_list_id IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
