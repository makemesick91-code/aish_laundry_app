<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EXPLICIT REVOCATION for API session credentials.
 *
 * Sanctum revokes a token by DELETING its row. Deletion cannot distinguish
 * "this session was deliberately revoked" from "this credential never existed",
 * so both produce the same UNAUTHENTICATED. That is a worse experience for a
 * legitimate user (who is told nothing about why they were signed out) and a
 * worse audit trail (the revocation leaves no record on the credential itself).
 *
 * Recording revocation rather than deleting mirrors how `device_sessions`
 * already works, and lets the API answer SESSION_REVOKED distinctly from
 * SESSION_EXPIRED (Rule 29 hard rule 9 — errors explain what happened and what
 * to do next).
 *
 * NOTE ON THE TOKEN COLUMN — unchanged and deliberately so. Sanctum stores only
 * `hash('sha256', $plainText)`. No plaintext credential is stored anywhere by
 * this application (Rule 03, hard rule 6).
 *
 * `device_identifier` here is an UNTRUSTED HINT recorded for revocation and
 * "which devices are signed in", never an authorization signal (Rule 31, hard
 * rule 12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_identifier')->nullable()->after('abilities');
            $table->string('device_name')->nullable()->after('device_identifier');
            $table->string('platform')->nullable()->after('device_name');
            $table->string('last_used_ip', 45)->nullable()->after('platform');

            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->uuid('revoked_by_user_id')->nullable()->after('revoked_at');

            $table->index('revoked_at', 'personal_access_tokens_revoked_at_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE personal_access_tokens
            ADD CONSTRAINT personal_access_tokens_revoked_by_user_id_foreign
            FOREIGN KEY (revoked_by_user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE SET NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_revoked_by_user_id_foreign');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_revoked_at_index');
            $table->dropColumn([
                'device_identifier',
                'device_name',
                'platform',
                'last_used_ip',
                'revoked_at',
                'revoked_by_user_id',
            ]);
        });
    }
};
