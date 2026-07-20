<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Context\TenantContextResolver;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\DeviceSession;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TENANT AND OUTLET CONTEXT SELECTION.
 *
 * `GET /context/tenants` and `POST /context/tenant` run OUTSIDE the tenant
 * middleware, necessarily: you cannot require an active tenant in order to
 * choose one. They compensate by scoping every query to the AUTHENTICATED user's
 * own memberships, so neither endpoint can enumerate tenants the caller has no
 * relationship with (Rule 02).
 *
 * `GET /context/outlets` and `POST /context/outlet` run INSIDE it, and their
 * queries are scoped by the resolved tenant. An outlet in another tenant is not
 * found rather than rejected.
 *
 * WHY SELECTION IS AN ENDPOINT AND NOT A MUTATION
 * -----------------------------------------------
 * TenantContext is immutable within a request (see the class docblock there).
 * Selecting a tenant therefore records a preference that applies from the NEXT
 * request onward. It is audited, because "who switched into this tenant, and
 * when" is exactly the kind of question an isolation incident starts with.
 */
final class ContextController
{
    public function __construct(
        private readonly TenantContextResolver $resolver,
        private readonly EffectivePermissions $permissions,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Every tenant the caller may act in.
     *
     * Scoped to the caller's own memberships. This is not a tenant directory and
     * must never become one.
     */
    public function tenants(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->with('tenant')
            ->get()
            ->filter(fn (Membership $m): bool => $m->tenant !== null)
            ->map(fn (Membership $m): array => [
                'tenant' => [
                    'id' => $m->tenant->id,
                    'name' => $m->tenant->name,
                    'slug' => $m->tenant->slug,
                    'timezone' => $m->tenant->timezone,
                ],
                'membership' => [
                    'id' => $m->id,
                    'status' => $m->status,
                    // Only an ACTIVE membership can be selected. Showing the
                    // others with their status is what lets a suspended user
                    // understand why, rather than seeing their tenant vanish.
                    'selectable' => $m->isActive(),
                ],
            ])
            ->values()
            ->all();

        return ApiResponse::success(['tenants' => $memberships]);
    }

    /**
     * Select the active tenant.
     */
    public function selectTenant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'device_identifier' => ['sometimes', 'string', 'max:190'],
            'device_name' => ['sometimes', 'string', 'max:120'],
            'platform' => ['sometimes', 'string', 'max:60'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // THE VERIFICATION. The submitted tenant_id is a request, not proof.
        $context = $this->resolver->resolve($user, $validated['tenant_id']);

        // Persist the selection for cookie-based clients. Token clients send
        // `X-Tenant-Id` per request instead. Either way the selection is
        // RE-VERIFIED on every subsequent request, so a membership revoked after
        // selection stops working immediately (DEC-0025 §6).
        if ($request->hasSession()) {
            $request->session()->put(ResolveTenantContext::SESSION_TENANT_KEY, $context->tenantId());
            // Switching tenant clears the outlet: an outlet from the previous
            // tenant must never survive into the new one.
            $request->session()->forget(ResolveTenantContext::SESSION_OUTLET_KEY);
        }

        $deviceSession = $this->registerDeviceSession($request, $context, $validated);

        $this->audit->record(
            action: AuditAction::TENANT_CONTEXT_SWITCHED,
            subjectType: 'tenancy.membership',
            subjectId: $context->membershipId(),
            tenantId: $context->tenantId(),
            actorUserId: $user->id,
            actorMembershipId: $context->membershipId(),
            metadata: ['device_session_id' => $deviceSession?->id],
            request: $request,
        );

        return ApiResponse::success([
            'context' => $context->toArray(),
            'permissions' => $this->permissions->forContext($context),
            'roles' => $this->permissions->roleKeysForMembership($context->membership),
        ]);
    }

    /**
     * Outlets of the ACTIVE tenant.
     */
    public function outlets(Request $request): JsonResponse
    {
        $context = app(TenantContext::class);

        if (! $this->permissions->has($context, PermissionRegistry::OUTLET_VIEW)) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $outlets = Outlet::query()
            ->forTenant($context->tenantId())
            ->orderBy('name')
            ->get()
            ->map(fn (Outlet $outlet): array => [
                'id' => $outlet->id,
                'name' => $outlet->name,
                'code' => $outlet->code,
                'timezone' => $outlet->timezone,
                'laundry_brand_id' => $outlet->laundry_brand_id,
            ])
            ->all();

        return ApiResponse::success([
            'tenant_id' => $context->tenantId(),
            'outlets' => $outlets,
        ]);
    }

    /**
     * Select the active outlet within the active tenant.
     */
    public function selectOutlet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'uuid'],
        ]);

        $context = app(TenantContext::class);

        if (! $this->permissions->has($context, PermissionRegistry::OUTLET_SWITCH)) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        // Scoped by the resolved tenant: an outlet elsewhere is not found.
        $withOutlet = $this->resolver->attachOutlet($context, $validated['outlet_id']);

        if ($request->hasSession()) {
            $request->session()->put(ResolveTenantContext::SESSION_OUTLET_KEY, $withOutlet->outletId());
        }

        $this->audit->record(
            action: AuditAction::OUTLET_CONTEXT_SWITCHED,
            subjectType: 'organization.outlet',
            subjectId: (string) $withOutlet->outletId(),
            tenantId: $withOutlet->tenantId(),
            actorUserId: $withOutlet->userId(),
            actorMembershipId: $withOutlet->membershipId(),
            outletId: $withOutlet->outletId(),
            request: $request,
        );

        return ApiResponse::success(['context' => $withOutlet->toArray()]);
    }

    /**
     * Register or refresh the tenant-scoped device session.
     *
     * The device identifier is an untrusted hint recorded so that "which devices
     * are signed in to this tenant" is answerable and so a single device can be
     * revoked without disturbing the others. It is never an authorization signal
     * (Rule 31, hard rule 12; Rule 03, hard rule 9).
     *
     * @param  array<string, mixed>  $validated
     */
    private function registerDeviceSession(Request $request, TenantContext $context, array $validated): ?DeviceSession
    {
        $token = $request->user()?->currentAccessToken();

        $deviceIdentifier = $validated['device_identifier']
            ?? ($token instanceof AccessToken ? $token->device_identifier : null)
            ?? $request->headers->get('X-Device-Id');

        if (! is_string($deviceIdentifier) || trim($deviceIdentifier) === '') {
            return null;
        }

        $existing = DeviceSession::query()
            ->where('tenant_id', $context->tenantId())
            ->where('user_id', $context->userId())
            ->where('device_identifier', $deviceIdentifier)
            ->first();

        if ($existing !== null && $existing->isRevoked()) {
            // A revoked device does not silently re-register itself by switching
            // tenant again. Re-enabling it is an administrative act.
            throw ApiException::of(ErrorCode::DEVICE_REVOKED);
        }

        $attributes = [
            'membership_id' => $context->membershipId(),
            'device_name' => $validated['device_name'] ?? ($token instanceof AccessToken ? $token->device_name : null),
            'platform' => $validated['platform'] ?? ($token instanceof AccessToken ? $token->platform : null),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays((int) config('aish.session.device_lifetime_days', 30)),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return DeviceSession::create(array_merge($attributes, [
            'tenant_id' => $context->tenantId(),
            'user_id' => $context->userId(),
            'device_identifier' => $deviceIdentifier,
        ]));
    }
}
