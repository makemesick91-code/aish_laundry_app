<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\Models\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * Happy paths for login, logout, and "who am I".
 *
 * The adversarial matrices (enumeration timing, brute force, cross-tenant
 * traversal) are a separate exercise and are deliberately not attempted here.
 */
final class AuthenticationTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    public function test_login_issues_a_token_and_returns_the_enveloped_user(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'placeholder-KataSandiUji12345',
            'mode' => 'token',
            'device_name' => 'Perangkat Uji',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mode', 'token')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name'], 'token', 'session'],
                'meta' => ['request_id'],
            ]);

        $this->assertIsString($response->json('data.token'));

        // The credential is handed over exactly once and is stored only as a
        // hash — the plaintext must not be recoverable from the database.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => $response->json('data.token'),
        ]);
    }

    public function test_login_in_cookie_mode_returns_no_token(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'placeholder-KataSandiUji12345',
            'mode' => 'cookie',
        ]);

        $response->assertOk()->assertJsonPath('data.mode', 'cookie');

        // A token in a browser is a token an XSS can read. Cookie mode must not
        // return one.
        $this->assertNull($response->json('data.token'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_accepts_the_phone_identifier(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'placeholder-KataSandiUji12345',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_records_a_success_audit_entry(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'placeholder-KataSandiUji12345',
        ])->assertOk();

        $entry = AuditEntry::query()
            ->where('action', AuditAction::AUTH_LOGIN_SUCCEEDED)
            ->where('actor_user_id', $user->id)
            ->first();

        $this->assertNotNull($entry);

        // The audit trail must never carry the credential that was presented.
        $encoded = json_encode($entry->toArray());
        $this->assertStringNotContainsString('placeholder-KataSandiUji12345', (string) $encoded);
    }

    public function test_login_failure_is_generic_and_identical_for_known_and_unknown_accounts(): void
    {
        $user = $this->makeUser();

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'KataSandiYangSalah999',
        ]);

        $unknownAccount = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'tidak.ada@contoh.invalid',
            'password' => 'KataSandiYangSalah999',
        ]);

        $wrongPassword->assertStatus(401);
        $unknownAccount->assertStatus(401);

        // Identical code AND identical message: a difference in either is a
        // user-enumeration oracle.
        $this->assertSame(
            $wrongPassword->json('error.code'),
            $unknownAccount->json('error.code'),
        );
        $this->assertSame(
            $wrongPassword->json('error.message'),
            $unknownAccount->json('error.message'),
        );
    }

    public function test_a_disabled_account_cannot_log_in(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['disabled_at' => now()])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'placeholder-KataSandiUji12345',
        ]);

        $response->assertStatus(401);

        // The rejection must not announce that the account exists but is
        // disabled — that is still an enumeration signal.
        $this->assertSame('UNAUTHENTICATED', $response->json('error.code'));
    }

    public function test_me_returns_the_identity_and_its_memberships(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user);

        $token = $this->loginToken($user);

        $this->getJson('/api/v1/auth/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.memberships.0.tenant.id', $tenant->id)
            ->assertJsonPath('data.memberships.0.status', 'active');
    }

    public function test_me_reports_no_role_or_permission_without_a_tenant(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $response = $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertOk();

        // Roles and permissions are properties of a MEMBERSHIP in a specific
        // tenant, never of the account (DEC-0025 §2).
        $this->assertArrayNotHasKey('roles', $response->json('data'));
        $this->assertArrayNotHasKey('permissions', $response->json('data'));
    }

    public function test_logout_revokes_only_the_current_session(): void
    {
        $user = $this->makeUser();

        $first = $this->loginToken($user);
        $second = $this->loginToken($user);

        $this->postJson('/api/v1/auth/logout', [], $this->bearer($first))
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        // The credential used to log out is now refused, and refused with a code
        // that says WHY rather than a bare UNAUTHENTICATED.
        $this->getJson('/api/v1/auth/me', $this->bearer($first))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REVOKED');

        // The other device is deliberately untouched.
        $this->getJson('/api/v1/auth/me', $this->bearer($second))->assertOk();
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        $user = $this->makeUser();

        $sawRateLimit = false;

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->email,
                'password' => 'KataSandiYangSalah999',
            ]);

            if ($response->json('error.code') === 'RATE_LIMITED') {
                $sawRateLimit = true;
                break;
            }
        }

        $this->assertTrue($sawRateLimit, 'Login harus dibatasi setelah percobaan gagal berulang.');
    }
}
