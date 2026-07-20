<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API tokens (Laravel Sanctum), adapted to this product's UUID keys.
 *
 * `token` stores a HASH. The plaintext token is returned to the client exactly
 * once, at issue time, and is never stored, never logged, and never committed
 * (Rule 03, hard rules 6 and 20).
 *
 * `expires_at` is present because a non-expiring credential is a credential
 * nobody can revoke by waiting. Revocation is by deletion of the row, which is
 * immediate and server-side (Rule 03, hard rule 8).
 *
 * This table is NOT the public tracking token store. A tracking token is a
 * different SECRET-class artefact owned by Step 7 and does not exist here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
