<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `price_lists.supersedes_price_list_id` had no foreign key at all (review N3).
 *
 * Every other relation on this table is bound by a COMPOSITE key carrying
 * `tenant_id`, so a cross-tenant pairing is refused by PostgreSQL rather than by
 * a check somebody has to remember. This one column was the exception: it
 * accepted any UUID, including one that referenced nothing, and including one
 * belonging to another tenant.
 *
 * The service path already asserts tenant and brand before setting it, and no
 * route exposes it, so nothing was reachable. But "the only writer happens to
 * check" is the shape of every constraint that later turns out to be missing —
 * and a supersession chain is exactly the structure a Step 5 price lookup will
 * walk to answer "what did this order actually pay".
 *
 * COMPOSITE, not simple. `(tenant_id, supersedes_price_list_id)` referencing
 * `(tenant_id, id)` makes a cross-tenant supersession structurally impossible;
 * a plain reference to `id` would permit tenant A's list to declare that it
 * supersedes tenant B's, which is a cross-tenant relation the database would
 * happily store (Rule 02, Rule 39 hard rule 5).
 *
 * `ON DELETE RESTRICT`: the superseded list is the one a historical order
 * resolves through, and it may not vanish from under the chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The composite foreign key needs a UNIQUE (tenant_id, id) to target.
        // One ALREADY EXISTS as `price_lists_tenant_id_id_unique`, created with
        // the table for exactly this purpose. This migration originally added a
        // second, identical constraint — and therefore a redundant index on the
        // financial-integrity table. Found by the closure review; the existing
        // constraint is reused instead of duplicated.

        DB::statement(<<<'SQL'
            ALTER TABLE price_lists
            ADD CONSTRAINT price_lists_supersedes_same_tenant_foreign
            FOREIGN KEY (tenant_id, supersedes_price_list_id)
            REFERENCES price_lists (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE price_lists DROP CONSTRAINT IF EXISTS price_lists_supersedes_same_tenant_foreign');
        // The unique constraint the key targets predates this migration and
        // is not ours to remove.
    }
};
