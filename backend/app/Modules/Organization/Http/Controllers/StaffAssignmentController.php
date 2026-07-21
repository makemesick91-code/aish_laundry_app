<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Organization\Models\MembershipOutlet;
use App\Modules\Organization\Services\OutletMasterDataRegistry;
use App\Modules\Organization\Services\StaffAssignmentRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * STAFF ASSIGNMENT WITHIN A TENANT (ROADMAP Step 4 scope, FR-018, DEC-0031 A).
 *
 * Two distinct acts, deliberately kept apart:
 *
 *   1. ASSIGNING A MEMBERSHIP TO AN OUTLET — where somebody works. Confers no
 *      capability whatsoever.
 *   2. ASSIGNING A ROLE — what somebody may do. Gated by the existing Step 3
 *      permission AND by the escalation guard in StaffAssignmentRegistry.
 *
 * Keeping them apart matters: if an outlet assignment granted capability, the
 * roster screen would be a privilege-escalation path wearing an innocent name,
 * and the guard on role assignment could be walked around entirely.
 *
 * EVERY LOOKUP IS TENANT-SCOPED FIRST. A membership or outlet belonging to
 * another tenant and one that does not exist produce the SAME 404 (Rule 48 hard
 * rule 5, threat T-13).
 *
 * VISIBILITY IS NOT AUTHORIZATION. This surface lists memberships so an
 * administrator can roster them; every action it offers is re-checked
 * server-side, and hiding a control in a client is never the control
 * (Rule 40 hard rule 2).
 */
