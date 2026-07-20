<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRAMEWORK TABLE — Laravel's database session driver.
 *
 * The primary key is the framework's own session identifier string, not a UUID.
 * That shape is mandated by the session driver contract and is not a deviation
 * we are free to make. Development uses the Redis session driver; this table
 * exists so the database driver remains available without a schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
