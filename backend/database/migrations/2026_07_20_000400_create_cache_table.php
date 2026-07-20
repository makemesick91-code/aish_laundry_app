<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRAMEWORK TABLE — Laravel's database cache store and lock store.
 *
 * The string primary key is mandated by the cache driver contract.
 *
 * Development uses Redis for cache and locks (Rule 06). When any cache is used
 * for tenant data, EVERY cache key must carry a tenant dimension: a tenant-less
 * cache key is a cross-tenant leak waiting to happen (Rule 02, Rule 06 rule 13).
 * Nothing financially or legally significant ever lives only in a cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