final class StaffAssignmentController
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly StaffAssignmentRegistry $staff,
        private readonly OutletMasterDataRegistry $outlets,
    ) {
    }

    /**
     * Memberships of the active tenant, with their roles and live assignments.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Membership::class);

        $context = app(TenantContext::class);

        $perPage = min(max((int) $request->query('per_page', 25), 1), self::MAX_PER_PAGE);

        $query = Membership::query()
            ->forTenant($context->tenantId())
            ->with(['user', 'roles']);

        if (($status = $request->query('status')) !== null) {
            if (! in_array($status, Membership::STATUSES, true)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Status keanggotaan tidak dikenal.',
                    ['status' => Membership::STATUSES]
                );
            }

            $query->where('status', $status);
        }

        $page = $query->orderBy('created_at')->paginate($perPage);

        $assignments = MembershipOutlet::query()
            ->forTenant($context->tenantId())
            ->active()
            ->whereIn('membership_id', array_map(
                static fn (Membership $m): string => $m->id,
                $page->items()
            ))
            ->get()
            ->groupBy('membership_id');

        return ApiResponse::success([
            'staff' => array_map(
                fn (Membership $m): array => $this->projectMembership($m, $assignments->get($m->id)?->all() ?? []),
                $page->items()
            ),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $membership): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->resolveMembership($context, $membership);

        Gate::authorize('view', $model);

        $assignments = MembershipOutlet::query()
            ->forTenant($context->tenantId())
            ->active()
            ->where('membership_id', $model->id)
            ->get()
            ->all();

        return ApiResponse::success([
            'staff' => $this->projectMembership($model->load(['user', 'roles']), $assignments),
        ]);
    }

    // ------------------------------------------------------------------
    // Outlet assignment — WHERE somebody works
    // ------------------------------------------------------------------

    public function assignOutlet(Request $request, string $membership): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->resolveMembership($context, $membership);

        Gate::authorize('assignStaff', $model);

        // `assigned_outlet_id`, NOT `outlet_id`, and the distinction is load-
        // bearing rather than stylistic.
        //
        // Step 3's `ResolveTenantContext` middleware treats a request-body
        // `outlet_id` as the caller's ACTIVE OUTLET selector — the outlet they
        // are currently working in. This endpoint means something entirely
        // different by an outlet: the one it is rostering somebody ONTO.
        //
        // Naming it `outlet_id` would silently switch the caller's own working
        // context every time they edited the roster, and would make a
        // cross-tenant assignment attempt fail in the middleware with
        // OUTLET_ACCESS_DENIED before this handler ever ran — a defensible
        // refusal arrived at for the wrong reason, and one that would stop being
        // true the moment the middleware changed.
        $validated = $request->validate([
            'assigned_outlet_id' => ['required', 'uuid'],
        ]);

        // Resolved WITHIN the active tenant. An outlet id from another tenant
        // does not resolve, so it produces a 404 rather than reaching a foreign
        // key that would refuse it with a constraint error (threat T-13).
        $outlet = $this->outlets->resolveOutlet($context, $validated['assigned_outlet_id']);

        $assignment = $this->staff->assignToOutlet($context, $model, $outlet, $request);

        return ApiResponse::success([
            'assignment' => $this->projectAssignment($assignment),
        ], 201);
    }

    public function revokeOutlet(Request $request, string $membership, string $assignment): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->resolveMembership($context, $membership);

        Gate::authorize('assignStaff', $model);

        $record = MembershipOutlet::query()
            ->forTenant($context->tenantId())
            ->where('membership_id', $model->id)
            ->whereKey($assignment)
            ->first();

        if ($record === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        $revoked = $this->staff->revokeFromOutlet($context, $record, $request);

        return ApiResponse::success(['assignment' => $this->projectAssignment($revoked)]);
    }

    // ------------------------------------------------------------------
    // Role assignment — WHAT somebody may do
    // ------------------------------------------------------------------

    public function assignRole(Request $request, string $membership): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->resolveMembership($context, $membership);

        Gate::authorize('assignRole', $model);

        $validated = $request->validate([
            // Enumerated against the canonical registry. A role key outside it
            // does not exist, and tenant-defined custom roles remain DEFERRED
            // and NOT IMPLEMENTED (DEC-0025 §10).
            'role' => ['required', 'string', Rule::in(PermissionRegistry::roleKeys())],
        ]);

        // The escalation guard lives in the registry, not here: it must hold for
        // every caller of the write path, not only for ones arriving over HTTP
        // (Rule 18 hard rule 2 — an invariant one code path honours is not one).
        $this->staff->assignRole($context, $model, $validated['role'], $request);

        return ApiResponse::success([
            'staff' => $this->projectMembership($model->fresh(['user', 'roles']), []),
        ]);
    }

    public function removeRole(Request $request, string $membership, string $role): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->resolveMembership($context, $membership);

        Gate::authorize('removeRole', $model);

        if (! in_array($role, PermissionRegistry::roleKeys(), true)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        $this->staff->removeRole($context, $model, $role, $request);

        return ApiResponse::success([
            'staff' => $this->projectMembership($model->fresh(['user', 'roles']), []),
        ]);
    }

    // ------------------------------------------------------------------
    // Projections — allow-lists, never model dumps
    // ------------------------------------------------------------------

    /**
     * @param  list<MembershipOutlet>  $assignments
     * @return array<string, mixed>
     */
    private function projectMembership(Membership $membership, array $assignments): array
    {
        return [
            'membership_id' => $membership->id,
            'status' => $membership->status,

            // The staff member's name and email, and NOT their phone number.
            // A roster screen has no operational need for it, and Rule 32 hard
            // rule 4 masks by default — the narrowest projection that does the
            // job is the one that cannot leak the rest.
            'user' => [
                'id' => $membership->user?->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
            ],

            // Role keys for DISPLAY. Authorization decisions are made on
            // permissions; comparing a role name is how a renamed role silently
            // grants or removes access (the Step 3 convention, unchanged).
            'roles' => $membership->roles->pluck('key')->values()->all(),

            'outlet_assignments' => array_map(
                fn (MembershipOutlet $a): array => $this->projectAssignment($a),
                $assignments
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function projectAssignment(MembershipOutlet $assignment): array
    {
        return [
            'id' => $assignment->id,
            'membership_id' => $assignment->membership_id,
            'outlet_id' => $assignment->outlet_id,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'revoked_at' => $assignment->revoked_at?->toIso8601String(),
            'is_active' => $assignment->isActive(),
        ];
    }

    private function resolveMembership(TenantContext $context, string $id): Membership
    {
        $membership = Membership::query()
            ->forTenant($context->tenantId())
            ->whereKey($id)
            ->first();

        if ($membership === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $membership;
    }
}
