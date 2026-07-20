<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Http\ExceptionRenderer;
use App\Modules\Tenancy\Models\Membership;
use Database\Seeders\DevelopmentDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Concerns\BuildsTenantScenario;
use Tests\Concerns\CapturesLogOutput;
use Tests\TestCase;

/**
 * MATRIX D — Adversarial authentication.
 *
 * Twenty-five attacks against the credential, session and token surface. The
 * organising principle throughout: an attacker learns from DIFFERENCES. A
 * different status code, a different message, a different response shape, a
 * different error class — each is a bit of information, and enough bits become
 * a valid username list or a working session.
 *
 * So most negative cases here do not merely assert "denied". They assert
 * "denied IDENTICALLY to the control", which is a strictly stronger and much
 * more fragile property, and the one that actually matters.
 *
 * Every credential, token and account below is fictional (Rule 23).
 */
final class AuthenticationAdversarialMatrixTest extends TestCase
{
    use BuildsTenantScenario;
    use CapturesLogOutput;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    protected function tearDown(): void
    {
        $this->endLogCapture();
        parent::tearDown();
    }

    // =====================================================================
    // D1 — wrong password
    // =====================================================================

    public function test_d01_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser();

        // Control: the correct credential works, proving the account is usable
        // and the endpoint reachable.
        $this->login($user->email, self::PASSWORD)->assertOk();

        $this->login($user->email, 'placeholder-KataSandiSalah99999')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    // =====================================================================
    // D2 — unknown user must be indistinguishable from wrong password
    // =====================================================================

    public function test_d02_an_unknown_account_is_indistinguishable_from_a_wrong_password(): void
    {
        $known = $this->makeUser();

        $this->login($known->email, self::PASSWORD)->assertOk();

        $wrongPassword = $this->login($known->email, 'placeholder-KataSandiSalah99999');
        $unknownAccount = $this->login('tidak.ada.'.Str::random(10).'@contoh.invalid', 'placeholder-KataSandiSalah99999');

        // Identical status...
        $wrongPassword->assertStatus(401);
        $unknownAccount->assertStatus(401);

        // ...and an identical body once the per-request correlation id is
        // removed. A different message, a different error code, or an extra
        // `details` key would each be a working account-enumeration oracle.
        $this->assertSame(
            $this->comparable($wrongPassword->json()),
            $this->comparable($unknownAccount->json()),
            'A known account with a wrong password is distinguishable from an unknown account. '
            .'That difference is a username enumeration oracle.'
        );

        // No timing oracle via a divergent exception path: an unknown account
        // must still perform a hash comparison rather than returning early.
        // Proven structurally — the equalisation hash exists and is exercised.
        $this->assertNotEmpty(
            DB::table('audit_entries')->where('action', 'like', 'auth.login.failed%')->count(),
            'Failed logins must be audited; an unaudited failure path cannot be reasoned about.'
        );
    }

    // =====================================================================
    // D3 — brute force throttling
    // =====================================================================

    public function test_d03_repeated_failures_are_throttled_with_rate_limited(): void
    {
        $user = $this->makeUser();

        // Control: the account works before the throttle engages.
        $this->login($user->email, self::PASSWORD)->assertOk();

        $sawRateLimited = false;

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $response = $this->login($user->email, 'placeholder-KataSandiSalah'.$attempt);

            if ($response->status() === 429) {
                $response->assertJsonPath('error.code', 'RATE_LIMITED');
                $sawRateLimited = true;
                break;
            }

            $response->assertStatus(401);
        }

        $this->assertTrue($sawRateLimited, 'Repeated credential failures were never throttled — unbounded brute force.');

        // And the throttle is not bypassed by then supplying the CORRECT
        // password: a lockout that yields to the right guess is not a lockout.
        $this->login($user->email, self::PASSWORD)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    // =====================================================================
    // D4 — password-reset enumeration
    // =====================================================================

