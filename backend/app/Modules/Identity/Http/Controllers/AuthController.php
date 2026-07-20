<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AuthenticationService;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Support\Redactor;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * LOGIN, LOGOUT, and WHO AM I.
 *
 * TWO CREDENTIAL SHAPES FROM ONE ENDPOINT
 * ---------------------------------------
 * `mode=cookie` (Admin Web SPA) establishes a first-party Sanctum session
 * cookie: HttpOnly, SameSite, CSRF-protected. No token is returned, because a
 * token in a browser is a token any injected script can read.
 *
 * `mode=token` (mobile) returns a plaintext personal access token EXACTLY ONCE.
 * The client stores it in platform secure storage — the Android Keystore, never
 * plain shared preferences and never a plain file (Rule 03, hard rule 7).
 *
 * SESSION ROTATION
 * ----------------
 * The session identifier is regenerated on every successful cookie login. This
 * closes session fixation: an identifier an attacker planted before
 * authentication is discarded at the moment it would have become valuable.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthenticationService $authentication,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'mode' => ['sometimes', Rule::in(['cookie', 'token'])],
            'device_name' => ['sometimes', 'string', 'max:120'],
            'device_identifier' => ['sometimes', 'string', 'max:190'],
            'platform' => ['sometimes', 'string', 'max:60'],
        ]);

        // Throws a GENERIC failure on every unsuccessful path — see
        // AuthenticationService. No branch here may specialise it.
        $user = $this->authentication->attempt(
            $validated['identifier'],
            $validated['password'],
            $request,
        );

        $mode = $validated['mode'] ?? 'token';

        $payload = ['user' => $this->userPayload($user)];

        if ($mode === 'cookie') {
            Auth::guard('web')->login($user);

            // Session fixation defence. Must happen AFTER login so the
            // authenticated state is carried onto the new identifier.
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $payload['mode'] = 'cookie';
        } else {
            $issued = $this->authentication->issueToken(
                user: $user,
                deviceName: $validated['device_name'] ?? 'Perangkat tidak dikenal',
                deviceIdentifier: $validated['device_identifier'] ?? null,
                platform: $validated['platform'] ?? null,
                request: $request,
            );

            $payload['mode'] = 'token';
            // The ONLY time this value ever exists outside the client.
            $payload['token'] = $issued['token'];
            $payload['session'] = $this->sessionPayload($issued['access_token']);
        }

        $this->audit->record(
            action: AuditAction::AUTH_LOGIN_SUCCEEDED,
            subjectType: 'identity.user',
            subjectId: $user->id,
            actorUserId: $user->id,
            metadata: ['mode' => $mode],
            request: $request,
        );

        return ApiResponse::success($payload);
    }

    /**
     * Ends the CURRENT session only.
     *
     * Other devices are deliberately unaffected: signing out of the counter
     * tablet must not sign out the owner's phone. Revoking everything is a
     * separate, explicit action (`POST /api/v1/sessions/revoke-others`).
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->currentAccessToken();

        if ($token instanceof AccessToken) {
            // Recorded, not deleted — so a later request with this credential
            // can be told SESSION_REVOKED rather than a bare UNAUTHENTICATED.
            $token->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $user->id,
            ])->save();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->audit->record(
            action: AuditAction::AUTH_LOGOUT,
            subjectType: 'identity.user',
            subjectId: $user->id,
            actorUserId: $user->id,
            request: $request,
        );

        return ApiResponse::success(['logged_out' => true]);
    }

    /**
     * The authenticated identity and the tenants it may act in.
     *
     * Note what is NOT here: no role, no permission. Those are properties of a
     * MEMBERSHIP in a specific tenant, not of the account, and are served by
     * `GET /api/v1/authorization/permissions` once a tenant is active
     * (Rule 02; DEC-0025 §2).
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->with('tenant')
            ->get()
            ->map(fn (Membership $membership): array => [
                'membership_id' => $membership->id,
                'status' => $membership->status,
                'tenant' => $membership->tenant === null ? null : [
                    'id' => $membership->tenant->id,
                    'name' => $membership->tenant->name,
                    'slug' => $membership->tenant->slug,
                ],
            ])
            ->all();

        return ApiResponse::success([
            'user' => $this->userPayload($user),
            'memberships' => $memberships,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            // Masked by default (Rule 32, hard rule 4). The account owner could
            // legitimately see their own contact details in full, but this
            // payload is also what an Ops surface renders, and defaulting to
            // masked means a future screen cannot leak by forgetting.
            'email' => Redactor::maskEmail($user->email),
            'phone' => Redactor::maskPhone($user->phone),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(AccessToken $token): array
    {
        return [
            'id' => (string) $token->getKey(),
            'device_name' => $token->device_name,
            'platform' => $token->platform,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }
}
