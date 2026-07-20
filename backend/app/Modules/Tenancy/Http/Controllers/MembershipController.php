<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Authorization\EffectivePermissions;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\CorrelationId;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The caller's OWN membership in the active tenant.
 *
 * Deliberately narrow: it answers "who am I here, and what may I do", and
 * nothing about anyone else. It needs no permission beyond an active membership,
 * because everything it returns is already the caller's own.
 */
final class MembershipController
{
    public function __construct(private readonly EffectivePermissions $permissions)
    {
    }

    public function current(Request $request): JsonResponse
    {
        $context = app(TenantContext::class);

        return ApiResponse::success([
            'membership' => [
                'id' => $context->membershipId(),
                'user_id' => $context->userId(),
                'tenant_id' => $context->tenantId(),
                'status' => $context->membership->status,
                'accepted_at' => $context->membership->accepted_at?->toIso8601String(),
            ],
            'tenant' => [
                'id' => $context->tenant->id,
                'name' => $context->tenant->name,
                'slug' => $context->tenant->slug,
                'timezone' => $context->tenant->timezone,
            ],
            'outlet' => $context->outlet === null ? null : [
                'id' => $context->outlet->id,
                'name' => $context->outlet->name,
                'code' => $context->outlet->code,
            ],
            // Role keys are returned for DISPLAY. Authorization decisions are
            // made on permissions; comparing a role name is how a renamed role
            // silently grants or removes access.
            'roles' => $this->permissions->roleKeysForMembership($context->membership),
            'permissions' => $this->permissions->forContext($context),
            'correlation_id' => app(CorrelationId::class)->value,
        ]);
    }
}
