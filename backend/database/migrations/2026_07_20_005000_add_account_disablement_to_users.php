<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ACCOUNT DISABLEMENT, distinct from soft deletion.
 *
 * A soft-deleted user is a removed record. A DISABLED user is a record that
 * still exists, still owns its history, and still appears in audit trails — but
 * authenticates to nothing. Conflating the two would mean the only way to lock
 * an account out is to delete it, which destroys the very attribution an audit
 * trail depends on.
 *
 * Checked at the authentication boundary AND on every authenticated request, so
 * disabling takes effect on the next request rather than when a token expires
 * (the same immediacy principle as membership revocation, DEC-0025 §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('phone_verified_at');
            $table->index('disabled_at', 'users_disabled_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_disabled_at_index');
            $table->dropColumn('disabled_at');
        });
    }
};
