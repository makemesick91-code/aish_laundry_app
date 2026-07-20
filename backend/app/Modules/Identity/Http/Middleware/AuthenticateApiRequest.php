<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ESTABLISHES THE AUTHENTICATED IDENTITY, with honest failure codes.
 *
 * TWO CREDENTIAL SHAPES, ONE IDENTITY MODEL
 * -----------------------------------------
 *   - Admin Web (SPA)  : Sanctum COOKIE session. HttpOnly, SameSite, CSRF-protected.
 *                        The browser never sees a token, so there is nothing for
 *                        cross-site script to steal.
 *   - Mobile (Flutter) : Sanctum personal access TOKEN in the Authorization
 *                        header, held in platform secure storage on device
 *                        (Rule 03, hard rule 7).
 *
 * There is deliberately NO guidance anywhere in this codebase suggesting a token
 * be stored in `localStorage`: a token readable by JavaScript is a token
 * exfiltrable by any script that ends up on the page.
 *
 * WHY THIS REPLACES `auth:sanctum` RATHER THAN WRAPPING IT
 * --------------------------------------------------------
 * Sanctum's guard returns "no user" for an expired token, a revoked token, and a
 * garbage token alike. All three become a single generic 401, which tells a
 * legitimate user nothing about why they were signed out and gives the operator
 * nothing to diagnose.
 *
 * This middleware inspects the credential itself and distinguishes:
 *   UNAUTHENTICATED  — no credential, or one that matches nothing
 *   SESSION_EXPIRED  — a real credential whose lifetime elapsed
 *   SESSION_REVOKED  — a real credential that was deliberately revoked
 *
 * IMPORTANT: distinguishing these leaks nothing. All three are only reachable by
 * someone already holding the credential in question, so the response tells them
 * about a credential they already possess (Rule 32, hard rule 2 concerns
 * disclosure ACROSS a tenant boundary — this is not that).
 *
 * Token lookup is by SHA-256 HASH. The plaintext token exists only in the
 * client's request; this application never stores it (Rule 03, hard rule 6).
 */
final class AuthenticateApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * THE PRESENTED CREDENTIAL DECIDES, AND A BEARER TOKEN WINS.
         *
         * When a request carries an Authorization bearer token, THAT is the
         * credential being offered and it is the one evaluated — including its
         * revocation and expiry checks. Consulting the session first would mean
         * a REVOKED token still authenticated any request that also happened to
         * carry a session, silently defeating revocation. Falling back to the
         * session only when no token is presented keeps each credential judged
         * on its own state.
         */
        $user = $request->bearerToken() !== null && $request->bearerToken() !== ''
            ? $this->resolveFromBearerToken($request)
            : $this->resolveFromStatefulSession($request);

        if ($user === null) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        // Checked on EVERY request, not only at login, so disabling an account
        // takes effect on the next request rather than when a token expires.
        if (! $user->isActive()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }

    /**
     * First-party SPA cookie session (Admin Web).
     */
    private function resolveFromStatefulSession(Request $request): ?User
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Mobile / API bearer token.
     */
    private function resolveFromBearerToken(Request $request): ?User
    {
        $plainTextToken = $request->bearerToken();

        if ($plainTextToken === null || $plainTextToken === '') {
            return null;
        }

        // Sanctum's stored format is "<id>|<plain>" for the value handed to the
        // client; only the hash of the second half is persisted.
        $parts = explode('|', $plainTextToken, 2);
        $secret = count($parts) === 2 ? $parts[1] : $plainTextToken;

        $accessToken = AccessToken::query()
            ->where('token', hash('sha256', $secret))
            ->first();

        if ($accessToken === null) {
            // Matches nothing. Indistinguishable from "no credential at all",
            // which is correct: a random string must not reveal whether it
            // nearly matched something.
            return null;
        }

        // Order matters: revocation is a deliberate act and is reported as such
        // even if the token would also have expired by now.
        if ($accessToken->isRevoked()) {
            throw ApiException::of(ErrorCode::SESSION_REVOKED);
        }

        if ($accessToken->isExpired()) {
            throw ApiException::of(ErrorCode::SESSION_EXPIRED);
        }

        $tokenable = $accessToken->tokenable;

        if (! $tokenable instanceof User) {
            return null;
        }

        // Recorded for "which devices are signed in" and for support diagnosis.
        // Never used as an authorization signal (Rule 31, hard rule 12).
        $accessToken->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        $tokenable->withAccessToken($accessToken);

        return $tokenable;
    }
}
