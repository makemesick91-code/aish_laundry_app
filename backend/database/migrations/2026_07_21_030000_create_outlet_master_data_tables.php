<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OUTLET MASTER DATA (FR-041 … FR-047).
 *
 * Extends the Step 3 `outlets` table additively and adds the four satellites the
 * Step 4 architecture justifies: service zones, shifts, printers, and a
 * tenant-level proof policy. No existing column is altered or dropped, so
 * rollback removes only what this migration created.
 *
 * WHY OPERATING HOURS ARE A COLUMN AND SHIFTS ARE A TABLE
 * ------------------------------------------------------
 * They look similar and are not. Operating hours are a fixed seven-row weekly
 * pattern that always exists and is always read whole — a table would add a join
 * to every read and buy nothing, because there is no such thing as "some" of an
 * outlet's opening hours. Shifts are an open-ended set the tenant names, counts,
 * and references individually (FR-044 anchors shift closing in Step 5), so each
 * one needs its own addressable, tenant-scoped identity.
 *
 * WALL-CLOCK LOCAL TIME, NOT UTC (FR-041, FR-047, Rule 43)
 * --------------------------------------------------------
 * Opening, closing and quiet hours are stored as LOCAL WALL-CLOCK strings
 * (`HH:MM`) interpreted against `outlets.timezone`. They are deliberately NOT
 * stored as UTC instants.
 *
 * An outlet that opens at 08.00 opens at 08.00 — before a daylight-saving change
 * and after one, before the tenant corrects a mis-set timezone and after. Storing
 * 01:00Z instead would silently move the opening time the moment the offset
 * changed, and Rule 43 is explicit that timezone conversion belongs to the
 * application layer and never inside the schema.
 *
 * This is the opposite discipline from `timestamps`, which ARE instants and ARE
 * stored in UTC. A wall-clock time and an instant are different types of thing;
 * conflating them is how "open at 08.00" becomes "open at 15.00" in Jayapura.
 *
 * NO `receipt`, `nota`, OR `struk` ANYWHERE (DEC-0030)
 * ----------------------------------------------------
 * FR-045 authorises printer CONFIGURATION as outlet master data. The document a
 * printer prints is FR-052 in Step 5 and its tokens remain forbidden. This
 * migration therefore configures a device, and names no document.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // FR-041, FR-042, FR-047 — additive columns on the existing outlets.
        // ------------------------------------------------------------------
        Schema::table('outlets', function (Blueprint $table) {
            // FR-041. Seven weekday entries of local wall-clock open/close.
            // `jsonb` rather than seven column pairs: the shape is read and
            // written whole, and a fixed-column layout would need a migration
            // every time a tenant wanted a second daily window.
            $table->jsonb('operating_hours')->nullable();

            // FR-042. Capacity is DESCRIPTIVE master data in Step 4: nothing
            // schedules against it, because scheduling is production operations
            // (Step 6). Recorded now so Step 6 inherits a configured value
            // rather than inventing one.
            $table->integer('daily_capacity_kg')->nullable();
            $table->integer('daily_capacity_orders')->nullable();

            // FR-047. THE CANONICAL DEFAULT IS 20.00–08.00 OUTLET LOCAL TIME
            // (Rule 08 hard rule 6, Rule 10). Set as a column default so an
            // outlet created without an opinion is quiet by default — a
            // messaging window that defaults to "always permitted" is the
            // failure mode this requirement exists to prevent.
            $table->string('quiet_hours_start', 5)->default('20:00');
            $table->string('quiet_hours_end', 5)->default('08:00');

            $table->string('contact_phone')->nullable();
            $table->string('address_line')->nullable();

            // Active/inactive is operational state; soft delete is archival.
            // They are separate because a temporarily closed outlet is not an
            // archived one, and conflating them loses that distinction.
            $table->boolean('is_active')->default(true);

            // OPTIMISTIC-CONCURRENCY COUNTER (threat T-12).
            //
            // Deliberately NOT `updated_at`. Laravel's `timestamps()` produces a
            // SECOND-PRECISION column here, so two edits inside the same second
            // are indistinguishable by timestamp — and two edits inside the same
            // second are exactly the conflict this guards against. An integer
            // that increments on every write cannot collide and does not depend
            // on clock precision.
            $table->unsignedBigInteger('version')->default(1);
        });

        // Wall-clock format is enforced at the engine, so a value that is not a
        // time cannot be stored by ANY writer — including a future migration or
        // a console command that bypasses the application (Rule 18 hard rule 2).
        DB::statement(<<<'SQL'
            ALTER TABLE outlets
            ADD CONSTRAINT outlets_quiet_hours_format_check
            CHECK (
                quiet_hours_start ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$'
                AND quiet_hours_end ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$'
            )
        SQL);

        // A capacity of zero means "closed", which `is_active` already says.
        // A negative capacity has no meaning at all.
        DB::statement(<<<'SQL'
            ALTER TABLE outlets
            ADD CONSTRAINT outlets_capacity_non_negative_check
            CHECK (
                (daily_capacity_kg IS NULL OR daily_capacity_kg >= 0)
                AND (daily_capacity_orders IS NULL OR daily_capacity_orders >= 0)
            )
        SQL);

        // The weekly pattern is an OBJECT keyed by weekday, never an array.
        // An array would make "Wednesday" a position, and a reordering bug would
        // silently move an outlet's opening hours to a different day.
        DB::statement(<<<'SQL'
            ALTER TABLE outlets
            ADD CONSTRAINT outlets_operating_hours_shape_check
            CHECK (operating_hours IS NULL OR jsonb_typeof(operating_hours) = 'object')
        SQL);

        // ------------------------------------------------------------------
        // FR-043 — service zones. COVERAGE DEFINITION ONLY.
        //
        // A zone says "we serve here". It does not route, sequence, estimate, or
        // assign: routing is Step 8, and Rule 09 hard rule 1 forbids any claim of
        // optimisation this product does not implement.
        // ------------------------------------------------------------------
        Schema::create('outlet_service_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');

            $table->string('code');
            $table->string('name');
            $table->string('description')->nullable();

            // Coverage is expressed as postal codes and area names — deliberately
            // NOT as a polygon. A polygon implies geometric containment tests the
            // product does not perform, and would read as a routing capability.
            $table->jsonb('postal_codes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);

            // Optimistic-concurrency counter — see the note on outlets.version.
            $table->unsignedBigInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'outlet_service_zones_tenant_id_index');
            $table->index(['tenant_id', 'outlet_id'], 'outlet_service_zones_tenant_outlet_index');
        });

        // ------------------------------------------------------------------
        // FR-044 — shift definitions. DEFINITIONS ONLY.
        //
        // Shift CLOSING, expected-versus-actual cash, and variance are Step 5
        // (Rule 04 hard rule 10). This table gives Step 5 a shift to close.
        // ------------------------------------------------------------------
        Schema::create('outlet_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');

            $table->string('code');
            $table->string('name');

            // Local wall-clock, same discipline as quiet hours above.
            $table->string('starts_at', 5);
            $table->string('ends_at', 5);

            // A shift that ends before it starts crosses midnight. That is
            // legitimate for a laundry and is recorded explicitly rather than
            // inferred, so a reader never has to guess which of the two a
            // 22:00–06:00 row means.
            $table->boolean('crosses_midnight')->default(false);

            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);

            // Optimistic-concurrency counter — see the note on outlets.version.
            $table->unsignedBigInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'outlet_shifts_tenant_id_index');
            $table->index(['tenant_id', 'outlet_id'], 'outlet_shifts_tenant_outlet_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE outlet_shifts
            ADD CONSTRAINT outlet_shifts_time_format_check
            CHECK (
                starts_at ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$'
                AND ends_at ~ '^([01][0-9]|2[0-3]):[0-5][0-9]$'
            )
        SQL);

        // The flag must agree with the times, or it is worse than absent.
        DB::statement(<<<'SQL'
            ALTER TABLE outlet_shifts
            ADD CONSTRAINT outlet_shifts_midnight_flag_agrees_check
            CHECK (
                (crosses_midnight AND ends_at <= starts_at)
                OR (NOT crosses_midnight AND ends_at > starts_at)
            )
        SQL);

        // ------------------------------------------------------------------
        // FR-045 — printer configuration. A DEVICE, NOT A DOCUMENT.
        // ------------------------------------------------------------------
        Schema::create('outlet_printers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');

            $table->string('code');
            $table->string('name');

            // thermal_58mm | thermal_80mm | label
            $table->string('device_kind');

            // bluetooth | usb | network
            $table->string('connection_kind');

            // A device identifier, never a credential. Rule 03 hard rule 10: no
            // secret is stored here, and a printer needing authentication would
            // read it from the environment, not from this row.
            $table->string('device_identifier')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Optimistic-concurrency counter — see the note on outlets.version.
            $table->unsignedBigInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'outlet_printers_tenant_id_index');
            $table->index(['tenant_id', 'outlet_id'], 'outlet_printers_tenant_outlet_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE outlet_printers
            ADD CONSTRAINT outlet_printers_device_kind_check
            CHECK (device_kind IN ('thermal_58mm', 'thermal_80mm', 'label'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE outlet_printers
            ADD CONSTRAINT outlet_printers_connection_kind_check
            CHECK (connection_kind IN ('bluetooth', 'usb', 'network'))
        SQL);

        // At most one default printer per outlet. Two defaults is an ambiguity
        // nothing downstream could resolve.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX outlet_printers_one_default_per_outlet
            ON outlet_printers (tenant_id, outlet_id)
            WHERE is_default AND is_active AND deleted_at IS NULL
        SQL);

        // ------------------------------------------------------------------
        // Shared structure for the three outlet satellites (invariants O3, O4).
        //
        // The composite foreign key is the point: PostgreSQL rejects a satellite
        // whose `tenant_id` disagrees with its outlet's, so a cross-tenant
        // pairing is structurally impossible rather than remembered by a
        // developer (Rule 02 hard rule 8, threat T-13).
        // ------------------------------------------------------------------
        foreach (['outlet_service_zones', 'outlet_shifts', 'outlet_printers'] as $table) {
            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD CONSTRAINT {$table}_tenant_id_foreign
                FOREIGN KEY (tenant_id) REFERENCES tenants (id)
                ON UPDATE CASCADE ON DELETE RESTRICT
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD CONSTRAINT {$table}_tenant_outlet_foreign
                FOREIGN KEY (tenant_id, outlet_id)
                REFERENCES outlets (tenant_id, id)
                ON UPDATE CASCADE ON DELETE RESTRICT
            SQL);

            DB::statement(<<<SQL
                ALTER TABLE {$table}
                ADD CONSTRAINT {$table}_tenant_id_id_unique UNIQUE (tenant_id, id)
            SQL);

            // INVARIANT O4 — code unique within the OUTLET, never globally and
            // never merely within the tenant. Two outlets of one tenant may both
            // have a zone "A"; that is normal, not a collision.
            //
            // Partial on `deleted_at IS NULL` so an archived row does not hold
            // its code hostage forever.
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX {$table}_outlet_code_unique
                ON {$table} (tenant_id, outlet_id, code)
                WHERE deleted_at IS NULL
            SQL);
        }

        // ------------------------------------------------------------------
        // FR-046 — tenant proof policy. CONFIGURATION ONLY.
        //
        // Capturing a proof at a custody transfer is Step 8. This table records
        // WHICH proofs the tenant requires, so Step 8 inherits a configured
        // policy instead of defaulting to none.
        //
        // Rule 09 hard rule 2: SOME proof is always required. The check
        // constraint below makes "no proof at all" unrepresentable — a policy
        // row that required nothing would be worse than no policy, because it
        // would look like a deliberate decision.
        // ------------------------------------------------------------------
        Schema::create('tenant_proof_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->boolean('pickup_requires_photo')->default(false);
            $table->boolean('pickup_requires_signature')->default(false);
            $table->boolean('pickup_requires_recipient_name')->default(true);
            $table->boolean('pickup_requires_otp')->default(false);

            $table->boolean('delivery_requires_photo')->default(false);
            $table->boolean('delivery_requires_signature')->default(false);
            $table->boolean('delivery_requires_recipient_name')->default(true);
            $table->boolean('delivery_requires_otp')->default(false);

            // Optimistic-concurrency counter — see the note on outlets.version.
            $table->unsignedBigInteger('version')->default(1);

            $table->timestamps();

            // Exactly one policy per tenant. A second row would mean two answers
            // to one question.
            $table->unique('tenant_id', 'tenant_proof_policies_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_proof_policies
            ADD CONSTRAINT tenant_proof_policies_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // RULE 09 HARD RULE 2, ENFORCED BY THE ENGINE.
        // A parcel does not silently change hands.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_proof_policies
            ADD CONSTRAINT tenant_proof_policies_pickup_requires_something
            CHECK (
                pickup_requires_photo
                OR pickup_requires_signature
                OR pickup_requires_recipient_name
                OR pickup_requires_otp
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_proof_policies
            ADD CONSTRAINT tenant_proof_policies_delivery_requires_something
            CHECK (
                delivery_requires_photo
                OR delivery_requires_signature
                OR delivery_requires_recipient_name
                OR delivery_requires_otp
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_proof_policies');
        Schema::dropIfExists('outlet_printers');
        Schema::dropIfExists('outlet_shifts');
        Schema::dropIfExists('outlet_service_zones');

        DB::statement('ALTER TABLE outlets DROP CONSTRAINT IF EXISTS outlets_operating_hours_shape_check');
        DB::statement('ALTER TABLE outlets DROP CONSTRAINT IF EXISTS outlets_capacity_non_negative_check');
        DB::statement('ALTER TABLE outlets DROP CONSTRAINT IF EXISTS outlets_quiet_hours_format_check');

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn([
                'operating_hours',
                'daily_capacity_kg',
                'daily_capacity_orders',
                'quiet_hours_start',
                'quiet_hours_end',
                'contact_phone',
                'address_line',
                'is_active',
                'version',
            ]);
        });
    }
};
