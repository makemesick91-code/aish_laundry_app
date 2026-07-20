<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERMISSIONS are a PLATFORM-DEFINED catalogue and carry no `tenant_id`, for
 * the same reason as `roles`: a permission is a definition, not tenant data.
 *
 * A permission is checked SERVER-SIDE at the API boundary on every request.
 * Client-side hiding of a control is a UX affordance and is never an access
 * control (Rule 03, hard rules 2 and 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Technical identifier, e.g. `outlet.view`. Least privilege is the
            // default: a permission grants exactly one capability (Rule 03).
            $table->string('key')->unique();

            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
