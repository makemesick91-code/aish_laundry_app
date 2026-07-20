<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\SharedKernel\Cache\TenantCacheKey;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;
use Throwable;

/**
 * MATRIX E — Redis tenant partitioning.
 *
 * A cache key without a tenant dimension is a cross-tenant leak waiting for a
 * cache hit (Rule 06, hard rule 13). Two tenants sharing a keyspace is the same
 * failure as two tenants sharing a table, except it produces no foreign key to
 * catch it and no row to audit afterwards.
 *
 * WHY PART OF THIS MATRIX TALKS TO A REAL REDIS
 * ---------------------------------------------
 * `phpunit.xml` sets `CACHE_STORE=array`, deliberately and correctly: rate-limit
 * counters living in a shared Redis would persist between tests and between the
 * suite and a developer's running application, so one throttle test would lock
 * out every test after it.
 *
 * But that isolation has a cost that must not go unstated: an in-memory array
 * store is a PHP array. It cannot demonstrate that two tenants occupy different
 * Redis keyspaces, because it has no keyspace. Verifying partitioning only
 * in-memory would be the same category of error as verifying tenant isolation
 * on SQLite.
 *
 * So this matrix is explicitly split, and the report says which half is which:
 *
 *   E1–E5  — the key BUILDER, exercised directly. No backend involved.
 *   E6–E8  — REAL Redis at the configured host, written and read for real.
 *
 * The real-Redis cases confine every write to a per-run random prefix and
 * delete it afterwards, so the suite never disturbs development data.
 */
