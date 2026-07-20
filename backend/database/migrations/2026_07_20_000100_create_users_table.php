<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A user account is an IDENTITY. It is deliberately tenant-agnostic and carries
 * NO tenant_id: one user may join multiple tenants (Rule 02, hard rule 1), and
 * a user account by itself is never authorization (Rule 02, hard rule 9).
 * Authorization derives from `memberships`.
 *
 * A `users` row is NOT a customer. A customer is a tenant-scoped business record
 * owned by a later Step and does not exist here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');

            // Either identifier may be absent — the product authenticates by
            // phone + OTP as well as by credential — but at least one must be
            // present, and each must be globally unique when present.
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Modern, salted, deliberately slow hash (Rule 03, hard rule 5).
            // Nullable because an OTP-only account has no password.
            $table->string('password')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // At least one identifier must exist. Enforced by the database so that
        // no code path — API, job, import, backfill — can create an account
        // that cannot be authenticated (Rule 18, hard rule 2).
        DB::statement(<<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_identifier_present_check
            CHECK (email IS NOT NULL OR phone IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
