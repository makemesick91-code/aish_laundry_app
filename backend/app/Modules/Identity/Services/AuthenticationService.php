<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\SharedKernel\Cache\TenantCacheKey;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * AUTHENTICATION — and, just as importantly, the discipline around FAILING.
 *
 * THE GENERIC-FAILURE RULE
 * ------------------------
 * Every login failure returns the SAME error code and the SAME message,
 * regardless of cause:
 *
 *   - the identifier belongs to no account
 *   - the identifier exists but the password is wrong
 *   - the account exists but is disabled
 *   - the account exists but has no password set
 *
 * These are indistinguishable to the caller ON PURPOSE. Any difference — a
 * different code, a different message, or even a measurably different response
 * time — turns the login endpoint into an oracle that answers "does this person
 * have an account here?". For a laundry SaaS that is a customer-list disclosure;
 * combined with tenant data it is a competitor intelligence feed (Rule 21 abuse
 * cases: tenant enumeration, customer account takeover).
 *
 * The real cause IS recorded, in the audit trail, for the operator — categorised
 * and with the submitted identifier stored only as a keyed hash.
 *
 * TIMING
 * ------
 * When no user is found, a dummy hash comparison still runs. Without it, "no
 * such account" returns measurably faster than "wrong password", and the timing
 * difference rebuilds the enumeration oracle the generic message just removed.
 *
 * RATE LIMITING
 * -------------
 * Two independent buckets: one keyed on the submitted identifier (slows a
 * password-spray against one account) and one keyed on the source IP (slows a
 * spray across many accounts from one origin). The identifier bucket keys on a
 * HASH, so the rate-limiter keyspace never becomes a list of who has an account
 * here.
 */
final class AuthenticationService
{
    private const MAX_ATTEMPTS_PER_IDENTIFIER = 5;

    private const MAX_ATTEMPTS_PER_IP = 20;

    private const DECAY_SECONDS = 300;

    /**
     * A hash of a value nobody knows, used to equalise timing on the "no such
     * account" path. It is not a credential and unlocks nothing.
     *
     * GENERATED AT RUNTIME, DELIBERATELY, RATHER THAN WRITTEN AS A LITERAL.
     * A hard-coded hash has two failure modes that a literal cannot avoid:
     *
     *   1. It can be malformed. A hash the configured hasher rejects makes
     *      Hash::check() THROW, so the unknown-account path returns a 500 while
     *      a wrong password returns a 401 — which is a user-enumeration oracle
     *      built out of the very code meant to prevent one. This exact defect
     *      was present here and is what motivated the change.
     *   2. It pins a work factor. A literal at cost 12 equalises nothing when
     *      the configured cost is different: the decoy check and the real check
     *      take measurably different times, restoring the timing signal.
     *
     * Deriving it from the configured hasher removes both. It is computed once
     * per process and the input is random, so nothing about it is guessable.
     */
    private static ?string $timingEqualisationHash = null;

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * Authenticate a credential pair. Throws a GENERIC failure on every
     * unsuccessful path.
     *
     * @throws ApiException
     */
    public function attempt(string $identifier, string $password, Request $request): User
    {
        $this->assertNotRateLimited($identifier, $request);

        $user = $this->findByIdentifier($identifier);

        if ($user === null) {
            // Equalise timing before failing. See class docblock.
            Hash::check($password, self::timingEqualisationHash());

            $this->recordFailure($identifier, AuditAction::FAILURE_UNKNOWN_IDENTIFIER, $request);

            throw $this->genericFailure();
        }

        if ($user->password === null || $user->password === '') {
            Hash::check($password, self::timingEqualisationHash());

            $this->recordFailure($identifier, AuditAction::FAILURE_NO_PASSWORD_SET, $request, $user->id);

            throw $this->genericFailure();
        }

        if (! Hash::check($password, $user->password)) {
            $this->recordFailure($identifier, AuditAction::FAILURE_INVALID_PASSWORD, $request, $user->id);

            throw $this->genericFailure();
        }

        // A disabled account fails EXACTLY like a wrong password. Telling the
        // caller "this account is disabled" confirms the account exists.
        if (! $user->isActive()) {
            $this->recordFailure($identifier, AuditAction::FAILURE_ACCOUNT_DISABLED, $request, $user->id);

            throw $this->genericFailure();
        }

        $this->clearRateLimits($identifier, $request);

        return $user;
    }

    /**
     * Issue a mobile/API session credential.
     *
     * The PLAINTEXT token is returned exactly once, here, and is never stored:
     * only `hash('sha256', $plain)` reaches the database (Rule 03, hard rule 6).
     *
     * @return array{token: string, access_token: AccessToken}
     */
    public function issueToken(
        User $user,
        string $deviceName,
        ?string $deviceIdentifier,
        ?string $platform,
        Request $request,
    ): array {
        $newToken = $user->createToken(
            name: $deviceName,
            abilities: ['*'],
            expiresAt: now()->addDays((int) config('aish.session.token_lifetime_days', 30)),
        );

        /** @var AccessToken $accessToken */
        $accessToken = AccessToken::query()->whereKey($newToken->accessToken->getKey())->firstOrFail();

        $accessToken->forceFill([
            'device_identifier' => $deviceIdentifier,
            'device_name' => $deviceName,
            'platform' => $platform,
            'last_used_ip' => $request->ip(),
        ])->save();

        return [
            'token' => $newToken->plainTextToken,
            'access_token' => $accessToken,
        ];
    }

    /**
     * The decoy hash, produced by the CONFIGURED hasher so its work factor
     * matches a real credential check. Computed once per process.
     */
    private static function timingEqualisationHash(): string
    {
        return self::$timingEqualisationHash ??= Hash::make(bin2hex(random_bytes(32)));
    }

    /**
     * An identifier is an email address or a phone number. Both columns are
     * unique, so at most one account matches.
     */
    public function findByIdentifier(string $identifier): ?User
    {
        $normalised = trim($identifier);

        if ($normalised === '') {
            return null;
        }

        return User::query()
            ->where(function ($query) use ($normalised): void {
                $query->whereRaw('lower(email) = ?', [mb_strtolower($normalised)])
                    ->orWhere('phone', $normalised);
            })
            ->first();
    }

    private function assertNotRateLimited(string $identifier, Request $request): void
    {
        $identifierKey = TenantCacheKey::preAuthRateLimit('login', $identifier, (string) $request->ip());
        $ipKey = TenantCacheKey::ipRateLimit('login', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($identifierKey, self::MAX_ATTEMPTS_PER_IDENTIFIER)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP)) {
            $this->recordFailure($identifier, AuditAction::FAILURE_RATE_LIMITED, $request);

            throw ApiException::of(ErrorCode::RATE_LIMITED);
        }

        RateLimiter::hit($identifierKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
    }

    private function clearRateLimits(string $identifier, Request $request): void
    {
        RateLimiter::clear(TenantCacheKey::preAuthRateLimit('login', $identifier, (string) $request->ip()));
    }

    private function recordFailure(
        string $identifier,
        string $category,
        Request $request,
        ?string $knownUserId = null,
    ): void {
        $this->audit->recordLoginFailure($identifier, $category, $request, $knownUserId);
    }

    /**
     * ONE failure shape for every cause. Never specialise this.
     */
    private function genericFailure(): ApiException
    {
        return ApiException::of(
            ErrorCode::UNAUTHENTICATED,
            'Kombinasi akun dan kata sandi tidak cocok. Periksa kembali, atau atur ulang kata sandi Anda.'
        );
    }
}
