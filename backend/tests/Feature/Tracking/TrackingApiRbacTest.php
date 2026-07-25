<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Notification\Services\NotificationIntentService;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Tracking\Services\TrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT H — THE INTERNAL SURFACE: RBAC AND TENANT ISOLATION.
 *
 * Two claims are under test and they are different claims.
 *
 * RBAC: a role without the permission cannot perform the action, and the split
 * between view and manage/send is real rather than decorative.
 *
 * ISOLATION: a member of tenant A cannot reach a tenant B record through ANY
 * access path that exists — direct ID, list, and the absence of search, export,
 * and file-URL paths — and a denial is indistinguishable from absence (Rule 48).
 */
final class TrackingApiRbacTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
        Carbon::setTestNow(Carbon::parse('2026-07-25 05:00:00', 'UTC')); // midday WIB
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A scenario whose membership carries the given role, with an API session.
     *
     * @return array<string, mixed>
     */
    private function actor(string $slug, string $roleKey): array
    {
        $s = $this->trackingScenario($slug);
        $this->grantRole($s['context']->membership, $roleKey);

        $user = $s['context']->membership->user;
        $token = $this->loginToken($user);

        return $s + [
            'headers' => $this->bearer($token, $s['context']->tenantId()),
            'user' => $user,
        ];
    }

    // =====================================================================
    // RBAC — the permission split is real
    // =====================================================================

    public function test_a_cashier_may_issue_read_rotate_and_revoke_a_tracking_link(): void
    {
        $a = $this->actor('rbac-cashier', PermissionRegistry::ROLE_CASHIER);

        // Handing a customer their link at the counter is the kasir's job
        // (TRACKING_ACCESS_LIFECYCLE K-01).
        $created = $this->postJson(
            "/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $this->ref()],
            $a['headers'],
        )->assertStatus(201);

        $tokenId = $created->json('data.tracking_link.id');

        $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])->assertOk();

        $this->postJson("/api/v1/tracking-links/{$tokenId}/rotate", [
            'reason_code' => 'over_shared',
            'client_reference' => $this->ref(),
        ], $a['headers'])->assertOk();

        $rotatedId = $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])
            ->json('data.tracking_link.id');

        $this->postJson("/api/v1/tracking-links/{$rotatedId}/revoke", [
            'reason_code' => 'lost',
        ], $a['headers'])->assertOk();
    }

    public function test_a_production_operator_holds_no_tracking_permission(): void
    {
        $a = $this->actor('rbac-operator', PermissionRegistry::ROLE_PRODUCTION_OPERATOR);

        $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])
            ->assertStatus(403);

        $this->postJson("/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $this->ref()], $a['headers'])->assertStatus(403);
    }

    public function test_a_courier_holds_no_tracking_or_notification_permission(): void
    {
        $a = $this->actor('rbac-courier', PermissionRegistry::ROLE_COURIER);

        // Rule 32 hard rule 11: the courier surface shows one assignment and
        // offers no traversal path. A courier able to read a tenant's tracking
        // links or notification history would be exactly that traversal.
        $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])->assertStatus(403);
        $this->getJson("/api/v1/orders/{$a['order']->id}/notifications", $a['headers'])->assertStatus(403);
        $this->getJson('/api/v1/notifications/provider-state', $a['headers'])->assertStatus(403);
    }

    public function test_finance_may_read_but_may_not_manage_or_send(): void
    {
        $a = $this->actor('rbac-finance', PermissionRegistry::ROLE_FINANCE);

        // Finance reports on what messaging costs (NOT-020) without spending the
        // budget or touching a customer's link.
        $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])->assertOk();
        $this->getJson("/api/v1/orders/{$a['order']->id}/notifications", $a['headers'])->assertOk();

        $this->postJson("/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $this->ref()], $a['headers'])->assertStatus(403);
    }

    public function test_the_four_step_7_permissions_are_registered_and_courier_has_none(): void
    {
        $matrix = PermissionRegistry::matrix();

        foreach ([
            PermissionRegistry::TRACKING_VIEW,
            PermissionRegistry::TRACKING_MANAGE,
            PermissionRegistry::NOTIFICATION_VIEW,
            PermissionRegistry::NOTIFICATION_SEND,
        ] as $permission) {
            $this->assertArrayHasKey($permission, PermissionRegistry::permissions());

            $this->assertNotContains($permission, $matrix[PermissionRegistry::ROLE_COURIER]);
            $this->assertNotContains($permission, $matrix[PermissionRegistry::ROLE_CUSTOMER]);
            $this->assertNotContains($permission, $matrix[PermissionRegistry::ROLE_PLATFORM_SUPPORT]);
            $this->assertNotContains($permission, $matrix[PermissionRegistry::ROLE_PLATFORM_SUPER_ADMIN]);
        }

        // Finance reads and does not send — the split is real, not decorative.
        $this->assertContains(PermissionRegistry::NOTIFICATION_VIEW, $matrix[PermissionRegistry::ROLE_FINANCE]);
        $this->assertNotContains(PermissionRegistry::NOTIFICATION_SEND, $matrix[PermissionRegistry::ROLE_FINANCE]);
        $this->assertNotContains(PermissionRegistry::TRACKING_MANAGE, $matrix[PermissionRegistry::ROLE_FINANCE]);
    }

    public function test_an_unauthenticated_caller_is_refused_on_every_internal_route(): void
    {
        $s = $this->trackingScenario('rbac-unauthenticated');

        $this->getJson("/api/v1/orders/{$s['order']->id}/tracking-link")->assertStatus(401);
        $this->postJson("/api/v1/orders/{$s['order']->id}/tracking-link",
            ['client_reference' => $this->ref()])->assertStatus(401);
        $this->getJson("/api/v1/orders/{$s['order']->id}/notifications")->assertStatus(401);
    }

    public function test_a_suspended_membership_loses_access_immediately(): void
    {
        $a = $this->actor('rbac-suspended', PermissionRegistry::ROLE_CASHIER);

        $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])->assertOk();

        $a['context']->membership->markSuspended();

        // Not at next login, not after a token expires — on the very next request.
        $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])
            ->assertStatus(403);
    }

    // =====================================================================
    // Tenant isolation — every path that exists
    // =====================================================================

    public function test_a_foreign_order_is_indistinguishable_from_an_absent_one(): void
    {
        $a = $this->actor('iso-a', PermissionRegistry::ROLE_CASHIER);
        $b = $this->trackingScenario('iso-b');

        $foreign = $this->getJson("/api/v1/orders/{$b['order']->id}/tracking-link", $a['headers']);
        $absent = $this->getJson('/api/v1/orders/'.Str::uuid().'/tracking-link', $a['headers']);

        $foreign->assertStatus(404);
        $absent->assertStatus(404);

        $this->assertSame(
            $foreign->json('error.code'),
            $absent->json('error.code'),
            'A denial must not reveal that a record exists in another tenant (Rule 48 hard rule 5).'
        );
    }

    public function test_a_foreign_tracking_link_cannot_be_rotated_or_revoked(): void
    {
        $a = $this->actor('iso-rotate-a', PermissionRegistry::ROLE_CASHIER);
        $b = $this->trackingScenario('iso-rotate-b');

        $issuedB = app(TrackingTokenService::class)->issue($b['context'], $b['order'], $this->ref());

        $this->postJson("/api/v1/tracking-links/{$issuedB->token->id}/rotate", [
            'reason_code' => 'over_shared',
            'client_reference' => $this->ref(),
        ], $a['headers'])->assertStatus(404);

        $this->postJson("/api/v1/tracking-links/{$issuedB->token->id}/revoke", [
            'reason_code' => 'lost',
        ], $a['headers'])->assertStatus(404);

        // And tenant B's link is untouched by the attempt.
        $this->assertSame('ISSUED',
            DB::table('tracking_tokens')->where('id', $issuedB->token->id)->value('state'));
    }

    public function test_a_foreign_notification_is_not_readable_or_retryable(): void
    {
        $a = $this->actor('iso-notif-a', PermissionRegistry::ROLE_CASHIER);
        $b = $this->trackingScenario('iso-notif-b');

        $intentB = app(NotificationIntentService::class)
            ->enqueue($b['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->getJson("/api/v1/notifications/{$intentB->id}", $a['headers'])->assertStatus(404);
        $this->postJson("/api/v1/notifications/{$intentB->id}/retry", [], $a['headers'])->assertStatus(404);
        $this->postJson("/api/v1/notifications/{$intentB->id}/manual-link", [], $a['headers'])->assertStatus(404);
    }

    public function test_a_notification_list_never_includes_another_tenants_rows(): void
    {
        $a = $this->actor('iso-list-a', PermissionRegistry::ROLE_CASHIER);
        $b = $this->trackingScenario('iso-list-b');

        app(NotificationIntentService::class)
            ->enqueue($a['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        app(NotificationIntentService::class)
            ->enqueue($b['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $response = $this->getJson("/api/v1/orders/{$a['order']->id}/notifications", $a['headers'])->assertOk();

        $this->assertCount(1, $response->json('data.notifications'));
    }

    public function test_a_client_supplied_tenant_id_is_never_authorization_proof(): void
    {
        $a = $this->actor('iso-header-a', PermissionRegistry::ROLE_CASHIER);
        $b = $this->trackingScenario('iso-header-b');

        // Claiming tenant B in the header, holding only a tenant A membership.
        $token = $this->loginToken($a['user']);
        $spoofed = $this->bearer($token, $b['context']->tenantId());

        $this->getJson("/api/v1/orders/{$b['order']->id}/tracking-link", $spoofed)
            ->assertStatus(403);
    }

    // =====================================================================
    // Absent-by-design surfaces
    // =====================================================================

    public function test_there_is_no_list_all_tokens_route_and_no_export_route(): void
    {
        // Either would be an enumeration surface over a tenant's live customer
        // credentials. Asserted against the ROUTE TABLE, because a URL probe
        // returning 404 could mean "no such route" or "route exists, record
        // absent" — and only one of those supports this claim.
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_contains($uri, 'tracking') && ! str_contains($uri, 'notification')) {
                continue;
            }

            $this->assertStringNotContainsString('export', $uri);
            $this->assertStringNotContainsString('bulk', $uri);

            // A bare collection route with no order scope would list every token
            // in the tenant.
            $this->assertNotSame('api/v1/tracking-links', $uri);
        }
    }

    public function test_no_endpoint_returns_the_token_after_issuance(): void
    {
        $a = $this->actor('iso-no-token-read', PermissionRegistry::ROLE_CASHIER);

        $created = $this->postJson("/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $this->ref()], $a['headers'])->assertStatus(201);

        $url = $created->json('data.url');
        $this->assertNotNull($url);
        $plaintext = substr($url, strrpos($url, '/') + 1);

        // The one and only time it is returned. Afterwards nothing can produce it,
        // because only the hash was ever stored (TRK-002, TRK-019).
        $read = $this->getJson("/api/v1/orders/{$a['order']->id}/tracking-link", $a['headers'])->assertOk();

        $body = (string) $read->getContent();
        $this->assertStringNotContainsString($plaintext, $body);
        $this->assertStringNotContainsString('token_hash', $body);
        $this->assertStringNotContainsString(hash('sha256', $plaintext), $body);
    }

    public function test_revocation_requires_a_reason_code(): void
    {
        $a = $this->actor('iso-reason-required', PermissionRegistry::ROLE_CASHIER);

        $created = $this->postJson("/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $this->ref()], $a['headers'])->assertStatus(201);

        // Knowing WHY a link was revoked distinguishes a lost link from a leaked
        // one (TRACKING_ACCESS_LIFECYCLE §9).
        $this->postJson("/api/v1/tracking-links/{$created->json('data.tracking_link.id')}/revoke",
            [], $a['headers'])->assertStatus(422);
    }

    public function test_issuing_is_idempotent_on_client_reference(): void
    {
        $a = $this->actor('iso-idempotent', PermissionRegistry::ROLE_CASHIER);
        $reference = $this->ref();

        $this->postJson("/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $reference], $a['headers'])->assertStatus(201);

        // A replayed command must not silently mint a second live link.
        $this->postJson("/api/v1/orders/{$a['order']->id}/tracking-link",
            ['client_reference' => $reference], $a['headers'])->assertStatus(409);

        $this->assertSame(1, DB::table('tracking_tokens')
            ->where('order_id', $a['order']->id)->count());
    }

    public function test_the_provider_state_endpoint_leaks_no_configuration(): void
    {
        $a = $this->actor('iso-provider-state', PermissionRegistry::ROLE_CASHIER);

        $body = (string) $this->getJson('/api/v1/notifications/provider-state', $a['headers'])
            ->assertOk()->getContent();

        foreach (['access_token', 'base_url', 'phone_number_id', 'Bearer'] as $secret) {
            $this->assertStringNotContainsString($secret, $body,
                'A status endpoint that leaked configuration would be a configuration disclosure (Rule 03).');
        }
    }
}
