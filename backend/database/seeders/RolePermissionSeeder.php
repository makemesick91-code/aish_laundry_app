<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Authorization\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Projects the canonical PermissionRegistry into the database catalogues.
 *
 * DIRECTION OF TRUTH IS ONE-WAY AND DELIBERATE
 * --------------------------------------------
 * DEC-0025 §1: `roles` and `permissions` are platform-managed catalogues whose
 * contents "are defined by the platform, not by tenants, and are seeded and
 * versioned as part of the application". This seeder therefore READS the
 * registry and WRITES the tables. It never reads the tables to discover what a
 * role is. If the two disagree, the registry is right and the database is stale.
 *
 * IDEMPOTENT BY KEY
 * -----------------
 * Roles and permissions are matched on their natural key, so re-running this
 * seeder updates descriptions and re-syncs grants without orphaning an existing
 * `membership_role` row that points at a role id. Re-seeding must never silently
 * revoke a tenant's assignments by recreating the role with a new id.
 *
 * WHAT THIS SEEDER DOES NOT DO
 * ----------------------------
 * It assigns nothing to anybody. A role row is "a name for a capability set,
 * never an entitlement" (DEC-0025 §3); granting one to a membership is a
 * tenant-scoped act that happens elsewhere, under audit.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = $this->syncPermissions();
        $this->syncRoles($permissionIds);
    }

    /**
     * @return array<string, string> permission key => permission id
     */
    private function syncPermissions(): array
    {
        $now = now();
        $ids = [];

        foreach (PermissionRegistry::permissions() as $key => $definition) {
            $existing = DB::table('permissions')->where('key', $key)->first();

            if ($existing !== null) {
                DB::table('permissions')
                    ->where('id', $existing->id)
                    ->update([
                        'description' => $definition['description'],
                        'updated_at' => $now,
                    ]);

                $ids[$key] = (string) $existing->id;

                continue;
            }

            $id = (string) Str::uuid();

            DB::table('permissions')->insert([
                'id' => $id,
                'key' => $key,
                'description' => $definition['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $ids[$key] = $id;
        }

        return $ids;
    }

    /**
     * @param  array<string, string>  $permissionIds
     */
    private function syncRoles(array $permissionIds): void
    {
        $now = now();

        foreach (PermissionRegistry::roles() as $roleKey => $definition) {
            $existing = DB::table('roles')->where('key', $roleKey)->first();

            if ($existing !== null) {
                $roleId = (string) $existing->id;

                DB::table('roles')
                    ->where('id', $roleId)
                    ->update([
                        'description' => $definition['description'],
                        'updated_at' => $now,
                    ]);
            } else {
                $roleId = (string) Str::uuid();

                DB::table('roles')->insert([
                    'id' => $roleId,
                    'key' => $roleKey,
                    'description' => $definition['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->syncRoleGrants($roleId, $definition['permissions'], $permissionIds, $now);
        }
    }

    /**
     * Re-syncs one role's grants to exactly what the registry states.
     *
     * A permission REMOVED from the registry is removed from the role here, so
     * narrowing a role in the registry actually narrows it in the running system
     * rather than leaving a stale grant behind. Narrowing authorization never
     * needs a new decision (DEC-0025 supersession policy).
     *
     * @param  list<string>  $wantedKeys
     * @param  array<string, string>  $permissionIds
     */
    private function syncRoleGrants(string $roleId, array $wantedKeys, array $permissionIds, mixed $now): void
    {
        $wantedIds = [];

        foreach ($wantedKeys as $key) {
            if (! isset($permissionIds[$key])) {
                // A role granting a permission the registry does not define is a
                // registry defect, not something to paper over at seed time.
                throw new \RuntimeException(
                    "Role grant references an unknown permission key: {$key}"
                );
            }

            $wantedIds[] = $permissionIds[$key];
        }

        $currentIds = DB::table('role_permission')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $toAdd = array_diff($wantedIds, $currentIds);
        $toRemove = array_diff($currentIds, $wantedIds);

        if ($toRemove !== []) {
            DB::table('role_permission')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', array_values($toRemove))
                ->delete();
        }

        foreach ($toAdd as $permissionId) {
            DB::table('role_permission')->insert([
                'id' => (string) Str::uuid(),
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
