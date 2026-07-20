<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\SharedKernel\Cache\TenantCacheKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Redis key partitioning.
 *
 * Rule 06 hard rule 13: "Every cache key carries a tenant dimension. A
 * tenant-less cache key is a cross-tenant leak." The builder THROWS rather than
 * degrading, so the failure lands at the call site in development instead of
 * silently becoming a shared key in production.
 */
final class TenantCacheKeyTest extends TestCase
{
    public function test_a_tenant_key_carries_its_tenant_and_is_classified_as_tenant_scoped(): void
    {
        $key = TenantCacheKey::forTenant('tenant-aaa', 'permissions', ['membership-1']);

        $this->assertStringContainsString('tenant-aaa', $key);
        // The two key classes are named differently on purpose, so a reader can
        // tell at a glance whether a key may hold tenant data.
        $this->assertStringStartsWith('t:', $key);
    }

    public function test_two_tenants_never_produce_the_same_key(): void
    {
        $a = TenantCacheKey::forTenant('tenant-aaa', 'permissions', ['membership-1']);
        $b = TenantCacheKey::forTenant('tenant-bbb', 'permissions', ['membership-1']);

        $this->assertNotSame($a, $b);
    }

    public function test_a_null_tenant_throws_rather_than_degrading(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantCacheKey::forTenant(null, 'permissions', ['membership-1']);
    }

    public function test_an_empty_tenant_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantCacheKey::forTenant('', 'permissions', ['membership-1']);
    }

    public function test_a_whitespace_only_tenant_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantCacheKey::forTenant('   ', 'permissions', ['membership-1']);
    }

    public function test_a_global_key_is_classified_distinctly_from_a_tenant_key(): void
    {
        $global = TenantCacheKey::global('role_catalogue');

        $this->assertStringStartsNotWith('t:', $global);
    }

    public function test_rate_limit_keys_do_not_merge_unrelated_users(): void
    {
        $userA = TenantCacheKey::rateLimit('tenant-aaa', 'pengguna-a', 'login');
        $userB = TenantCacheKey::rateLimit('tenant-aaa', 'pengguna-b', 'login');

        $this->assertNotSame(
            $userA,
            $userB,
            'Dua pengguna berbeda tidak boleh berbagi penghitung pembatas laju.'
        );
    }

    public function test_rate_limit_keys_do_not_merge_unrelated_tenants(): void
    {
        $inA = TenantCacheKey::rateLimit('tenant-aaa', 'pengguna-sama', 'login');
        $inB = TenantCacheKey::rateLimit('tenant-bbb', 'pengguna-sama', 'login');

        $this->assertNotSame($inA, $inB);
    }

    public function test_different_actions_do_not_share_a_counter(): void
    {
        $login = TenantCacheKey::rateLimit('tenant-aaa', 'pengguna-sama', 'login');
        $reset = TenantCacheKey::rateLimit('tenant-aaa', 'pengguna-sama', 'password_reset');

        $this->assertNotSame($login, $reset);
    }

    public function test_pre_auth_rate_limit_separates_identifiers_and_addresses(): void
    {
        // Before authentication there is no tenant, so these keys are explicitly
        // global — and must still not merge unrelated subjects.
        $identifierA = TenantCacheKey::preAuthRateLimit('login', 'a@contoh.invalid', '203.0.113.1');
        $identifierB = TenantCacheKey::preAuthRateLimit('login', 'b@contoh.invalid', '203.0.113.1');

        $this->assertNotSame($identifierA, $identifierB);

        $addressA = TenantCacheKey::ipRateLimit('login', '203.0.113.1');
        $addressB = TenantCacheKey::ipRateLimit('login', '203.0.113.2');

        $this->assertNotSame($addressA, $addressB);
    }

    public function test_a_pre_auth_key_does_not_embed_the_raw_identifier(): void
    {
        $key = TenantCacheKey::preAuthRateLimit('login', 'rahasia@contoh.invalid', '203.0.113.1');

        // The identifier is an account name. A Redis key is readable by anyone
        // with access to the instance, so the raw value has no business in it.
        $this->assertStringNotContainsString('rahasia@contoh.invalid', $key);
    }
}
