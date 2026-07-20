<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LOCAL DEVELOPMENT DATA — every datum is fictional and recognisably so.
 *
 * PUBLIC REPOSITORY CONSTRAINTS (Rule 23, Rule 03, AMENDMENT-0001, DEC-0016)
 * --------------------------------------------------------------------------
 * This repository is PUBLIC and every file in it is world-readable, permanently.
 * Accordingly:
 *
 *   - Email addresses use the reserved `.invalid` TLD, which can never resolve.
 *   - Telephone numbers are structurally fabricated (a single repeated digit),
 *     so no real subscriber can be reached by dialling one.
 *   - Names are invented businesses and invented people.
 *   - NO PASSWORD IS COMMITTED. A distinct random password is generated per user
 *     at seed time and printed once to the console. There is deliberately no
 *     shared default: one shared development password is one credential-stuffing
 *     list away from being a production password too.
 *
 * The printed passwords exist only in the operator's terminal. They are not
 * written to a file, not logged, and not recoverable — re-seed to get new ones.
 *
 * SCOPE
 * -----
 * Step 3 entities only: tenants, brands, outlets, users, memberships, and role
 * assignments. There is no customer record, no service, no price list, and no
 * order here, because no such table exists (CLAUDE.md §3, roadmap lock).
 */
final class DevelopmentDataSeeder extends Seeder
{
    /**
     * Fictional telephone numbers. Each is a single repeated digit after the
     * `08` prefix, which is structurally impossible to mistake for a subscriber.
     */
    private const PHONES = [
        'owner.melati' => '081200000100',
        'admin.melati' => '081200000200',
        'kasir.melati' => '081200000300',
        'kurir.melati' => '081200000400',
        'owner.kenanga' => '081200000500',
        'lintas.tenant' => '081200000600',
        'nonaktif' => '081200000700',
    ];

