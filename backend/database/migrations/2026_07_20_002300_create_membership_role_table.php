<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MEMBERSHIP -> ROLE assignment. This is the tenant-scoped half of
 * authorization: a user's roles are a property of their MEMBERSHIP in a
 * specific tenant, never of their user account (Rule 02).
 *
 * A user who is an owner in tenant A and a kasir in tenant B has two
 * memberships and two unrelated role sets. Neither can be reached from the
 * other.
 *
 * `tenant_id` is carried here and bound to the membership by a COMPOSITE
 * foreign key, so a role assignment can never be attached to a membership in a
 * different tenant. As with `outlets`, the guarantee is structural.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_role', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');
            $table->uuid('membership_id');

            $table->foreignUuid('role_id')
                ->constrained('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['membership_id', 'role_id'], 'membership_role_unique');

            $table->index('tenant_id', 'membership_role_tenant_id_index');
            $table->index('role_id', 'membership_role_role_id_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE membership_role
            ADD CONSTRAINT membership_role_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE.
        // A role assignment may only reference a membership in the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE membership_role
            ADD CONSTRAINT membership_role_tenant_membership_foreign
            FOREIGN KEY (tenant_id, membership_id)
            REFERENCES memberships (tenant_id, id)
            ON UPDATE CASCADE ON DELETE CASCADE
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_role');
    }
};
