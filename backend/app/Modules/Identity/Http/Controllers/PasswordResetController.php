<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use Carbon\CarbonImmutable;
use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\SharedKernel\Cache\TenantCacheKey;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * PASSWORD RESET — request and completion.
 *
 * THE DELIVERY TRANSPORT IS LOCAL ONLY
 * ------------------------------------
 * Step 3 introduces NO third-party service. Adding an email or WhatsApp provider
 * requires owner approval and a decision record (Rule 12 — "adding any
 * third-party service, dependency, SDK, or paid provider" must stop and ask).
 * The reset link is therefore written to the application LOG via the `log` mail
 * transport, which is sufficient for development and for tests and honest about
 * what it is. Real delivery arrives with the Step that introduces a provider.
 *
 * NO USER ENUMERATION
 * -------------------
 * The request endpoint returns the SAME success response whether or not the
 * identifier matches an account. If it returned 404 for an unknown address, an
 * attacker could enumerate the platform's users one request at a time — which
 * would defeat the generic-failure discipline the login endpoint maintains
 * (Rule 21 abuse cases).
 *
 * TOKEN HANDLING
 * --------------
 * The reset token is high-entropy and is stored HASHED. The plaintext exists
 * only inside the delivered link. It is never logged as context, never audited,
 * and never returned in a response body (Rule 03; Rule 21 hard rule 18).
 *
 * COMPLETION REVOKES EVERY EXISTING SESSION. Someone resetting a password is
 * often doing so because they believe their account is compromised; leaving the
 * attacker's existing sessions alive would make the reset cosmetic.
 */
final class PasswordResetController
{
    private const MAX_REQUESTS_PER_IDENTIFIER = 3;

    private const MAX_REQUESTS_PER_IP = 10;

    private const DECAY_SECONDS = 900;

    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Request a reset link. ALWAYS reports success.
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['identifier']);

        $this->assertNotRateLimited($identifier, $request);

        $user = $this->authentication->findByIdentifier($identifier);

        // The ONLY branch in this method, and it is invisible from outside.
        if ($user !== null && $user->isActive()) {
            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['identifier' => $this->storageIdentifier($user->email, $user->phone, $identifier)],
                [
                    // HASHED. The plaintext lives only in the delivered link.
                    'token' => Hash::make($plainToken),
                    'created_at' => now(),
                ]
            );

            $this->deliverResetLink($identifier, $plainToken);

            $this->audit->record(
                action: AuditAction::AUTH_PASSWORD_RESET_REQUESTED,
                subjectType: 'identity.user',
                subjectId: $user->id,
                actorUserId: $user->id,
                // No token, no identifier. Only the fact that it happened.
                metadata: ['delivery' => 'log'],
                request: $request,
            );
        }

        // Identical response on both paths. Do not add a branch here.
        return ApiResponse::success([
            'message' => 'Jika akun tersebut terdaftar, tautan atur ulang kata sandi telah dikirim. '
                .'Periksa pesan masuk Anda, lalu ikuti tautan tersebut.',
        ]);
    }

    /**
     * Complete a reset using the token from the delivered link.
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $identifier = trim($validated['identifier']);
        $user = $this->authentication->findByIdentifier($identifier);

        if ($user === null || ! $user->isActive()) {
            throw $this->genericResetFailure();
        }

        $record = DB::table('password_reset_tokens')
            ->where('identifier', $this->storageIdentifier($user->email, $user->phone, $identifier))
            ->first();

        if ($record === null || ! Hash::check($validated['token'], $record->token)) {
            throw $this->genericResetFailure();
        }

        $expiryMinutes = (int) config('auth.passwords.users.expire', 60);

        // Compare instants directly rather than measuring a diff.
        //
        // The previous form was `now()->diffInMinutes($record->created_at) > $expiryMinutes`.
        // Carbon 3 returns a SIGNED diff, so for a token created in the past this
        // evaluated to a negative number and the comparison was never true — the
        // expiry branch was unreachable and a reset link, which is a bearer
        // credential, never expired. The `auth.passwords.users.expire` setting was
        // inert. Verified on Carbon 3.13.1: a token aged 120 minutes yielded
        // -120.0, and -120.0 > 60 is false.
        //
        // `lessThan` on explicit instants has no sign to get wrong.
        //
        // `created_at` is parsed explicitly: this row is read with the query
        // builder, not Eloquent, so it arrives as a raw PostgreSQL timestamp
        // string with no date casting applied.
        $issuedAt = CarbonImmutable::parse((string) $record->created_at);

        if ($issuedAt->lessThan(now()->subMinutes($expiryMinutes))) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Tautan atur ulang kata sandi sudah kedaluwarsa. Ajukan permintaan baru.'
            );
        }

        // The `hashed` cast on User::password hashes this on assignment, so a
        // plaintext value cannot reach the column.
        $user->password = $validated['password'];
        $user->save();

        DB::table('password_reset_tokens')
            ->where('identifier', $this->storageIdentifier($user->email, $user->phone, $identifier))
            ->delete();

        // Every existing credential dies with the old password. A reset that
        // left the attacker's session alive would be cosmetic.
        $revoked = AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_by_user_id' => $user->id]);

        $this->audit->record(
            action: AuditAction::AUTH_PASSWORD_RESET_COMPLETED,
            subjectType: 'identity.user',
            subjectId: $user->id,
            actorUserId: $user->id,
            metadata: ['sessions_revoked' => $revoked],
            request: $request,
        );

        return ApiResponse::success([
            'message' => 'Kata sandi berhasil diperbarui. Semua sesi lama telah diakhiri. Silakan masuk kembali.',
            'sessions_revoked' => $revoked,
        ]);
    }

    /**
     * Development/test delivery. The token appears in the LINK only.
     *
     * Note the token is placed in the message STRING, never in the log context
     * array — the redaction processor scrubs context keys, and relying on it to
     * carry a secret would be depending on a control to fail safely rather than
     * not creating the exposure. In a real deployment this transport is replaced
     * by a provider, which is a Step-gated decision.
     */
    private function deliverResetLink(string $identifier, string $plainToken): void
    {
        $url = rtrim((string) config('app.url'), '/').'/atur-ulang-kata-sandi?token='.$plainToken;

        Log::channel(config('aish.password_reset.log_channel', 'single'))->info(
            sprintf(
                '[PASSWORD RESET — LOCAL TRANSPORT ONLY] Tautan untuk %s: %s',
                Str::mask($identifier, '*', 2, max(strlen($identifier) - 4, 0)),
                $url
            )
        );
    }

    /**
     * A reset identifier is stored as the account's canonical contact value, so
     * a reset requested by phone and one requested by email do not collide.
     */
    private function storageIdentifier(?string $email, ?string $phone, string $submitted): string
    {
        $normalised = mb_strtolower(trim($submitted));

        if ($email !== null && mb_strtolower($email) === $normalised) {
            return mb_strtolower($email);
        }

        if ($phone !== null && $phone === trim($submitted)) {
            return $phone;
        }

        return $normalised;
    }

    private function assertNotRateLimited(string $identifier, Request $request): void
    {
        $identifierKey = TenantCacheKey::preAuthRateLimit('password_reset', $identifier, (string) $request->ip());
        $ipKey = TenantCacheKey::ipRateLimit('password_reset', (string) $request->ip());

        if (RateLimiter::tooManyAttempts($identifierKey, self::MAX_REQUESTS_PER_IDENTIFIER)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_REQUESTS_PER_IP)) {
            throw ApiException::of(ErrorCode::RATE_LIMITED);
        }

        RateLimiter::hit($identifierKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
    }

    /**
     * One failure shape for "no such account", "wrong token", and "account
     * disabled" alike — for the same reason login has one.
     */
    private function genericResetFailure(): ApiException
    {
        return ApiException::of(
            ErrorCode::VALIDATION_FAILED,
            'Tautan atur ulang kata sandi tidak valid atau sudah digunakan. Ajukan permintaan baru.'
        );
    }
}