    public function run(): void
    {
        $melati = $this->tenant('Laundry Melati Fiktif', 'laundry-melati-fiktif');
        $kenanga = $this->tenant('Laundry Kenanga Fiktif', 'laundry-kenanga-fiktif');

        $melatiBrand = $this->brand($melati, 'Melati Express (Fiktif)', 'melati-express');
        $kenangaBrand = $this->brand($kenanga, 'Kenanga Wangi (Fiktif)', 'kenanga-wangi');

        $this->outlet($melati, $melatiBrand, 'Outlet Melati Pusat (Fiktif)', 'MLT-01');
        $this->outlet($melati, $melatiBrand, 'Outlet Melati Cabang (Fiktif)', 'MLT-02');
        $this->outlet($kenanga, $kenangaBrand, 'Outlet Kenanga Pusat (Fiktif)', 'KNG-01');

        $credentials = [];

        // --- Tenant Melati ---------------------------------------------------
        $credentials[] = $this->member(
            'Budi Contoh', 'owner.melati@contoh.invalid', self::PHONES['owner.melati'],
            [[$melati, [PermissionRegistry::ROLE_TENANT_OWNER]]],
        );

        $credentials[] = $this->member(
            'Sari Contoh', 'admin.melati@contoh.invalid', self::PHONES['admin.melati'],
            [[$melati, [PermissionRegistry::ROLE_TENANT_ADMIN]]],
        );

        $credentials[] = $this->member(
            'Dewi Contoh', 'kasir.melati@contoh.invalid', self::PHONES['kasir.melati'],
            [[$melati, [PermissionRegistry::ROLE_CASHIER]]],
        );

        $credentials[] = $this->member(
            'Agus Contoh', 'kurir.melati@contoh.invalid', self::PHONES['kurir.melati'],
            [[$melati, [PermissionRegistry::ROLE_COURIER]]],
        );

        // --- Tenant Kenanga --------------------------------------------------
        $credentials[] = $this->member(
            'Rina Contoh', 'owner.kenanga@contoh.invalid', self::PHONES['owner.kenanga'],
            [[$kenanga, [PermissionRegistry::ROLE_TENANT_OWNER]]],
        );

        // --- One identity, two tenants (Rule 02, hard rule 1) ----------------
        // Exists so tenant switching and the isolation matrices have a subject
        // that legitimately belongs to both tenants and must still never see one
        // tenant's data while acting in the other.
        $credentials[] = $this->member(
            'Tono Contoh', 'lintas.tenant@contoh.invalid', self::PHONES['lintas.tenant'],
            [
                [$melati, [PermissionRegistry::ROLE_OUTLET_MANAGER]],
                [$kenanga, [PermissionRegistry::ROLE_FINANCE]],
            ],
        );

        // --- A disabled account ----------------------------------------------
        // A disabled user authenticates to nothing. Seeded so that rejection is
        // exercisable locally rather than only asserted in a test.
        $disabled = $this->member(
            'Nonaktif Contoh', 'nonaktif@contoh.invalid', self::PHONES['nonaktif'],
            [[$melati, [PermissionRegistry::ROLE_CASHIER]]],
        );

        User::query()->where('email', 'nonaktif@contoh.invalid')->update(['disabled_at' => now()]);
        $disabled['note'] = 'AKUN DINONAKTIFKAN — tidak dapat masuk';
        $credentials[] = $disabled;

        $this->printCredentials($credentials);
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'timezone' => 'Asia/Jakarta'],
        );
    }

    private function brand(Tenant $tenant, string $name, string $slug): LaundryBrand
    {
        return LaundryBrand::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $slug],
            ['name' => $name],
        );
    }

    private function outlet(Tenant $tenant, LaundryBrand $brand, string $name, string $code): Outlet
    {
        return Outlet::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $code],
            [
                'laundry_brand_id' => $brand->id,
                'name' => $name,
                'timezone' => 'Asia/Jakarta',
            ],
        );
    }

    /**
     * Create a user with a distinct random password and its tenant memberships.
     *
     * @param  list<array{0: Tenant, 1: list<string>}>  $tenantRoles
     * @return array<string, string>
     */
    private function member(string $name, string $email, string $phone, array $tenantRoles): array
    {
        // Distinct per user, random per seed run, never committed.
        $password = $this->generatePassword();

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone, 'password' => $password],
        );

        // firstOrCreate does not re-hash on an existing row; force the new
        // password so a re-seed leaves the operator with credentials that work.
        $user->password = $password;
        $user->save();

        $roleSummary = [];

        foreach ($tenantRoles as [$tenant, $roleKeys]) {
            $membership = Membership::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]);

            // `status` is not fillable by design; it moves only through the
            // audited domain method (Rule 02 — no silent privilege change).
            if (! $membership->isActive()) {
                $membership->markActive();
            }

            $this->assignRoles($membership, $roleKeys);

            $roleSummary[] = $tenant->slug.': '.implode(', ', $roleKeys);
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'roles' => implode(' | ', $roleSummary),
        ];
    }

    /**
     * @param  list<string>  $roleKeys
     */
    private function assignRoles(Membership $membership, array $roleKeys): void
    {
        foreach ($roleKeys as $roleKey) {
            // Refuses a platform role outright (DEC-0025 §8). A platform role is
            // not assignable through `membership_role`, and the seeder is not an
            // exception to that.
            PermissionRegistry::assertAssignableToMembership($roleKey);

            $role = Role::query()->where('key', $roleKey)->first();

            if ($role === null) {
                throw new \RuntimeException(
                    "Role {$roleKey} is absent. Run RolePermissionSeeder first."
                );
            }

            $exists = DB::table('membership_role')
                ->where('membership_id', $membership->id)
                ->where('role_id', $role->id)
                ->exists();

            if ($exists) {
                continue;
            }

            // `tenant_id` is written explicitly and is bound to the membership's
            // tenant by the composite foreign key
            // `membership_role_tenant_membership_foreign`, so a cross-tenant
            // assignment is rejected by PostgreSQL (DEC-0025 §4).
            DB::table('membership_role')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $membership->tenant_id,
                'membership_id' => $membership->id,
                'role_id' => $role->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * A distinct, high-entropy development password.
     *
     * Satisfies the reset policy (>= 12 characters, letters and numbers) so a
     * seeded account can also exercise the password-reset path.
     */
    private function generatePassword(): string
    {
        return 'Dev'.Str::random(14).random_int(100, 999);
    }

    /**
     * @param  list<array<string, string>>  $credentials
     */
    private function printCredentials(array $credentials): void
    {
        $this->command?->newLine();
        $this->command?->warn('=========================================================');
        $this->command?->warn(' KREDENSIAL PENGEMBANGAN LOKAL — DICETAK SATU KALI');
        $this->command?->warn('=========================================================');
        $this->command?->line(' Setiap data di bawah ini FIKTIF. Kata sandi dibuat acak');
        $this->command?->line(' per pengguna pada saat seeding, tidak disimpan di berkas,');
        $this->command?->line(' dan tidak pernah dikomit. Jalankan ulang seeder untuk');
        $this->command?->line(' mendapatkan kata sandi baru.');
        $this->command?->newLine();

        foreach ($credentials as $row) {
            $this->command?->line('  '.$row['email']);
            $this->command?->line('    nama      : '.$row['name']);
            $this->command?->line('    kata sandi: '.$row['password']);
            $this->command?->line('    peran     : '.$row['roles']);

            if (isset($row['note'])) {
                $this->command?->line('    catatan   : '.$row['note']);
            }

            $this->command?->newLine();
        }

        $this->command?->warn(' Jangan salin kredensial ini ke berkas mana pun.');
        $this->command?->warn('=========================================================');
        $this->command?->newLine();
    }
}
