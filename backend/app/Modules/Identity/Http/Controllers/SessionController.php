<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SESSION SELF-SERVICE: see my sessions, end one, end all the others.
 *
 * A USER MAY ONLY EVER TOUCH THEIR OWN SESSIONS
 * ---------------------------------------------
 * Every query in this controller is scoped by `tokenable_id = <authenticated
 * user>`. A session belonging to somebody else is therefore NOT FOUND rather
 * than forbidden — the query never had access to it. That is the difference
 * between failing closed and remembering to check, and it means a mistyped or
 * guessed identifier reveals nothing about whether that session exists.
 *
 * Revoking ANOTHER user's device access is a different, administrative act with
 * its own permission, and lives on the tenant-scoped device-session endpoints.
 *
 * NO PLAINTEXT TOKEN IS EVER RETURNED HERE. A session is identified by its row
 * id. The credential itself was handed over exactly once, at creation, and this
 * application cannot reproduce it — only its SHA-256 hash is stored.
 */
final class SessionController
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * List the caller's own sessions, current one flagged.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $currentId = $user->currentAccessToken()?->getKey();

        $sessions = AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (AccessToken $token): array => [
                'id' => (string) $token->getKey(),
                'device_name' => $token->device_name ?? $token->name,
                'platform' => $token->platform,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'is_current' => $currentId !== null && (string) $token->getKey() === (string) $currentId,
                'expired' => $token->isExpired(),
            ])
            ->all();

        return ApiResponse::success(['sessions' => $sessions]);
    }

    /**
     * Revoke ONE of the caller's own sessions.
     */
    public function revoke(Request $request, string $sessionId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Scoped by owner. Somebody else's session is simply not found.
        $token = AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->whereKey($sessionId)
            ->first();

        if ($token === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND);
        }

        if (! $token->isRevoked()) {
            $token->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $user->id,
            ])->save();
        }

        $this->audit->record(
            action: AuditAction::AUTH_SESSION_REVOKED,
            subjectType: 'identity.access_token',
            subjectId: (string) $token->getKey(),
            actorUserId: $user->id,
            metadata: ['device_name' => $token->device_name],
            request: $request,
        );

        return ApiResponse::success(['revoked' => true, 'session_id' => (string) $token->getKey()]);
    }

    /**
     * Revoke every session EXCEPT the one making this request.
     *
     * This is the "I think my account is compromised" control. It deliberately
     * spares the current session so the user is not signed out of the device
     * they are using to secure their account.
     */
    public function revokeOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $currentId = $user->currentAccessToken()?->getKey();

        $query = AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->whereNull('revoked_at');

        if ($currentId !== null) {
            $query->whereKeyNot($currentId);
        }

        $revoked = $query->update([
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->id,
        ]);

        $this->audit->record(
            action: AuditAction::AUTH_SESSION_REVOKED_OTHERS,
            subjectType: 'identity.user',
            subjectId: $user->id,
            actorUserId: $user->id,
            metadata: ['sessions_revoked' => $revoked],
            request: $request,
        );

        return ApiResponse::success(['revoked' => true, 'sessions_revoked' => $revoked]);
    }
}
