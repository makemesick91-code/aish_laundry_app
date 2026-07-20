<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Explicit builders for Step 3 fixtures.
 *
 * Deliberately NOT model factories. Every relationship here crosses a tenant
 * boundary that the schema constrains, and writing the rows out longhand keeps
 * the tenant binding visible at the call site — which is exactly what an
 * isolation test needs to be able to vary on purpose.
 *
 * Every value produced here is fictional (Rule 23).
 */
trait BuildsTenantScenario
{
    /**
     * Seeds the platform-managed role and permission catalogues.
     *
     * Required before any role assignment: a role that does not exist cannot be
     * granted, and the catalogue is a platform artefact, not test data.
     */
    protected function seedCatalogue(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    protected function makeTenant(string $slug = 'tenant-uji-fiktif', string $name = 'Tenant Uji Fiktif'): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug.'-'.Str::lower(Str::random(6)),
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    protected function makeBrand(Tenant $tenant, string $name = 'Merek Uji Fiktif'): LaundryBrand
    {
        return LaundryBrand::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => 'merek-'.Str::lower(Str::random(8)),
        ]);
    }

    protected function makeOutlet(Tenant $tenant, ?LaundryBrand $brand = null, string $name = 'Outlet Uji Fiktif'): Outlet
    {
        $brand ??= $this->makeBrand($tenant);

        return Outlet::query()->create([
            'tenant_id' => $tenant->id,
            'laundry_brand_id' => $brand->id,
            'name' => $name,
            'code' => 'UJI-'.Str::upper(Str::random(5)),
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    /**
     * A fictional user with a known password.
     *
     * The password is a test constant, not a seeded credential: it never reaches
     * a database anyone can log into, because the test database is torn down.
     */
    protected function makeUser(string $password = 'placeholder-KataSandiUji12345', ?string $email = null): User
    {
        return User::query()->create([
            'name' => 'Pengguna Uji Fiktif',
            'email' => $email ?? 'uji.'.Str::lower(Str::random(10)).'@contoh.invalid',
            'phone' => $this->fictionalPhone(),
            'password' => $password,
        ]);
    }

    /**
     * A structurally fabricated telephone number that is also UNIQUE.
     *
     * `users.phone` is unique, so a fixed placeholder collides the moment a test
     * builds a second user. The number stays recognisably fake by keeping the
     * subscriber body all zeros apart from a leading counter digit — it can
     * never reach a real subscriber — while the counter keeps it distinct.
     */
    private function fictionalPhone(): string
    {
        static $counter = 0;

        $counter++;

        return sprintf('08%02d%08d', $counter % 100, 0);
    }

    /**
     * An ACTIVE membership carrying the given tenant roles.
     *
     * @param  list<string>  $roleKeys
     */
    protected function makeMembership(Tenant $tenant, User $user, array $roleKeys = []): Membership
    {
        $membership = Membership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        // `status` is not mass-assignable; it moves through the audited method.
        $membership->markActive();

        foreach ($roleKeys as $roleKey) {
            $this->grantRole($membership, $roleKey);
        }

        return $membership->fresh();
    }

    protected function grantRole(Membership $membership, string $roleKey): void
    {
        PermissionRegistry::assertAssignableToMembership($roleKey);

        $role = Role::query()->where('key', $roleKey)->firstOrFail();

        DB::table('membership_role')->insert([
            'id' => (string) Str::uuid(),
            // Bound to the membership's tenant by composite foreign key.
            'tenant_id' => $membership->tenant_id,
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Log in over the API and return the issued bearer token.
     *
     * Uses the real endpoint rather than Sanctum::actingAs, so the test
     * exercises the authentication path it claims to cover.
     */
    protected function loginToken(User $user, string $password = 'placeholder-KataSandiUji12345'): string
    {
        return $this->loginSession($user, $password)['token'];
    }

    /**
     * Log in and return BOTH the bearer token and the session row id.
     *
     * The id comes from the response rather than from a "most recent row"
     * query: two logins in the same second are indistinguishable by timestamp,
     * so ordering by `created_at` picks an arbitrary one of them.
     *
     * @return array{token: string, id: string}
     */
    protected function loginSession(User $user, string $password = 'placeholder-KataSandiUji12345'): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => $password,
            'mode' => 'token',
            'device_name' => 'Perangkat Uji',
        ]);

        $response->assertOk();

        return [
            'token' => $response->json('data.token'),
            'id' => $response->json('data.session.id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token, ?string $tenantId = null): array
    {
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        if ($tenantId !== null) {
            // A REQUEST for a tenant, never proof of access to it. The server
            // re-verifies membership on every request (Rule 02, hard rule 9).
            $headers['X-Tenant-Id'] = $tenantId;
        }

        return $headers;
    }
}
