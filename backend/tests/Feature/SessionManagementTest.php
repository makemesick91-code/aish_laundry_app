<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Audit\AuditAction;
use App\Modules\Identity\Models\AccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * Happy paths for session self-service: list, revoke one, revoke the others.
 *
 * Every query in the controller is scoped to the authenticated user, so a
 * session belonging to somebody else is NOT FOUND rather than forbidden.
 */
final class SessionManagementTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    public function test_a_user_lists_their_own_sessions_with_the_current_one_flagged(): void
    {
        $user = $this->makeUser();

        $first = $this->loginToken($user);
        $this->loginToken($user);

        $response = $this->getJson('/api/v1/sessions', $this->bearer($first))->assertOk();

        $sessions = $response->json('data.sessions');
        $this->assertCount(2, $sessions);

        $current = array_values(array_filter($sessions, fn (array $s): bool => $s['is_current'] === true));
        $this->assertCount(1, $current, 'Tepat satu sesi harus ditandai sebagai sesi berjalan.');

        // The listing identifies a session by row id. The credential itself was
        // handed over once and cannot be reproduced.
        foreach ($sessions as $session) {
            $this->assertArrayNotHasKey('token', $session);
        }
    }

    public function test_the_session_listing_never_exposes_another_users_session(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser(email: 'lain@contoh.invalid');

        $token = $this->loginToken($user);
        $this->loginToken($other);

        $sessions = $this->getJson('/api/v1/sessions', $this->bearer($token))
            ->assertOk()
            ->json('data.sessions');

        $this->assertCount(1, $sessions);
    }

    public function test_a_user_revokes_one_of_their_own_sessions(): void
    {
        $user = $this->makeUser();

        $current = $this->loginToken($user);
        $doomedSession = $this->loginSession($user);
        $doomed = $doomedSession['token'];

        $this->deleteJson('/api/v1/sessions/'.$doomedSession['id'], [], $this->bearer($current))
            ->assertOk()
            ->assertJsonPath('data.revoked', true);

        // The revoked credential is refused, and told why.
        $this->getJson('/api/v1/auth/me', $this->bearer($doomed))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_REVOKED');

        // The session used to perform the revocation still works.
        $this->getJson('/api/v1/auth/me', $this->bearer($current))->assertOk();

        $this->assertDatabaseHas('audit_entries', [
            'action' => AuditAction::AUTH_SESSION_REVOKED,
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_revoking_another_users_session_reports_not_found(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser(email: 'lain@contoh.invalid');

        $token = $this->loginToken($user);
        $this->loginToken($other);

        $foreignId = AccessToken::query()
            ->where('tokenable_id', $other->id)
            ->firstOrFail()
            ->getKey();

        // NOT FOUND, not FORBIDDEN: the query never had access to the row, so
        // the response reveals nothing about whether it exists.
        $this->deleteJson('/api/v1/sessions/'.$foreignId, [], $this->bearer($token))
            ->assertStatus(404);

        $this->assertNull(AccessToken::query()->whereKey($foreignId)->firstOrFail()->revoked_at);
    }

    public function test_revoke_others_spares_the_current_session(): void
    {
        $user = $this->makeUser();

        $keep = $this->loginToken($user);
        $dropA = $this->loginToken($user);
        $dropB = $this->loginToken($user);

        $this->postJson('/api/v1/sessions/revoke-others', [], $this->bearer($keep))
            ->assertOk()
            ->assertJsonPath('data.revoked', true)
            ->assertJsonPath('data.sessions_revoked', 2);

        $this->getJson('/api/v1/auth/me', $this->bearer($keep))->assertOk();

        foreach ([$dropA, $dropB] as $revoked) {
            $this->getJson('/api/v1/auth/me', $this->bearer($revoked))
                ->assertStatus(401)
                ->assertJsonPath('error.code', 'SESSION_REVOKED');
        }
    }

    public function test_an_expired_token_is_reported_as_expired_not_merely_unauthenticated(): void
    {
        $user = $this->makeUser();
        $token = $this->loginToken($user);

        AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->update(['expires_at' => now()->subDay()]);

        $this->getJson('/api/v1/auth/me', $this->bearer($token))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_a_garbage_token_is_indistinguishable_from_no_credential(): void
    {
        $noCredential = $this->getJson('/api/v1/auth/me');
        $garbage = $this->getJson('/api/v1/auth/me', $this->bearer('bukan-token-yang-sah'));

        $noCredential->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $garbage->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
