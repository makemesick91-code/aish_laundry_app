<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MEMBERSHIP is the join between a user and a tenant, and it is where
 * authorization comes from. A user account alone grants access to nothing
 * (Rule 02, hard rule 9).
 *
 * NOTE ON THE NAME: `memberships` here means TENANCY membership. It is not a
 * loyalty or commercial membership programme — that is a Step 9+ concept and
 * does not exist.
 *
 * One user may join multiple tenants (hard rule 1); one owner may manage
 * multiple tenants (hard rule 2). A user's presence in tenant A tells tenant B
 * nothing, and records are NEVER merged across tenants because name, email,
 * phone, or device match (hard rule 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Membership lifecycle. A stale membership must be revocable
            // without deleting the historical record it authorised.
            $table->string('status')->default('invited');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // A user joins a given tenant exactly once. Roles are attached to
            // the membership (see `membership_role`), never duplicated by
            // creating a second membership row.
            $table->unique(['tenant_id', 'user_id'], 'memberships_tenant_user_unique');

            // Every tenant-scoped query filters on tenant_id first.
            $table->index('tenant_id', 'memberships_tenant_id_index');
            $table->index('user_id', 'memberships_user_id_index');
        });

        // Status is a closed set. There is no free-text status anywhere in this
        // product (Rule 19, hard rule 2).
        DB::statement(<<<'SQL'
            ALTER TABLE memberships
            ADD CONSTRAINT memberships_status_check
            CHECK (status IN ('invited', 'active', 'suspended', 'revoked'))
        SQL);

        // Referenced by `membership_role` so that a role assignment can never
        // point at a membership in a different tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE memberships
            ADD CONSTRAINT memberships_tenant_id_id_unique UNIQUE (tenant_id, id)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
