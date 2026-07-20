<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROLE -> PERMISSION grants. Both sides are platform-defined catalogues, so
 * this pivot carries no `tenant_id` and cannot express anything tenant-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('role_id')
                ->constrained('roles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignUuid('permission_id')
                ->constrained('permissions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            // A grant exists once. A duplicate row would make revocation
            // ambiguous, and an ambiguous revocation is a failure to revoke.
            $table->unique(['role_id', 'permission_id'], 'role_permission_unique');

            $table->index('permission_id', 'role_permission_permission_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
