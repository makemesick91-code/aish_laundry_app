<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Cache;

use InvalidArgumentException;

/**
 * THE ONE PLACE A CACHE OR RATE-LIMIT KEY IS BUILT.
 *
 * Rule 06 hard rule 13: "Every cache key carries a tenant dimension. A
 * tenant-less cache key is a cross-tenant leak." Rule 02 design consequence:
 * caches, queues, search indexes and exports are all tenant-scoped.
 *
 * The guarantee here is STRUCTURAL, not conventional. `forTenant()` THROWS when
 * the tenant identifier is missing or blank, so a developer cannot produce a
 * tenant-data key without a tenant — the failure is at the call site, loudly, in
 * development, rather than silently in production as a shared key.
 *
 * KEY CLASSIFICATION
 * ------------------
 * Exactly two classes exist and they are named differently on purpose:
 *
 *   t:{tenant}:{namespace}:{...}   TENANT-SCOPED. May hold tenant data.
 *   g:{namespace}:{...}            GLOBAL / PLATFORM. May NEVER hold tenant data.
 *
 * A reader can tell from the key alone which class a value belongs to, which is
 * what makes an audit of the keyspace possible at all.
 */
final class TenantCacheKey
{
    /** Separator chosen so a key is greppable and never ambiguous. */
    private const SEPARATOR = ':';

    private const TENANT_PREFIX = 't';

    private const GLOBAL_PREFIX = 'g';

    /**
     * Namespaces permitted to carry GLOBAL (non-tenant) data.
     *
     * Deliberately an allowlist. A global key is the one place a cross-tenant
     * leak could hide, so adding a namespace here is a decision someone must
     * make explicitly rather than a side effect of typing a new string.
     */
    private const GLOBAL_NAMESPACES = [
        'health',            // liveness/readiness probe memoisation
        'role_catalogue',    // platform-managed role catalogue (DEC-0025)
        'permission_catalogue', // platform-managed permission catalogue (DEC-0025)
        'throttle_ip',       // pre-authentication, per-IP throttling only
        'throttle_identity', // pre-authentication, per-credential-hash throttling
    ];

    private function __construct()
    {
    }

    /**
     * Build a TENANT-SCOPED key. Throws when the tenant dimension is absent.
     *
     * @param  list<string|int>  $segments
     *
     * @throws InvalidArgumentException when the tenant identifier is missing.
     */
    public static function forTenant(?string $tenantId, string $namespace, array $segments = []): string
    {
        if ($tenantId === null || trim($tenantId) === '') {
            throw new InvalidArgumentException(
                'A tenant-scoped cache key requires a tenant identifier. '
                .'Omitting it would merge two tenants into one keyspace (Rule 02, Rule 06 hard rule 13). '
                .'If this value is genuinely not tenant data, use TenantCacheKey::global() and classify it explicitly.'
            );
        }

        return implode(self::SEPARATOR, array_merge(
            [self::TENANT_PREFIX, self::sanitise($tenantId), self::sanitise($namespace)],
            array_map(self::sanitise(...), $segments)
        ));
    }

    /**
     * Build a GLOBAL / PLATFORM key.
     *
     * The namespace must be on the allowlist. This is the mechanism that stops a
     * developer from routing tenant data around the tenant requirement by simply
     * calling the global builder instead.
     *
     * @param  list<string|int>  $segments
     *
     * @throws InvalidArgumentException when the namespace is not classified global.
     */
    public static function global(string $namespace, array $segments = []): string
    {
        if (! in_array($namespace, self::GLOBAL_NAMESPACES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Cache namespace "%s" is not classified as global. Tenant data must use '
                .'TenantCacheKey::forTenant(). Adding a global namespace is a deliberate '
                .'classification decision, not a convenience.',
                $namespace
            ));
        }

        return implode(self::SEPARATOR, array_merge(
            [self::GLOBAL_PREFIX, self::sanitise($namespace)],
            array_map(self::sanitise(...), $segments)
        ));
    }

    /**
     * Rate-limit key for an AUTHENTICATED, tenant-scoped action.
     *
     * Carries both tenant and user, so two users never share a bucket and two
     * tenants never share a bucket. A rate limiter that merges unrelated
     * principals is both a correctness bug (one user locks out another) and an
     * information leak (bucket state reveals another party's activity).
     */
    public static function rateLimit(string $tenantId, string $userId, string $action): string
    {
        return self::forTenant($tenantId, 'throttle', [$action, $userId]);
    }

    /**
     * Rate-limit key for a PRE-AUTHENTICATION action (login, password reset).
     *
     * There is no tenant and no trusted user identity at this point, so the
     * bucket is keyed on the IP address and on a HASH of the submitted
     * identifier. The identifier is hashed rather than stored so that the Redis
     * keyspace never becomes a list of who has accounts here — which would be a
     * user-enumeration oracle sitting in the cache (Rule 21).
     */
    public static function preAuthRateLimit(string $action, string $identifier, string $ipAddress): string
    {
        return self::global('throttle_identity', [
            $action,
            hash('sha256', strtolower(trim($identifier))),
            hash('sha256', $ipAddress),
        ]);
    }

    /**
     * Per-IP-only pre-authentication rate-limit key.
     */
    public static function ipRateLimit(string $action, string $ipAddress): string
    {
        return self::global('throttle_ip', [$action, hash('sha256', $ipAddress)]);
    }

    /**
     * Keep keys to a predictable alphabet so a value cannot smuggle a separator
     * and collide with a differently-scoped key.
     */
    private static function sanitise(string|int $segment): string
    {
        $value = (string) $segment;

        return preg_replace('/[^A-Za-z0-9_.\-]/', '_', $value) ?? '_';
    }
}
