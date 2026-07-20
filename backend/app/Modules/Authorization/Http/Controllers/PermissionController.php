<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "WHAT MAY I DO IN THIS TENANT, RIGHT NOW?"
 *
 * Recomputed per request from the caller's live membership and current role
 * assignments (DEC-0025 §5). A role removed a second ago is already gone from
 * this response — nothing here is cached and nothing is read from a token.
 *
 * WHAT THIS ENDPOINT IS FOR, AND WHAT IT IS NOT
 * ---------------------------------------------
 * It exists so a client can decide what to RENDER. It is emphatically NOT an
 * access control: hiding a button is a user-experience affordance, and every
 * request is authorised server-side regardless of what the client believed
 * (Rule 03, hard rule 2; Rule 28, hard rule 6).
 *
 * It returns only the CALLER'S OWN effective permissions. It never reports what
 * another user may do, and it never reveals the tenant's role assignments.
 */
final class PermissionController
{
    public function __construct(private readonly EffectivePermissions $permissions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $context = app(TenantContext::class);

        $effective = $this->permissions->forContext($context);
        $roleKeys = $this->permissions->roleKeysForMembership($context->membership);

        return ApiResponse::success([
            'tenant_id' => $context->tenantId(),
            'membership_id' => $context->membershipId(),
            'roles' => $roleKeys,
            'permissions' => $effective,
            // Descriptions come from the registry so a client can render a
            // meaningful denial explanation without hard-coding copy.
            'catalogue' => $this->catalogueFor($effective),
            'notice' => 'Izin ini dihitung ulang pada setiap permintaan. Menyembunyikan tombol bukan kontrol akses; '
                .'setiap permintaan tetap diperiksa di server.',
        ]);
    }

    /**
     * @param  list<string>  $effective
     * @return array<string, string>
     */
    private function catalogueFor(array $effective): array
    {
        $catalogue = PermissionRegistry::permissions();
        $out = [];

        foreach ($effective as $permission) {
            if (isset($catalogue[$permission])) {
                $out[$permission] = $catalogue[$permission]['description'];
            }
        }

        return $out;
    }
}
