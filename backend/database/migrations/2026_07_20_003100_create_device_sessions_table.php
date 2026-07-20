<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEVICE SESSIONS exist so that a SPECIFIC device's access can be revoked
 * without forcing every other device to re-authenticate (Rule 03, hard rule 9).
 *
 * The table is tenant-scoped: a user who belongs to two tenants has separate
 * device sessions per tenant, and revoking one reveals and affects nothing in
 * the other (Rule 02).
 *
 * `device_identifier` is an UNTRUSTED HINT. A device characteristic is NEVER an
 * authorization signal, exactly as a client-supplied tenant identifier is never
 * authorization proof (Rule 31, hard rule 12; Rule 02, hard rule 9). It is
 * stored to make revocation and "which devices are signed in" possible — not to
 * make an access decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');
            $table->uuid('membership_id');

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Opaque, client-generated device handle. Not a hardware serial and
            // not a stable advertising identifier.
            $table->string('device_identifier');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Revocation is explicit and recorded, never a silent row deletion,
            // so that "this device was revoked, by whom, and when" stays
            // answerable (Rule 03, Rule 17).
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by_user_id')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'user_id', 'device_identifier'],
                'device_sessions_tenant_user_device_unique'
            );

            $table->index('tenant_id', 'device_sessions_tenant_id_index');
            $table->index(['tenant_id', 'user_id'], 'device_sessions_tenant_user_index');
            $table->index('membership_id', 'device_sessions_membership_id_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE device_sessions
            ADD CONSTRAINT device_sessions_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE.
        // A device session may only reference a membership in the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE device_sessions
            ADD CONSTRAINT device_sessions_tenant_membership_foreign
            FOREIGN KEY (tenant_id, membership_id)
            REFERENCES memberships (tenant_id, id)
            ON UPDATE CASCADE ON DELETE CASCADE
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE device_sessions
            ADD CONSTRAINT device_sessions_revoked_by_user_id_foreign
            FOREIGN KEY (revoked_by_user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE SET NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
