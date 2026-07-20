<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Audit\AuditAction;
use App\Modules\Identity\Models\AccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * Password reset over a LOCAL transport only.
 *
 * Step 3 introduces no third-party service: the reset link is written to a log
 * channel, which is honest about what it is rather than pretending to be
 * delivery. Adding an email or WhatsApp provider needs owner approval and a
 * decision record (Rule 12).
 */
final class PasswordResetTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    public function test_a_reset_request_for_a_known_account_stores_only_a_hashed_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/password-reset/request', [
            'identifier' => $user->email,
        ])->assertOk();

        $record = DB::table('password_reset_tokens')->first();

        $this->assertNotNull($record);

        // The plaintext lives only in the delivered link. What is stored must be
        // a hash, never a value that could be replayed straight from the table.
        $this->assertNotSame('', (string) $record->token);
        $this->assertStringStartsWith('$2y$', (string) $record->token);

        $this->assertDatabaseHas('audit_entries', [
            'action' => AuditAction::AUTH_PASSWORD_RESET_REQUESTED,
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_a_reset_request_is_identical_for_known_and_unknown_accounts(): void
    {
        $user = $this->makeUser();

        $known = $this->postJson('/api/v1/auth/password-reset/request', [
            'identifier' => $user->email,
        ]);

        $unknown = $this->postJson('/api/v1/auth/password-reset/request', [
            'identifier' => 'tidak.terdaftar@contoh.invalid',
        ]);

        $known->assertOk();
        $unknown->assertOk();

        // Byte-for-byte identical payloads. Any difference — a field, a word, a
        // status — is a user-enumeration oracle.
        $this->assertSame($known->json('data'), $unknown->json('data'));

        // And nothing was written for the account that does not exist.
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }

    public function test_a_reset_completes_and_the_new_password_works(): void
    {
        $user = $this->makeUser();
        $plainToken = $this->issueResetToken($user->email);

        $this->postJson('/api/v1/auth/password-reset/complete', [
            'identifier' => $user->email,
            'token' => $plainToken,
            'password' => 'KataSandiBaru98765',
            'password_confirmation' => 'KataSandiBaru98765',
        ])->assertOk();

        // The new credential authenticates.
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'KataSandiBaru98765',
        ])->assertOk();

        // The old one does not.
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'placeholder-KataSandiUji12345',
        ])->assertStatus(401);

        // The token is single-use: the row is consumed on completion.
        $this->assertSame(0, DB::table('password_reset_tokens')->count());

        $this->assertDatabaseHas('audit_entries', [
            'action' => AuditAction::AUTH_PASSWORD_RESET_COMPLETED,
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_completing_a_reset_revokes_every_existing_session(): void
    {
        $user = $this->makeUser();

        $existing = $this->loginToken($user);
        $this->getJson('/api/v1/auth/me', $this->bearer($existing))->assertOk();

        $plainToken = $this->issueResetToken($user->email);

        $this->postJson('/api/v1/auth/password-reset/complete', [
            'identifier' => $user->email,
            'token' => $plainToken,
            'password' => 'KataSandiBaru98765',
            'password_confirmation' => 'KataSandiBaru98765',
        ])->assertOk();

        // Someone resetting a password often believes they are compromised.
        // Leaving the attacker's session alive would make the reset cosmetic.
        $this->getJson('/api/v1/auth/me', $this->bearer($existing))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REVOKED');

        $this->assertSame(
            0,
            AccessToken::query()->where('tokenable_id', $user->id)->whereNull('revoked_at')->count()
        );
    }

    public function test_a_wrong_reset_token_fails_generically(): void
    {
        $user = $this->makeUser();
        $this->issueResetToken($user->email);

        $this->postJson('/api/v1/auth/password-reset/complete', [
            'identifier' => $user->email,
            'token' => Str::random(64),
            'password' => 'KataSandiBaru98765',
            'password_confirmation' => 'KataSandiBaru98765',
        ])->assertStatus(422);

        // The original password still works: a failed reset changes nothing.
        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'placeholder-KataSandiUji12345',
        ])->assertOk();
    }

    public function test_repeated_reset_requests_are_rate_limited(): void
    {
        $user = $this->makeUser();

        $sawRateLimit = false;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $response = $this->postJson('/api/v1/auth/password-reset/request', [
                'identifier' => $user->email,
            ]);

            if ($response->json('error.code') === 'RATE_LIMITED') {
                $sawRateLimit = true;
                break;
            }
        }

        $this->assertTrue(
            $sawRateLimit,
            'Permintaan atur ulang kata sandi harus dibatasi; tanpa itu endpoint ini menjadi alat enumerasi.'
        );
    }

    /**
     * Drive the real request endpoint, then plant a token whose plaintext this
     * test knows. The endpoint stores only a hash and delivers the plaintext to
     * a log channel, so a test cannot read the issued value back — planting the
     * row is how the completion path is exercised without weakening the code
     * that keeps the plaintext out of reach.
     */
    private function issueResetToken(string $identifier): string
    {
        $this->postJson('/api/v1/auth/password-reset/request', [
            'identifier' => $identifier,
        ])->assertOk();

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')
            ->where('identifier', $identifier)
            ->update(['token' => Hash::make($plainToken), 'created_at' => now()]);

        return $plainToken;
    }
}