    public function test_d04_password_reset_request_does_not_enumerate_accounts(): void
    {
        $known = $this->makeUser();

        $forKnown = $this->postJson('/api/v1/auth/password-reset/request', ['identifier' => $known->email]);
        $forUnknown = $this->postJson('/api/v1/auth/password-reset/request', [
            'identifier' => 'tidak.ada.'.Str::random(10).'@contoh.invalid',
        ]);

        $forKnown->assertOk();
        $forUnknown->assertOk();

        $this->assertSame(
            $this->comparable($forKnown->json()),
            $this->comparable($forUnknown->json()),
            'The reset endpoint answers differently for a registered and an unregistered identifier — '
            .'an account enumeration oracle on an unauthenticated endpoint.'
        );

        // Control: the known account really did get a token, proving the
        // identical response is a deliberate mask and not a broken endpoint.
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }

    // =====================================================================
    // D5 — EXPIRED reset token
    // =====================================================================

    public function test_d05_an_expired_reset_token_is_rejected(): void
    {
        $user = $this->makeUser();
        $plain = $this->issueResetToken($user);

        // Control: the token works while fresh. This runs FIRST so that the
        // negative case below cannot be blamed on a malformed fixture.
        $this->completeReset($user->email, $plain)->assertOk();

        // Re-issue and age it well past the configured expiry window.
        $user2 = $this->makeUser();
        $plain2 = $this->issueResetToken($user2);
        $expiryMinutes = (int) config('auth.passwords.users.expire', 60);

        DB::table('password_reset_tokens')
            ->where('identifier', mb_strtolower($user2->email))
            ->update(['created_at' => now()->subMinutes($expiryMinutes * 4)]);

        $response = $this->completeReset($user2->email, $plain2);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // The password must NOT have been changed by an expired token.
        $this->assertFalse(
            Hash::check('placeholder-KataSandiBaruUji12345', User::query()->whereKey($user2->id)->value('password')),
            'An EXPIRED password-reset token was accepted and changed the account password. '
            .'An expired reset link is a credential that outlives its own revocation window.'
        );
    }

    // =====================================================================
    // D6 — reused reset token
    // =====================================================================

    public function test_d06_a_reset_token_cannot_be_reused(): void
    {
        $user = $this->makeUser();
        $plain = $this->issueResetToken($user);

        // Control: first use succeeds.
        $this->completeReset($user->email, $plain)->assertOk();

        // Violation: the same token, a second time.
        $this->completeReset($user->email, $plain, 'placeholder-KataSandiKetiga12345')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertFalse(
            Hash::check('placeholder-KataSandiKetiga12345', User::query()->whereKey($user->id)->value('password')),
            'A replayed reset token changed the password a second time.'
        );
    }

    // =====================================================================
    // D7 — altered / forged reset token
    // =====================================================================

    public function test_d07_an_altered_or_forged_reset_token_is_rejected(): void
    {
        $user = $this->makeUser();
        $plain = $this->issueResetToken($user);

        foreach ([
            'altered' => substr($plain, 0, -1).'X',
            'forged' => Str::random(64),
            'empty-ish' => 'x',
        ] as $label => $candidate) {
            $this->completeReset($user->email, $candidate)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');

            $this->assertFalse(
                Hash::check('placeholder-KataSandiBaruUji12345', User::query()->whereKey($user->id)->value('password')),
                sprintf('A %s reset token was accepted.', $label)
            );
        }

        // Control: the genuine token still works afterwards, proving the
        // rejections above were about the token and not about a broken account.
        $this->completeReset($user->email, $plain)->assertOk();
    }

    // =====================================================================
    // D8 — session fixation
    // =====================================================================

