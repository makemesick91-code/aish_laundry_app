<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A published price list may not be deleted — enforced at the ENGINE (NEW-01).
 *
 * WHAT THE CLOSURE REVIEW PROVED
 * ------------------------------
 * The previous remediation put a `static::deleting` model event on `PriceList`.
 * Eloquent does not fire model events for query-builder deletes, so:
 *
 *     PriceList::where('code', 'PL-X')->delete();
 *
 * soft-deleted a PUBLISHED list and it left the default scope. Confirmed against
 * the live database: `ACCEPTED rows=1`, `still live: NO`.
 *
 * The test that was supposed to cover this exercised `$list->delete()` only,
 * while its docblock claimed the list "cannot be deleted, softly or otherwise".
 * That is the same shape as SEC-12 — a guard documented as absolute, with an
 * unenumerated bypass, and a green test proving the narrower case. Twice in one
 * step is a pattern, not bad luck: a model-level guard describes what one code
 * path does, never what the table permits.
 *
 * WHY THIS MATTERS MORE THAN IT LOOKS
 * -----------------------------------
 * FR-036 requires an order's captured price to be immune to later change, and a
 * historical order resolves its price through this record. A published list that
 * quietly leaves the default scope does not raise an error anywhere — the next
 * lookup simply finds nothing, and "the price is missing" surfaces as a bug in
 * whatever code was reading it. Rule 04 makes a financial-integrity failure an
 * automatic NO-GO, and this is the row that failure would run through.
 *
 * `customer_consents` got engine-level triggers for exactly this reason. The
 * price list is the financial-integrity anchor and had only a model event.
 *
 * ENABLE ALWAYS, not the default. `CREATE TRIGGER` yields `ENABLE ORIGIN`, which
 * `session_replication_role = 'replica'` skips — the SEC-12 bypass. Applying
 * that lesson here rather than rediscovering it.
 *
 * WHAT IS NOT CLAIMED. A principal that may drop or disable the trigger is not
 * defended against; see `docs/deployment/DATABASE_ROLE_PREREQUISITE.md`. A DRAFT
 * remains freely deletable — an unpublished list has never priced anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION price_lists_refuse_published_removal()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                -- A hard DELETE of anything that was ever published.
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status <> 'draft' THEN
                        RAISE EXCEPTION
                            'A published price list is never deleted (FR-035, FR-036). '
                            'A historical order resolves its captured price through this record.'
                            USING ERRCODE = 'restrict_violation';
                    END IF;

                    RETURN OLD;
                END IF;

                -- A SOFT delete is an UPDATE that sets `deleted_at`. Only the
                -- TRANSITION into deleted is refused: an ordinary lifecycle
                -- update on an already-soft-deleted draft must still work.
                IF OLD.status <> 'draft'
                   AND NEW.deleted_at IS NOT NULL
                   AND OLD.deleted_at IS NULL THEN
                    RAISE EXCEPTION
                        'A published price list is never soft-deleted (FR-035, FR-036). '
                        'Archive it by setting its status instead.'
                        USING ERRCODE = 'restrict_violation';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER price_lists_refuse_published_delete
            BEFORE DELETE ON price_lists
            FOR EACH ROW
            EXECUTE FUNCTION price_lists_refuse_published_removal()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER price_lists_refuse_published_soft_delete
            BEFORE UPDATE ON price_lists
            FOR EACH ROW
            EXECUTE FUNCTION price_lists_refuse_published_removal()
        SQL);

        foreach ([
            'price_lists_refuse_published_delete',
            'price_lists_refuse_published_soft_delete',
        ] as $trigger) {
            DB::statement("ALTER TABLE price_lists ENABLE ALWAYS TRIGGER {$trigger}");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS price_lists_refuse_published_soft_delete ON price_lists');
        DB::statement('DROP TRIGGER IF EXISTS price_lists_refuse_published_delete ON price_lists');
        DB::statement('DROP FUNCTION IF EXISTS price_lists_refuse_published_removal()');
    }
};
