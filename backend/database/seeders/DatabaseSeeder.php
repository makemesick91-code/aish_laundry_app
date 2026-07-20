<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ORDER MATTERS.
 *
 * The role catalogue must exist before anything can be assigned from it, so
 * RolePermissionSeeder always runs first. It is the platform-managed projection
 * of the PermissionRegistry (DEC-0025 §1) and is required in EVERY environment.
 *
 * DevelopmentDataSeeder is fictional local data and is deliberately skipped in
 * production: seeding invented tenants into a live system would put fabricated
 * businesses next to real ones.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (app()->environment('production')) {
            $this->command?->warn(
                'Lingkungan produksi terdeteksi — data pengembangan fiktif tidak di-seed.'
            );

            return;
        }

        $this->call(DevelopmentDataSeeder::class);
    }
}