final class RedisTenantPartitioningTest extends TestCase
{
    /** Namespaces every real-Redis key in this test under one deletable prefix. */
    private string $probePrefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->probePrefix = 'aish_uji:'.Str::lower(Str::random(12)).':';
    }

    protected function tearDown(): void
    {
        $this->forgetProbeKeys();
        parent::tearDown();
    }

    // =====================================================================
    // E1–E5 — the key builder (no backend)
    // =====================================================================

    public function test_e1_two_tenants_produce_different_namespaces(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        $keyA = TenantCacheKey::forTenant($tenantA, 'membership', ['effective_permissions']);
        $keyB = TenantCacheKey::forTenant($tenantB, 'membership', ['effective_permissions']);

        $this->assertNotSame($keyA, $keyB, 'Two tenants produced the same cache key — one keyspace for two tenants.');

        // The tenant is a leading, structural part of the key rather than a
        // suffix somewhere in the middle: a prefix scan for one tenant can
        // never reach another tenant's entries.
        $this->assertStringStartsWith('t:'.$tenantA, $keyA);
        $this->assertStringStartsWith('t:'.$tenantB, $keyB);
        $this->assertStringNotContainsString($tenantB, $keyA);
        $this->assertStringNotContainsString($tenantA, $keyB);
    }

    public function test_e2_omitting_the_tenant_id_from_a_tenant_data_key_throws(): void
    {
        // Control: with a tenant id the builder works.
        $this->assertNotSame('', TenantCacheKey::forTenant((string) Str::uuid(), 'membership'));

        // Violation: every falsy-ish way of "forgetting" the tenant fails
        // CLOSED. Returning a tenant-less key would silently merge tenants.
        foreach ([null, '', '   '] as $missing) {
            $threw = false;

            try {
                TenantCacheKey::forTenant($missing, 'membership');
            } catch (InvalidArgumentException) {
                $threw = true;
            }

            $this->assertTrue($threw, sprintf(
                'A tenant-scoped key was built with a missing tenant id (%s). It must throw, not degrade.',
                var_export($missing, true)
            ));
        }
    }

    public function test_e3_an_invalid_tenant_context_fails_closed(): void
    {
        // An unclassified namespace cannot be smuggled through the global
        // builder to escape tenant scoping.
        $this->expectException(InvalidArgumentException::class);
        TenantCacheKey::global('membership');
    }

    public function test_e4_global_keys_cannot_carry_tenant_data(): void
    {
        // Control: a genuinely global namespace is accepted.
        $this->assertStringStartsWith('g:role_catalogue', TenantCacheKey::global('role_catalogue'));

        // Violation: anything that looks like tenant data is refused, so
        // "classify it global" is never the path of least resistance.
        foreach (['membership', 'customer', 'order', 'outlet', 'audit', 'tenant'] as $tenantNamespace) {
            $threw = false;

            try {
                TenantCacheKey::global($tenantNamespace);
            } catch (InvalidArgumentException) {
                $threw = true;
            }

            $this->assertTrue($threw, sprintf(
                'Namespace "%s" was accepted as global. Adding a global namespace must be a deliberate '
                .'classification decision, not a convenience.',
                $tenantNamespace
            ));
        }

        // Global and tenant keyspaces are disjoint by prefix.
        $this->assertStringStartsWith('g:', TenantCacheKey::global('health'));
        $this->assertStringStartsWith('t:', TenantCacheKey::forTenant((string) Str::uuid(), 'membership'));
    }

    public function test_e5_rate_limit_keys_do_not_merge_unrelated_users_or_tenants(): void
    {
        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $keys = [
            'A/userA' => TenantCacheKey::rateLimit($tenantA, $userA, 'login'),
            'A/userB' => TenantCacheKey::rateLimit($tenantA, $userB, 'login'),
            'B/userA' => TenantCacheKey::rateLimit($tenantB, $userA, 'login'),
            'B/userB' => TenantCacheKey::rateLimit($tenantB, $userB, 'login'),
            'A/userA/other-action' => TenantCacheKey::rateLimit($tenantA, $userA, 'password_reset'),
        ];

        // Every combination is distinct. A merged counter means one tenant's
        // traffic can lock out another tenant's staff — a cross-tenant denial
        // of service that looks like a bug in the victim's own account.
        $this->assertCount(count($keys), array_unique($keys), 'Rate-limit keys collide across tenant, user or action.');

        // Pre-authentication throttles carry no tenant (there is no tenant yet)
        // but must still separate identity and IP, and must never contain the
        // raw identifier.
        $identity = TenantCacheKey::preAuthRateLimit('login', 'orang@contoh.invalid', '203.0.113.10');
        $otherIdentity = TenantCacheKey::preAuthRateLimit('login', 'lain@contoh.invalid', '203.0.113.10');
        $sameIdentityOtherIp = TenantCacheKey::preAuthRateLimit('login', 'orang@contoh.invalid', '203.0.113.99');

        $this->assertNotSame($identity, $otherIdentity);
        $this->assertNotSame($identity, $sameIdentityOtherIp);
        $this->assertStringNotContainsString('orang@contoh.invalid', $identity, 'A raw identifier appears in a cache key.');
        $this->assertStringNotContainsString('203.0.113.10', $identity, 'A raw IP address appears in a cache key.');
    }

    // =====================================================================
    // E6–E8 — REAL Redis
    // =====================================================================

    public function test_e6_real_redis_keeps_two_tenants_in_different_keyspaces(): void
    {
        $redis = $this->realRedis();

        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        $keyA = $this->probePrefix.TenantCacheKey::forTenant($tenantA, 'membership', ['permissions']);
        $keyB = $this->probePrefix.TenantCacheKey::forTenant($tenantB, 'membership', ['permissions']);

        $redis->set($keyA, 'data-tenant-a-fiktif');
        $redis->set($keyB, 'data-tenant-b-fiktif');

        // Control: each tenant reads back its OWN value from a real server.
        $this->assertSame('data-tenant-a-fiktif', $redis->get($keyA));
        $this->assertSame('data-tenant-b-fiktif', $redis->get($keyB));

        // Violation: a read built for tenant A can never surface tenant B's
        // value, because the key it computes is a different key.
        $this->assertNotSame($keyA, $keyB);
        $this->assertNotSame('data-tenant-b-fiktif', $redis->get($keyA));
    }

    public function test_e7_a_real_redis_prefix_scan_for_one_tenant_never_returns_another_tenants_keys(): void
    {
        $redis = $this->realRedis();

        $tenantA = (string) Str::uuid();
        $tenantB = (string) Str::uuid();

        foreach (['permissions', 'outlets', 'profile'] as $segment) {
            $redis->set($this->probePrefix.TenantCacheKey::forTenant($tenantA, 'membership', [$segment]), 'a');
            $redis->set($this->probePrefix.TenantCacheKey::forTenant($tenantB, 'membership', [$segment]), 'b');
        }

        // The realistic attack: an operator or a cache-invalidation routine
        // globs one tenant's namespace. It must be structurally impossible for
        // that glob to reach another tenant.
        $matchedForA = $redis->keys($this->probePrefix.'t:'.$tenantA.':*');

        $this->assertCount(3, $matchedForA, 'Control: the tenant A prefix scan must find tenant A\'s own keys.');

        foreach ($matchedForA as $key) {
            $this->assertStringNotContainsString($tenantB, (string) $key, 'A tenant A prefix scan returned a tenant B key.');
            $this->assertSame('a', $redis->get($this->stripConnectionPrefix((string) $key)));
        }
    }

    public function test_e8_real_redis_rate_limit_counters_do_not_merge_unrelated_users(): void
    {
        $redis = $this->realRedis();

        $tenant = (string) Str::uuid();
        $userA = (string) Str::uuid();
        $userB = (string) Str::uuid();

        $keyA = $this->probePrefix.TenantCacheKey::rateLimit($tenant, $userA, 'login');
        $keyB = $this->probePrefix.TenantCacheKey::rateLimit($tenant, $userB, 'login');

        // User A burns five attempts against a real counter.
        for ($i = 0; $i < 5; $i++) {
            $redis->incr($keyA);
        }

        $this->assertSame(5, (int) $redis->get($keyA), 'Control: user A\'s counter must actually increment.');
        $this->assertNull(
            $redis->get($keyB),
            'User A\'s failed attempts incremented user B\'s throttle counter. One user could lock out another.'
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * A connection to the REAL configured Redis, or a skipped test with a
     * reason. Skipping is the honest outcome when the backend is genuinely
     * unavailable; silently falling back to the array driver and reporting a
     * pass would not be.
     */
    private function realRedis(): \Illuminate\Redis\Connections\Connection
    {
        try {
            $connection = Redis::connection();
            $connection->ping();
        } catch (Throwable $exception) {
            $this->markTestSkipped(sprintf(
                'Real Redis at %s:%s is unavailable (%s). Partitioning was NOT verified against a real server '
                .'in this run, and must not be reported as if it were.',
                (string) config('database.redis.default.host'),
                (string) config('database.redis.default.port'),
                $exception->getMessage()
            ));
        }

        // Guard against a silent in-memory substitution: this matrix is
        // worthless unless it is talking to a real server.
        $this->assertNotSame(
            'array',
            (string) config('database.redis.client'),
            'Redis partitioning must be verified against a real Redis, not an in-memory stand-in.'
        );

        return $connection;
    }

    /**
     * Laravel's Redis connection applies a configured key prefix on write; the
     * keys returned by KEYS come back WITH it. Strip it so a subsequent GET
     * through the same connection does not double-prefix.
     */
    private function stripConnectionPrefix(string $key): string
    {
        $prefix = (string) config('database.redis.options.prefix', '');

        return $prefix !== '' && str_starts_with($key, $prefix)
            ? substr($key, strlen($prefix))
            : $key;
    }

    private function forgetProbeKeys(): void
    {
        try {
            $redis = Redis::connection();
            $keys = $redis->keys($this->probePrefix.'*');

            foreach ($keys as $key) {
                $redis->del($this->stripConnectionPrefix((string) $key));
            }
        } catch (Throwable) {
            // Nothing was written if the connection never opened.
        }
    }
}