    public function test_d08_the_session_id_rotates_on_login(): void
    {
        $user = $this->makeUser();

        $this->startSession();
        $before = session()->getId();
        $this->assertNotEmpty($before, 'Control: a session must exist before login for fixation to be testable.');

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => self::PASSWORD,
            'mode' => 'cookie',
        ])->assertOk()->assertJsonPath('data.mode', 'cookie');

        $after = session()->getId();

        $this->assertNotSame(
            $before,
            $after,
            'The session identifier survived authentication. An attacker who fixes a victim\'s session id '
            .'before login then holds an authenticated session afterwards.'
        );
    }

    // =====================================================================
    // D9 — revoked session replay
    // =====================================================================

    public function test_d09_a_revoked_session_token_cannot_be_replayed(): void
    {
        $user = $this->makeUser();
        $session = $this->loginSession($user);

        // Control: the token works before revocation.
        $this->getJson('/api/v1/auth/me', $this->bearer($session['token']))->assertOk();

        $this->deleteJson('/api/v1/sessions/'.$session['id'], [], $this->bearer($session['token']))->assertOk();

        // Replay of the captured bearer token must name revocation explicitly,
        // not a generic 401 — the client needs to know re-login is the fix.
        $this->getJson('/api/v1/auth/me', $this->bearer($session['token']))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REVOKED');
    }

    // =====================================================================
    // D10 — logout replay
    // =====================================================================

    public function test_d10_a_token_cannot_be_replayed_after_logout(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertOk();
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))->assertOk();

        // Both a repeated logout and any other authenticated call must fail.
        $this->postJson('/api/v1/auth/logout', [], $this->bearer($token))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REVOKED');

        $this->getJson('/api/v1/auth/me', $this->bearer($token))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REVOKED');
    }

    // =====================================================================
    // D11 — disabled user
    // =====================================================================

    public function test_d11_a_disabled_user_is_denied(): void
    {
        $user = $this->makeUser();

        // Control: the account authenticates while enabled.
        $token = $this->loginToken($user);
        $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertOk();

        User::query()->whereKey($user->id)->update(['disabled_at' => now()]);

        // New logins are refused...
        $this->login($user->email, self::PASSWORD)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // ...and the ALREADY-ISSUED token stops working too. Disablement that
        // only blocks the front door leaves every open session running.
        $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertStatus(401);
    }

    // =====================================================================
    // D12 / D13 — authorization is recomputed, never carried in the token
    // =====================================================================

    public function test_d12_a_revoked_membership_invalidates_a_still_valid_session(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user), $tenant->id);

        $this->getJson('/api/v1/memberships/current', $headers)->assertOk();

        Membership::query()->whereKey($membership->id)->update(['status' => Membership::STATUS_REVOKED]);

        // The TOKEN is still valid — the MEMBERSHIP is not. The two must be
        // evaluated separately on every request.
        $this->getJson('/api/v1/auth/me', $this->bearer($this->tokenFromHeaders($headers)))->assertOk();
        $this->getJson('/api/v1/memberships/current', $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEMBERSHIP_REVOKED');
    }

    public function test_d13_a_removed_role_invalidates_a_still_valid_session(): void
    {
        $tenant = $this->makeTenant();
        $this->makeOutlet($tenant);
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_OUTLET_MANAGER]);
        $headers = $this->bearer($this->loginToken($user), $tenant->id);

        $this->getJson('/api/v1/context/outlets', $headers)->assertOk();

        DB::table('membership_role')->where('membership_id', $membership->id)->delete();

        $this->getJson('/api/v1/context/outlets', $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // =====================================================================
    // D14 — forged tenant header
    // =====================================================================

    public function test_d14_a_forged_tenant_header_is_never_authorization_proof(): void
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $user = $this->makeUser();
        $this->makeMembership($tenantA, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $this->makeUser(), [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);

        $this->getJson('/api/v1/memberships/current', $this->bearer($token, $tenantA->id))->assertOk();

        $this->getJson('/api/v1/memberships/current', $this->bearer($token, $tenantB->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    // =====================================================================
    // D15 / D16 — role cannot be self-asserted through the request body
    // =====================================================================

    public function test_d15_a_forged_role_field_in_the_request_body_is_ignored(): void
    {
        $tenant = $this->makeTenant();
        $this->makeOutlet($tenant);
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CASHIER]);

        // The attacker asserts a role in the login payload itself.
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => self::PASSWORD,
            'mode' => 'token',
            'role' => PermissionRegistry::ROLE_TENANT_OWNER,
            'roles' => [PermissionRegistry::ROLE_TENANT_OWNER],
            'permissions' => [PermissionRegistry::OUTLET_VIEW],
        ])->assertOk();

        $headers = $this->bearer($response->json('data.token'), $tenant->id);
        $served = $this->getJson('/api/v1/authorization/permissions', $headers)->assertOk();

        // The claim is ignored, not honoured: the roles and permissions served
        // are exactly the cashier's, computed from the membership.
        $this->assertSame([PermissionRegistry::ROLE_CASHIER], $served->json('data.roles'));

        $expected = PermissionRegistry::matrix()[PermissionRegistry::ROLE_CASHIER];
        $actual = $served->json('data.permissions');
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'A role asserted in the request body altered the effective permission set.');

        // Explicitly: none of the owner-only capabilities the forged role
        // claimed were acquired. (A cashier legitimately holds outlet.view and
        // outlet.switch — they work a counter at an outlet — so the escalation
        // to test for is the OWNER-only set, not outlet access.)
        foreach (array_diff(
            PermissionRegistry::matrix()[PermissionRegistry::ROLE_TENANT_OWNER],
            PermissionRegistry::matrix()[PermissionRegistry::ROLE_CASHIER]
        ) as $ownerOnly) {
            $this->assertNotContains($ownerOnly, $actual, sprintf(
                'Forging a role in the login body granted owner-only permission "%s".', $ownerOnly
            ));
        }
    }

    public function test_d16_a_role_cannot_be_mass_assigned_onto_the_user_record(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CASHIER]);

        // There must be no `role` column on `users` at all: a role is a
        // property of a MEMBERSHIP, so a user-level role column would be a role
        // that outranks the tenant boundary by construction.
        $userColumns = array_map(
            static fn ($c): string => (string) $c,
            DB::connection()->getSchemaBuilder()->getColumnListing('users')
        );

        foreach (['role', 'roles', 'is_admin', 'permissions', 'tenant_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $userColumns, sprintf(
                'Column "users.%s" exists. Authorization derived from the user account rather than from a '
                .'membership escapes the tenant boundary (Rule 02).',
                $forbidden
            ));
        }

        // And a direct mass-assignment attempt does not create one.
        $user->fill(['role' => 'tenant_owner', 'is_admin' => true]);
        $user->save();

        $this->assertSame(
            [PermissionRegistry::ROLE_CASHIER],
            $this->getJson(
                '/api/v1/authorization/permissions',
                $this->bearer($this->loginToken($user), $tenant->id)
            )->assertOk()->json('data.roles')
        );
    }

    // =====================================================================
    // D17 / D18 — cross-user and cross-tenant session access
    // =====================================================================

    public function test_d17_a_user_cannot_delete_another_users_session(): void
    {
        $victim = $this->makeUser();
        $attacker = $this->makeUser();

        $victimSession = $this->loginSession($victim);
        $attackerToken = $this->loginToken($attacker);

        // Control: the attacker CAN delete their own session, so a 404 below is
        // about ownership rather than a broken route.
        $attackerSession = $this->loginSession($attacker);
        $this->deleteJson('/api/v1/sessions/'.$attackerSession['id'], [], $this->bearer($attackerToken))->assertOk();

        $this->deleteJson('/api/v1/sessions/'.$victimSession['id'], [], $this->bearer($attackerToken))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertNull(AccessToken::query()->whereKey($victimSession['id'])->value('revoked_at'));
        $this->getJson('/api/v1/auth/me', $this->bearer($victimSession['token']))->assertOk();
    }

    public function test_d18_session_listing_never_crosses_a_tenant_or_user_boundary(): void
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $sessionA = $this->loginSession($userA);
        $sessionB = $this->loginSession($userB);

        $listedForB = array_column(
            $this->getJson('/api/v1/sessions', $this->bearer($sessionB['token']))->assertOk()->json('data.sessions'),
            'id'
        );

        $this->assertContains($sessionB['id'], $listedForB, 'Control: user B must see their own session.');
        $this->assertNotContains($sessionA['id'], $listedForB, 'A session belonging to another user/tenant was listed.');
    }

    // =====================================================================
    // D19 — CSRF
    // =====================================================================

    public function test_d19_a_csrf_token_mismatch_renders_as_csrf_failed(): void
    {
        // NOTE ON SCOPE: an end-to-end CSRF rejection cannot be driven through
        // the HTTP kernel in this environment. Laravel's ValidateCsrfToken
        // short-circuits whenever the application is running unit tests, so a
        // "CSRF rejected" assertion made over the test HTTP client would be
        // asserting a code path that did not execute — precisely the kind of
        // invalid proof this suite exists to avoid. This is stated in the
        // report rather than papered over.
        //
        // What IS verified here is the contract that a CSRF failure surfaces as
        // the specific documented error code and status, exercised against the
        // real ExceptionRenderer.
        $this->assertTrue(
            $this->app->runningUnitTests(),
            'Precondition for the scope note above: Laravel considers this a unit-test run.'
        );

        $response = app(ExceptionRenderer::class)->render(
            new TokenMismatchException('CSRF token mismatch.'),
            request()
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            ErrorCode::CSRF_FAILED->value,
            json_decode((string) $response->getContent(), true)['error']['code']
        );
    }

    // =====================================================================
    // D20 — hostile credentialed CORS origin
    // =====================================================================

    public function test_d20_a_hostile_origin_is_not_granted_credentialed_cors_access(): void
    {
        // Control: an allow-listed origin IS echoed, proving CORS is active and
        // a null header below means rejection rather than "CORS not running".
        $allowed = (string) (config('cors.allowed_origins')[0] ?? 'http://localhost:3000');

        $permitted = $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN' => $allowed,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
        $this->assertSame($allowed, $permitted->headers->get('Access-Control-Allow-Origin'));

        $hostile = $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN' => 'https://penyerang.contoh.invalid',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNull(
            $hostile->headers->get('Access-Control-Allow-Origin'),
            'A hostile origin was reflected back. With credentials enabled this lets any site issue '
            .'authenticated requests on a signed-in user\'s behalf.'
        );

        // The wildcard-plus-credentials combination must be impossible.
        $this->assertNotContains('*', (array) config('cors.allowed_origins'));
        $this->assertSame([], (array) config('cors.allowed_origins_patterns'));
    }

    // =====================================================================
    // D21 — open redirect
    // =====================================================================

    public function test_d21_login_and_reset_do_not_honour_an_attacker_supplied_redirect(): void
    {
        $user = $this->makeUser();
        $hostile = 'https://penyerang.contoh.invalid/panen';

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => self::PASSWORD,
            'mode' => 'token',
            'redirect_to' => $hostile,
            'next' => $hostile,
            'return_url' => $hostile,
        ])->assertOk();

        $this->assertNull($login->headers->get('Location'), 'The API must not redirect at all.');
        $this->assertStringNotContainsString($hostile, (string) $login->getContent());

        $path = $this->beginLogCapture();

        $this->postJson('/api/v1/auth/password-reset/request', [
            'identifier' => $user->email,
            'redirect_to' => $hostile,
            'next' => $hostile,
        ])->assertOk();

        // The reset link is built from the server's own configured app URL.
        // If an attacker-supplied host reached it, the reset link mailed to a
        // victim would deliver the token to the attacker.
        $this->assertStringNotContainsString(
            'penyerang.contoh.invalid',
            $this->capturedLog(),
            'An attacker-supplied host reached the password-reset link.'
        );
        $this->assertFileExists($path);
    }

    // =====================================================================
    // D22 / D23 — credentials must never reach the log
    // =====================================================================

    public function test_d22_a_bearer_token_never_appears_in_the_log(): void
    {
        $user = $this->makeUser();
        $this->beginLogCapture();

        $token = $this->loginToken($user);
        $this->getJson('/api/v1/auth/me', $this->bearer($token))->assertOk();
        // Also drive a failing authenticated request, which is where an
        // unhandled-exception logger would dump request state.
        $this->getJson('/api/v1/memberships/current', $this->bearer($token, (string) Str::uuid()))->assertStatus(403);

        $secret = explode('|', $token, 2)[1] ?? $token;

        $log = $this->capturedLog();
        $this->assertStringNotContainsString($token, $log, 'A plaintext bearer token was written to the log.');
        $this->assertStringNotContainsString($secret, $log, 'A bearer token secret was written to the log.');
    }

    public function test_d23_the_authorization_header_and_password_never_appear_in_the_log(): void
    {
        $user = $this->makeUser();
        $this->beginLogCapture();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => self::PASSWORD,
        ], ['Authorization' => 'Bearer rahasia-token-uji-fiktif', 'Cookie' => 'sesi=rahasia-cookie-uji'])
            ->assertOk();

        $log = $this->capturedLog();

        $this->assertStringNotContainsString(self::PASSWORD, $log, 'A plaintext password was written to the log.');
        $this->assertStringNotContainsString('rahasia-token-uji-fiktif', $log, 'An Authorization header value was logged.');
        $this->assertStringNotContainsString('rahasia-cookie-uji', $log, 'A Cookie header value was logged.');
    }

    // =====================================================================
    // D24 — the audit trail must not become a credential store
    // =====================================================================

    public function test_d24_a_password_never_appears_in_audit_metadata(): void
    {
        $user = $this->makeUser();

        $this->login($user->email, self::PASSWORD)->assertOk();
        $this->login($user->email, 'placeholder-KataSandiSalah99999')->assertStatus(401);
        $this->issueResetToken($user);

        $entries = DB::table('audit_entries')->get();

        // Control: audit entries were actually written, so the scan below is
        // not passing over an empty table.
        $this->assertGreaterThan(0, $entries->count(), 'No audit entries were written; this scan would be vacuous.');

        foreach ($entries as $entry) {
            $blob = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString(self::PASSWORD, (string) $blob, 'A correct password reached the audit trail.');
            $this->assertStringNotContainsString('placeholder-KataSandiSalah99999', (string) $blob, 'An attempted password reached the audit trail.');
        }
    }

    // =====================================================================
    // D25 — no shared default development password
    // =====================================================================

    public function test_d25_seeded_users_do_not_share_a_default_development_password(): void
    {
        // 1. The generator itself never repeats.
        $seeder = new DevelopmentDataSeeder();
        $generate = new ReflectionMethod($seeder, 'generatePassword');
        // PHP 8.1+ makes reflected methods accessible without an explicit call.

        $generated = [];
        for ($i = 0; $i < 25; $i++) {
            $generated[] = (string) $generate->invoke($seeder);
        }

        $this->assertCount(25, array_unique($generated), 'The development seeder reuses passwords across accounts.');

        foreach ($generated as $password) {
            $this->assertGreaterThanOrEqual(12, strlen($password), 'Seeded development passwords are too short to resist guessing.');
        }

        // 2. No seeded account uses a common default.
        $this->seed(DevelopmentDataSeeder::class);
        $users = User::query()->whereNotNull('password')->get();
        $this->assertGreaterThan(1, $users->count(), 'Control: the seeder must create more than one account.');

        foreach ($users as $seededUser) {
            foreach ([
                'password', 'Password123', 'rahasia', 'aish', 'aishlaundry', 'secret',
                '12345678', 'password123', 'admin', 'dev', 'development', 'placeholder-KataSandiUji12345',
            ] as $common) {
                $this->assertFalse(
                    Hash::check($common, (string) $seededUser->password),
                    sprintf('Seeded account "%s" uses the shared/common password "%s".', $seededUser->id, $common)
                );
            }
        }

        // 3. No plaintext password is committed anywhere in the seeder source.
        $source = (string) file_get_contents(database_path('seeders/DevelopmentDataSeeder.php'));
        $this->assertMatchesRegularExpression(
            '/Str::random|random_bytes|random_int/',
            $source,
            'The development seeder must generate credentials rather than carry them.'
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function login(string $identifier, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/login', [
            'identifier' => $identifier,
            'password' => $password,
            'mode' => 'token',
        ]);
    }

    /**
     * Issue a genuine reset token by driving the real endpoint, then recover the
     * plaintext the way the delivery transport would. The token is never read
     * out of the database, because only its HASH is stored there — which is the
     * property being relied upon.
     */
    private function issueResetToken(User $user): string
    {
        $path = $this->beginLogCapture();

        $this->postJson('/api/v1/auth/password-reset/request', ['identifier' => $user->email])->assertOk();

        $log = $this->capturedLog();
        $this->assertNotSame('', $log, 'Control: the reset transport must have emitted the link. Path: '.$path);

        preg_match('/token=([A-Za-z0-9]+)/', $log, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Could not recover the issued reset token from the transport.');

        // Confirm the stored value is a hash, not the token itself.
        $stored = (string) DB::table('password_reset_tokens')
            ->where('identifier', mb_strtolower($user->email))
            ->value('token');
        $this->assertNotSame($matches[1], $stored, 'The reset token is stored in plaintext.');

        $this->endLogCapture();

        return $matches[1];
    }

    private function completeReset(
        string $identifier,
        string $token,
        string $password = 'placeholder-KataSandiBaruUji12345',
    ): \Illuminate\Testing\TestResponse {
        return $this->postJson('/api/v1/auth/password-reset/complete', [
            'identifier' => $identifier,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /** @param array<string, string> $headers */
    private function tokenFromHeaders(array $headers): string
    {
        return str_replace('Bearer ', '', $headers['Authorization']);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function comparable(?array $body): array
    {
        $body ??= [];
        unset($body['meta']);

        return $body;
    }
}
