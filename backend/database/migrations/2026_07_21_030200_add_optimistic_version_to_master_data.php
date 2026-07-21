<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIMISTIC-CONCURRENCY COUNTERS ON THE REMAINING STEP 4 MASTER DATA (T-12).
 *
 * The outlet tables got theirs in `030000`. This adds the same counter to the
 * customer and service-catalogue tables so every mutable Step 4 aggregate
 * detects a stale write the same way, rather than some of them detecting it and
 * the rest silently applying last-write-wins.
 *
 * WHY A COUNTER AND NOT `updated_at`
 * ----------------------------------
 * `updated_at` was the original design and it is wrong. Laravel's `timestamps()`
 * yields a SECOND-PRECISION column in PostgreSQL, so two edits inside the same
 * second carry an identical value — and two edits inside the same second are
 * exactly the collision a stale-write check exists to catch. The failure was
 * silent: a test passed whenever the two writes happened to straddle a second
 * boundary. See `SharedKernel\Concerns\HasOptimisticVersion`.
 *
 * NOT ADDED TO `customer_consents`, AND THE OMISSION IS THE POINT.
 * Consent records are APPEND-ONLY (FR-027, FR-028, invariant C5): there is no
 * update path, so there is no stale write to detect. Adding a version counter
 * there would imply a mutation that must never exist.
 *
 * NOT ADDED TO `price_list_items` of a published list either — those are
 * immutable (FR-035). The column exists on the table because a DRAFT item is
 * editable; the model guard is what refuses a published one.
 *
 * Purely additive: a new nullable-free column with a default, no data rewritten,
 * no existing column touched. Rollback drops only what this added.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'customers',
        'customer_addresses',
        'service_categories',
        'service_catalog',
        'service_packages',
        'service_package_items',
        'service_addons',
        'price_lists',
        'price_list_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('version')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('version');
            });
        }
    }
};
