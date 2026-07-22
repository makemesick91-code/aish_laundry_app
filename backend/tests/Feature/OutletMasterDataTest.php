<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Organization\Http\Controllers\OutletMasterDataController;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Organization\Models\OutletPrinter;
use App\Modules\Organization\Models\OutletServiceZone;
use App\Modules\Organization\Models\OutletShift;
use App\Modules\Organization\Models\TenantProofPolicy;
use App\Modules\Organization\Support\OperatingHours;
use App\Modules\Organization\Support\WallClockTime;
use App\Modules\Tenancy\Models\Tenant;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * OUTLET MASTER DATA — FR-041 … FR-047.
 *
 * Runs against POSTGRESQL, which is the only engine whose result counts as
 * evidence for a tenant-isolation or constraint claim (Rule 43 hard rules 1–2).
 * Several assertions below check a CHECK or EXCLUDE constraint directly; on a
 * substitute engine they would silently prove nothing.
 *
 * Every value here is fictional and recognisably so (Rule 23, Rule 45).
 */
final class OutletMasterDataTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    /**
     * A tenant with an owner who holds every Step 4 master-data permission.
     *
     * @return array{tenant: Tenant, outlet: Outlet, token: string}
     */
    private function ownerScenario(string $timezone = 'Asia/Jakarta'): array
    {
        $this->seedCatalogue();

        $tenant = $this->makeTenant();
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);

        if ($timezone !== 'Asia/Jakarta') {
            $outlet->timezone = $timezone;
            $outlet->save();
        }

        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        return [
            'tenant' => $tenant,
            'outlet' => $outlet->refresh(),
            'token' => $this->loginToken($user, self::PASSWORD),
        ];
    }

    // ==================================================================
    // FR-047 — quiet hours, and the canonical default
    // ==================================================================

    public function test_a_new_outlet_defaults_to_quiet_hours_2000_to_0800(): void
    {
        // FR-047 and Rule 08 hard rule 6 name this window exactly. It is a
        // COLUMN DEFAULT, so an outlet created by any writer — this test, a
        // seeder, a console command — inherits it without anyone remembering to.
        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);

        $this->assertSame('20:00', $outlet->quiet_hours_start);
        $this->assertSame('08:00', $outlet->quiet_hours_end);
        $this->assertSame(Outlet::DEFAULT_QUIET_HOURS_START, $outlet->quiet_hours_start);
        $this->assertSame(Outlet::DEFAULT_QUIET_HOURS_END, $outlet->quiet_hours_end);
    }

    public function test_quiet_hours_are_evaluated_in_the_outlets_own_timezone(): void
    {
        // THE TEST THAT WOULD PASS VACUOUSLY IN ONE TIMEZONE.
        // 22:00 WIB is 00:00 WIT. The same instant is inside the quiet window
        // for a Jakarta outlet and inside it for a Jayapura outlet too — but the
        // 14:00 UTC instant below is 21:00 WIB (quiet) and 23:00 WIT (quiet),
        // while 01:00 UTC is 08:00 WIB (NOT quiet) and 10:00 WIT (not quiet).
        //
        // The discriminating instant is 00:30 UTC: 07:30 WIB is still quiet,
        // 09:30 WIT is not. An implementation reading a single server timezone
        // returns the same answer for both and fails here.
        $tenant = $this->makeTenant();

        $jakarta = $this->makeOutlet($tenant);
        $jakarta->timezone = 'Asia/Jakarta';
        $jakarta->save();

        $jayapura = $this->makeOutlet($tenant, null, 'Outlet Uji Timur');
        $jayapura->timezone = 'Asia/Jayapura';
        $jayapura->save();

        $instant = new DateTimeImmutable('2026-07-21T00:30:00+00:00');

        $this->assertTrue(
            $jakarta->isWithinQuietHours($instant),
            '07:30 WIB is inside the 20:00–08:00 quiet window.'
        );

        $this->assertFalse(
            $jayapura->isWithinQuietHours($instant),
            '09:30 WIT is outside the 20:00–08:00 quiet window. A single-timezone '
            .'implementation would wrongly report it as quiet.'
        );
    }

    public function test_the_quiet_window_spans_midnight_rather_than_matching_nothing(): void
    {
        // 20:00–08:00 read naively as `start <= t < end` matches NO time at all.
        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);

        // 23:00 WIB = 16:00 UTC.
        $this->assertTrue($outlet->isWithinQuietHours(
            new DateTimeImmutable('2026-07-21T16:00:00+00:00')
        ));

        // 12:00 WIB = 05:00 UTC — the middle of the working day.
        $this->assertFalse($outlet->isWithinQuietHours(
            new DateTimeImmutable('2026-07-21T05:00:00+00:00')
        ));
    }

    public function test_quiet_hours_boundaries_are_start_inclusive_and_end_exclusive(): void
    {
        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);

        // Exactly 20:00 WIB = 13:00 UTC — quiet begins.
        $this->assertTrue($outlet->isWithinQuietHours(
            new DateTimeImmutable('2026-07-21T13:00:00+00:00')
        ));

        // Exactly 08:00 WIB = 01:00 UTC — quiet has ended. An inclusive end
        // would silence the first message of the working day.
        $this->assertFalse($outlet->isWithinQuietHours(
            new DateTimeImmutable('2026-07-21T01:00:00+00:00')
        ));
    }

    public function test_the_database_refuses_a_quiet_hours_value_that_is_not_a_time(): void
    {
        // Enforced by a CHECK constraint, so no writer can store it — including a
        // future migration or console command that never touches the application
        // (Rule 18 hard rule 2).
        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('outlets')
            ->where('id', $outlet->id)
            ->update(['quiet_hours_start' => '25:00']);
    }

    // ==================================================================
    // FR-041 — operating hours in outlet local time
    // ==================================================================

    public function test_operating_hours_are_stored_and_returned_per_weekday(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $response = $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", [
                'operating_hours' => [
                    'monday' => ['is_open' => true, 'opens_at' => '08:00', 'closes_at' => '20:00'],
                    'sunday' => ['is_open' => false],
                ],
            ]);

        $response->assertOk();

        $hours = $response->json('data.outlet.operating_hours');

        $this->assertSame('08:00', $hours['monday']['opens_at']);
        $this->assertFalse($hours['sunday']['is_open']);

        // A closed day carries no times — stale hours on a closed day are how a
        // reopened outlet inherits last year's schedule.
        $this->assertArrayNotHasKey('opens_at', $hours['sunday']);

        // The timezone that makes those wall-clock times meaningful travels with
        // them. A bare "08:00" in a response invites the reader's own zone.
        $this->assertSame('Asia/Jakarta', $response->json('data.outlet.timezone'));
    }

    public function test_operating_hours_across_two_timezones_resolve_to_different_instants(): void
    {
        // FR-041's acceptance criterion is explicit that a single-timezone test
        // proves nothing about the rule.
        $hours = OperatingHours::fromArray([
            'monday' => ['is_open' => true, 'opens_at' => '08:00', 'closes_at' => '20:00'],
        ]);

        $this->assertTrue($hours->isOpenAt('monday', WallClockTime::parse('09:00')));
        $this->assertFalse($hours->isOpenAt('monday', WallClockTime::parse('07:59')));

        // The same wall clock resolves to instants two hours apart.
        $jakarta = WallClockTime::parse('08:00')->onDate('2026-07-21', 'Asia/Jakarta');
        $jayapura = WallClockTime::parse('08:00')->onDate('2026-07-21', 'Asia/Jayapura');

        $this->assertNotSame($jakarta->getTimestamp(), $jayapura->getTimestamp());
        $this->assertSame(2 * 3600, $jakarta->getTimestamp() - $jayapura->getTimestamp());
    }

    public function test_an_unconfigured_day_is_not_open_by_default(): void
    {
        $hours = OperatingHours::fromArray([
            'monday' => ['is_open' => true, 'opens_at' => '08:00', 'closes_at' => '20:00'],
        ]);

        // Defaulting to open would have the product asserting availability the
        // tenant never stated.
        $this->assertFalse($hours->isOpenAt('tuesday', WallClockTime::parse('09:00')));
    }

    public function test_operating_hours_may_cross_midnight(): void
    {
        $hours = OperatingHours::fromArray([
            'friday' => ['is_open' => true, 'opens_at' => '22:00', 'closes_at' => '02:00'],
        ]);

        $this->assertTrue($hours->isOpenAt('friday', WallClockTime::parse('23:30')));
        $this->assertTrue($hours->isOpenAt('friday', WallClockTime::parse('01:00')));
        $this->assertFalse($hours->isOpenAt('friday', WallClockTime::parse('12:00')));
    }

    public function test_an_unknown_weekday_is_rejected_by_name(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $response = $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", [
                'operating_hours' => ['senin' => ['is_open' => true, 'opens_at' => '08:00', 'closes_at' => '20:00']],
            ]);

        // The message names the offending day. A generic "invalid" would leave
        // an operator guessing which of seven entries was wrong (Rule 29).
        $response->assertStatus(422);
        $this->assertStringContainsString('senin', (string) $response->json('error.message'));
    }

    public function test_an_open_day_without_times_is_rejected(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", [
                'operating_hours' => ['monday' => ['is_open' => true]],
            ])
            ->assertStatus(422);
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        foreach (['WIB', 'GMT+7', 'Bukan/Zona'] as $invalid) {
            $this->withHeaders($this->bearer($token, $tenant->id))
                ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", ['timezone' => $invalid])
                ->assertStatus(422);
        }

        // A real IANA identifier is accepted.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", ['timezone' => 'Asia/Makassar'])
            ->assertOk();
    }

    // ==================================================================
    // FR-042 — capacity
    // ==================================================================

    public function test_capacity_is_recorded_and_may_not_be_negative(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", [
                'daily_capacity_kg' => 150,
                'daily_capacity_orders' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.outlet.daily_capacity_kg', 150);

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", ['daily_capacity_kg' => -1])
            ->assertStatus(422);
    }

    // ==================================================================
    // FR-043 — service zones
    // ==================================================================

    public function test_a_service_zone_is_created_and_listed(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/service-zones", [
                'code' => 'ZONA-A',
                'name' => 'Zona Uji Fiktif A',
                'postal_codes' => ['00001', '00002'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.zone.code', 'ZONA-A')
            ->assertJsonPath('data.zone.outlet_id', $outlet->id);

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson("/api/v1/outlets/{$outlet->id}/service-zones")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_a_zone_code_is_unique_within_the_outlet_but_not_across_outlets(): void
    {
        // INVARIANT O4. Two outlets of one tenant may both have a zone "A" — that
        // is normal, not a collision.
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $second = $this->makeOutlet($tenant, null, 'Outlet Uji Kedua');

        $payload = ['code' => 'ZONA-A', 'name' => 'Zona Uji Fiktif A'];

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/service-zones", $payload)
            ->assertStatus(201);

        // Same code, DIFFERENT outlet — permitted.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$second->id}/service-zones", $payload)
            ->assertStatus(201);

        // Same code, SAME outlet — refused, with a field-level message.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/service-zones", $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.details.code.0', 'duplicate');
    }

    // ==================================================================
    // FR-044 — shifts
    // ==================================================================

    public function test_a_shift_is_created_with_a_derived_midnight_flag(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/shifts", [
                'code' => 'PAGI',
                'name' => 'Shift Pagi',
                'starts_at' => '08:00',
                'ends_at' => '16:00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.shift.crosses_midnight', false);

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/shifts", [
                'code' => 'MALAM',
                'name' => 'Shift Malam',
                'starts_at' => '22:00',
                'ends_at' => '06:00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.shift.crosses_midnight', true);
    }

    public function test_the_midnight_flag_cannot_be_forced_to_contradict_the_hours(): void
    {
        // `crosses_midnight` is absent from $fillable and is derived server-side,
        // so a client claim is ignored rather than trusted (threat T-05).
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/shifts", [
                'code' => 'PAGI',
                'name' => 'Shift Pagi',
                'starts_at' => '08:00',
                'ends_at' => '16:00',
                'crosses_midnight' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.shift.crosses_midnight', false);
    }

    public function test_the_database_refuses_a_shift_whose_flag_disagrees_with_its_hours(): void
    {
        ['outlet' => $outlet, 'tenant' => $tenant] = $this->ownerScenario();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('outlet_shifts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'code' => 'RUSAK',
            'name' => 'Shift Tidak Konsisten',
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            // Contradicts the hours. The CHECK constraint refuses it regardless
            // of which writer attempts it.
            'crosses_midnight' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_partial_shift_update_keeps_the_derived_flag_correct(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $created = $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/shifts", [
                'code' => 'PAGI', 'name' => 'Shift Pagi',
                'starts_at' => '08:00', 'ends_at' => '16:00',
            ])->json('data.shift.id');

        // Only `ends_at` is sent. The flag must be re-derived against the STORED
        // `starts_at`, not against a null.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson("/api/v1/outlets/{$outlet->id}/shifts/{$created}", ['ends_at' => '06:00'])
            ->assertOk()
            ->assertJsonPath('data.shift.crosses_midnight', true)
            ->assertJsonPath('data.shift.starts_at', '08:00');
    }

    // ==================================================================
    // FR-045 — printers
    // ==================================================================

    public function test_a_printer_is_configured_with_an_enumerated_device_kind(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/printers", [
                'code' => 'PRN-1',
                'name' => 'Printer Kasir Uji',
                'device_kind' => OutletPrinter::DEVICE_THERMAL_58,
                'connection_kind' => OutletPrinter::CONNECTION_BLUETOOTH,
                'device_identifier' => 'PRINTER-UJI-0000',
                'is_default' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.printer.device_kind', 'thermal_58mm');

        // A free-text device kind would be unusable by the Step 5 printing path.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/printers", [
                'code' => 'PRN-2',
                'name' => 'Printer Uji Dua',
                'device_kind' => 'printer apa saja',
                'connection_kind' => OutletPrinter::CONNECTION_USB,
            ])
            ->assertStatus(422);
    }

    public function test_only_one_default_printer_per_outlet_is_permitted(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $base = [
            'device_kind' => OutletPrinter::DEVICE_THERMAL_80,
            'connection_kind' => OutletPrinter::CONNECTION_NETWORK,
            'is_default' => true,
        ];

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/printers", [...$base, 'code' => 'PRN-1', 'name' => 'Printer Satu'])
            ->assertStatus(201);

        // Two defaults is an ambiguity nothing downstream could resolve. The
        // partial unique index refuses the second at the engine.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/outlets/{$outlet->id}/printers", [...$base, 'code' => 'PRN-2', 'name' => 'Printer Dua'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.is_default.0', 'duplicate');
    }

    // ==================================================================
    // FR-046 — tenant proof policy
    // ==================================================================

    public function test_the_proof_policy_defaults_to_requiring_a_recipient_name(): void
    {
        ['token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson('/api/v1/proof-policy')
            ->assertOk()
            // Rule 09 hard rule 2: a tenant that has never opened this screen is
            // still covered by a policy that means something.
            ->assertJsonPath('data.proof_policy.pickup.pickup_requires_recipient_name', true)
            ->assertJsonPath('data.proof_policy.delivery.delivery_requires_recipient_name', true);
    }

    public function test_a_policy_requiring_no_proof_at_all_is_refused(): void
    {
        ['token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson('/api/v1/proof-policy', [
                'pickup_requires_photo' => false,
                'pickup_requires_signature' => false,
                'pickup_requires_recipient_name' => false,
                'pickup_requires_otp' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.proof.0', 'at_least_one_required');
    }

    public function test_the_database_refuses_a_proof_policy_that_requires_nothing(): void
    {
        // The application refuses it with a readable message; the CHECK
        // constraint refuses it regardless of writer. An invariant only one code
        // path honours is not an invariant (Rule 18 hard rule 2).
        ['tenant' => $tenant] = $this->ownerScenario();

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('tenant_proof_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'pickup_requires_photo' => false,
            'pickup_requires_signature' => false,
            'pickup_requires_recipient_name' => false,
            'pickup_requires_otp' => false,
            'delivery_requires_recipient_name' => true,
            'delivery_requires_photo' => false,
            'delivery_requires_signature' => false,
            'delivery_requires_otp' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_proof_policy_switching_to_a_different_proof_is_accepted(): void
    {
        ['token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->patchJson('/api/v1/proof-policy', [
                'pickup_requires_photo' => true,
                'pickup_requires_recipient_name' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.proof_policy.pickup.pickup_requires_photo', true)
            ->assertJsonPath('data.proof_policy.pickup.pickup_requires_recipient_name', false);
    }

    public function test_exactly_one_proof_policy_exists_per_tenant(): void
    {
        ['token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $headers = $this->bearer($token, $tenant->id);

        $this->withHeaders($headers)->getJson('/api/v1/proof-policy')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/proof-policy')->assertOk();

        // Two rows would mean two answers to one question.
        $this->assertSame(
            1,
            TenantProofPolicy::query()->forTenant($tenant->id)->count()
        );
    }

    // ==================================================================
    // Optimistic concurrency (threat T-12)
    // ==================================================================

    public function test_a_stale_write_is_refused_rather_than_silently_applied(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $headers = $this->bearer($token, $tenant->id);

        $version = $this->withHeaders($headers)
            ->getJson("/api/v1/outlets/{$outlet->id}/master-data")
            ->json('data.outlet.version');

        // Somebody else edits first.
        $this->withHeaders($headers)
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", ['daily_capacity_kg' => 100])
            ->assertOk();

        // Our write, holding the version we read before that edit.
        $response = $this->withHeaders([...$headers, 'If-Unmodified-Since-Version' => $version])
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", ['daily_capacity_kg' => 200]);

        // Surfaced, never resolved by picking a winner (Rule 07 hard rule 5's
        // principle applied to master data).
        $response->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');

        // And the earlier edit survived intact.
        $this->assertSame(100, $outlet->refresh()->daily_capacity_kg);
    }

    public function test_a_fresh_version_is_accepted(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $headers = $this->bearer($token, $tenant->id);

        $version = $this->withHeaders($headers)
            ->getJson("/api/v1/outlets/{$outlet->id}/master-data")
            ->json('data.outlet.version');

        $this->withHeaders([...$headers, 'If-Unmodified-Since-Version' => $version])
            ->patchJson("/api/v1/outlets/{$outlet->id}/master-data", ['daily_capacity_kg' => 200])
            ->assertOk();
    }

    // ==================================================================
    // Bounded listing (threats T-03, T-17)
    // ==================================================================

    public function test_an_arbitrary_sort_field_is_refused(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson("/api/v1/outlets/{$outlet->id}/service-zones?sort=tenant_id")
            ->assertStatus(422);
    }

    /**
     * SEC-09 — the exact request that returned HTTP 500.
     *
     * `display_order` is a real column on zones and shifts and does not exist on
     * `outlet_printers`. The allow-list was shared across all three collections,
     * so the value passed validation and then reached the database, which
     * refused it. The refusal surfaced as a 500.
     *
     * A 500 here is not merely untidy. It is an unhandled database error on a
     * path a client controls: it carries driver-level text rather than the
     * canonical envelope, it tells a prober which column names the engine
     * recognises, and with debug rendering enabled it would carry the statement
     * itself.
     */
    public function test_a_sort_field_from_another_collection_is_refused_rather_than_reaching_the_database(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $response = $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson("/api/v1/outlets/{$outlet->id}/printers?sort=display_order");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // The advertised set must be the one that actually applies here.
        // Echoing a column this table does not have is what invited the 500.
        $this->assertNotContains('display_order', $response->json('error.details.sort'));

        // The same value on a collection that DOES have the column still works.
        // Without this control the assertion above would also pass if sorting
        // had simply been removed.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson("/api/v1/outlets/{$outlet->id}/service-zones?sort=display_order")
            ->assertOk();
    }

    /**
     * The general form of SEC-09, checked against the LIVE schema.
     *
     * The specific bug was one column missing from one table. The bug CLASS is
     * an allow-list asserting a column exists when nothing verifies it — which
     * recurs the moment a fourth collection is added or a column is dropped.
     * Checking every entry against `information_schema` closes the class rather
     * than the instance.
     */
    public function test_every_sortable_column_exists_on_its_own_table(): void
    {
        $tables = [
            'zones' => 'outlet_service_zones',
            'shifts' => 'outlet_shifts',
            'printers' => 'outlet_printers',
        ];

        $sortable = (new \ReflectionClass(OutletMasterDataController::class))
            ->getConstant('SORTABLE');

        $this->assertSame(
            array_keys($tables),
            array_keys($sortable),
            'a collection was added or renamed without extending this check'
        );

        foreach ($sortable as $collection => $columns) {
            $this->assertNotEmpty($columns, "{$collection} allows no sort column at all");
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($tables[$collection], $column),
                    "{$collection} allows sorting by `{$column}`, which does not exist on "
                    ."`{$tables[$collection]}` — that request would reach the database and 500."
                );
            }
        }
    }

    public function test_pagination_is_hard_bounded(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson("/api/v1/outlets/{$outlet->id}/service-zones?per_page=100000")
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 100);
    }

    // ==================================================================
    // Absent by design (threats T-18, T-19, T-20)
    // ==================================================================

    public function test_there_is_no_delete_export_or_bulk_route_for_outlet_master_data(): void
    {
        // ABSENCE ASSERTED AGAINST THE ROUTE TABLE, not by probing URLs.
        //
        // A URL probe is the wrong instrument here: GET
        // `/service-zones/export` returns 405 rather than 404 because "export"
        // binds as the `{zone}` path parameter of the PATCH route. That 405 is
        // correct behaviour, but reading it as "no export route" would be
        // reasoning from a status code that happens to agree with the claim.
        //
        // Reading the registered routes proves the thing directly (T-18, T-19,
        // T-20).
        $registered = [];

        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $registered[] = $method.' /'.$route->uri();
            }
        }

        foreach ($registered as $signature) {
            $this->assertStringNotContainsString(
                'export',
                $signature,
                'Step 4 registers no export route; the isolation matrix records '
                .'the export path as NOT APPLICABLE rather than as a pass.'
            );

            $this->assertStringNotContainsString(
                'bulk',
                $signature,
                'Step 4 offers no bulk-mutation route (T-19).'
            );
        }

        // No DELETE verb anywhere in the Step 4 outlet master-data surface: a
        // zone, shift, or printer a future delivery references must stay
        // resolvable, so deactivation replaces deletion (T-18).
        $outletDeletes = array_filter(
            $registered,
            static fn (string $s): bool => str_starts_with($s, 'DELETE ') && str_contains($s, 'outlets/')
        );

        $this->assertSame([], array_values($outletDeletes));

        // And the verb genuinely is not routed.
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->deleteJson("/api/v1/outlets/{$outlet->id}/service-zones/".Str::uuid())
            ->assertStatus(405);
    }

    public function test_deactivating_rather_than_deleting_keeps_the_record_resolvable(): void
    {
        ['outlet' => $outlet, 'token' => $token, 'tenant' => $tenant] = $this->ownerScenario();

        $headers = $this->bearer($token, $tenant->id);

        $zoneId = $this->withHeaders($headers)
            ->postJson("/api/v1/outlets/{$outlet->id}/service-zones", ['code' => 'ZONA-A', 'name' => 'Zona Uji A'])
            ->json('data.zone.id');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/outlets/{$outlet->id}/service-zones/{$zoneId}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.zone.is_active', false);

        // Still resolvable — the row was deactivated, not destroyed (T-18).
        $this->assertNotNull(OutletServiceZone::query()->whereKey($zoneId)->first());

        // And filterable out of the active list.
        $this->withHeaders($headers)
            ->getJson("/api/v1/outlets/{$outlet->id}/service-zones?is_active=true")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    // ==================================================================
    // Structural facts
    // ==================================================================

    public function test_every_outlet_satellite_table_carries_tenant_id_not_null(): void
    {
        // Rule 02 hard rule 7, verified against the LIVE schema rather than by
        // reading the migration source — a migration can say one thing and the
        // resulting column be another.
        foreach (['outlet_service_zones', 'outlet_shifts', 'outlet_printers', 'tenant_proof_policies'] as $table) {
            $column = DB::selectOne(
                'select data_type, is_nullable from information_schema.columns '
                .'where table_name = ? and column_name = ?',
                [$table, 'tenant_id']
            );

            $this->assertNotNull($column, "{$table} has no tenant_id column.");
            $this->assertSame('NO', $column->is_nullable, "{$table}.tenant_id is nullable.");
        }
    }

    public function test_every_outlet_satellite_is_bound_to_its_outlets_tenant_by_composite_key(): void
    {
        // INVARIANT O3. The composite foreign key is what makes a cross-tenant
        // pairing structurally impossible rather than merely discouraged.
        foreach (['outlet_service_zones', 'outlet_shifts', 'outlet_printers'] as $table) {
            $constraint = DB::selectOne(
                'select conname from pg_constraint where conname = ?',
                ["{$table}_tenant_outlet_foreign"]
            );

            $this->assertNotNull(
                $constraint,
                "{$table} has no composite (tenant_id, outlet_id) foreign key."
            );
        }
    }

    public function test_a_satellite_cannot_be_attached_to_another_tenants_outlet(): void
    {
        // The structural guarantee, exercised. Tenant B's outlet with tenant A's
        // id is refused by PostgreSQL, not by a remembered check (threat T-13).
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $outletB = $this->makeOutlet($tenantB);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('outlet_service_zones')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'outlet_id' => $outletB->id,
            'code' => 'ZONA-X',
            'name' => 'Zona Lintas Tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
