<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROLES are a PLATFORM-DEFINED catalogue and carry no `tenant_id`.
 *
 * This is a deliberate decision, not an oversight of Rule 02 hard rule 7. A
 * role is a definition ("kasir", "manager outlet"), not tenant business data.
 * The tenant-scoped fact is which MEMBERSHIP holds which role, and that lives
 * in `membership_role`, which is tenant-scoped and constrained accordingly.
 *
 * Consequence: no tenant can read, enumerate, or infer anything about another
 * tenant from this table, because it contains nothing tenant-specific.
 *
 * If a later Step introduces tenant-defined custom roles, that requires a
 * decision record and a `tenant_id` column — it is not added silently here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Stable machine identifier. English is permitted here because this
            // is a TECHNICAL IDENTIFIER; what a user reads is its glossary
            // Indonesian label, which is a presentation concern (Rule 30).
            $table->string('key')->unique();

            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
