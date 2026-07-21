<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Organization\Models\MembershipOutlet;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * STAFF ASSIGNMENT — the only writer of `membership_outlet` and of Step 4's
 * role-assignment path (ROADMAP Step 4 scope, FR-018, DEC-0031 A).
 *
 * NO SECOND AUTHORIZATION SYSTEM. Roles come from `PermissionRegistry`, are
 * stored in `membership_role`, and are evaluated by `EffectivePermissions` —
 * all Step 3 machinery, consumed here rather than duplicated (Rule 40).
 *
 * THE ESCALATION GUARD IS THE POINT OF THIS CLASS (invariant A2, threat T-14).
 * ---------------------------------------------------------------------------
 * Holding `membership.role.assign` means you may hand out roles. It must not
 * mean you may hand out ANY role, or the permission is a self-service route to
 * tenant ownership: an admin assigns `tenant_owner` to themselves, or to an
 * account they control, and the tenant is gone.
 *
 * `assertNoEscalation()` therefore requires the caller to already hold every
 * permission the role they are granting would confer. You may delegate what you
 * have; you may not mint what you do not.
 *
 * The comparison is over PERMISSIONS, not role names. Comparing role names would
 * mean a renamed role, or a role whose grant list widened in a later release,
 * silently changed who could hand it out.
 */
final class StaffAssignmentRegistry
{
    public function __construct(
        private readonly EffectivePermissions $permissions,
        private readonly AuditRecorder $audit,
    ) {
    }

    // ------------------------------------------------------------------
    // Outlet assignment
    // ------------------------------------------------------------------

    public function assignToOutlet(
        TenantContext $context,
        Membership $membership,
        Outlet $outlet,
        ?Request $request = null,
    ): MembershipOutlet {
        $this->assertSameTenant($context, $membership->tenant_id);
        $this->assertSameTenant($context, $outlet->tenant_id);

        // A revoked membership is not staff. Assigning one to an outlet would
        // create a roster entry that grants nothing and reads as if it does.
        if ($membership->status === Membership::STATUS_REVOKED) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Keanggotaan yang sudah dicabut tidak dapat ditugaskan ke outlet. '
                .'Aktifkan kembali keanggotaan tersebut lebih dahulu.',
                ['membership' => ['revoked']]
            );
        }

        $assignment = new MembershipOutlet;
        $assignment->tenant_id = $context->tenantId();
        $assignment->membership_id = $membership->id;
        $assignment->outlet_id = $outlet->id;
        $assignment->assigned_by_membership_id = $context->membershipId();
        $assignment->assigned_at = now();

        try {
            $assignment->save();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'membership_outlet_one_active_assignment')) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Anggota ini sudah ditugaskan pada outlet tersebut.',
                    ['assigned_outlet_id' => ['duplicate']]
                );
            }

            throw $exception;
        }

        $this->audit->record(
            action: AuditAction::STAFF_OUTLET_ASSIGNED,
            subjectType: MembershipOutlet::class,
            subjectId: $assignment->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            outletId: $outlet->id,
            metadata: ['membership_id' => $membership->id],
            request: $request,
        );

        return $assignment->refresh();
    }

    public function revokeFromOutlet(
        TenantContext $context,
        MembershipOutlet $assignment,
        ?Request $request = null,
    ): MembershipOutlet {
        $this->assertSameTenant($context, $assignment->tenant_id);

        if (! $assignment->isActive()) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Penugasan ini sudah dicabut sebelumnya.',
                ['assignment' => ['already_revoked']]
            );
        }

        // Both fields together, or the CHECK constraint refuses the row. A
        // revocation with no recorded actor is a half-written audit fact.
        $assignment->revoked_at = now();
        $assignment->revoked_by_membership_id = $context->membershipId();
        $assignment->save();

        $this->audit->record(
            action: AuditAction::STAFF_OUTLET_REVOKED,
            subjectType: MembershipOutlet::class,
            subjectId: $assignment->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            outletId: $assignment->outlet_id,
            metadata: ['membership_id' => $assignment->membership_id],
            request: $request,
        );

        return $assignment->refresh();
    }

    // ------------------------------------------------------------------
    // Role assignment — Step 3 machinery, with the Step 4 escalation guard
    // ------------------------------------------------------------------

    public function assignRole(
        TenantContext $context,
        Membership $membership,
        string $roleKey,
        ?Request $request = null,
    ): void {
        $this->assertSameTenant($context, $membership->tenant_id);

        // DEC-0025 §8 — a PLATFORM role is never assignable through a
        // membership. Step 3's guard, called rather than reimplemented
        // (invariant A3).
        try {
            PermissionRegistry::assertAssignableToMembership($roleKey);
        } catch (InvalidArgumentException $exception) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Peran tersebut tidak dapat diberikan melalui keanggotaan tenant.',
                ['role' => ['not_assignable']]
            );
        }

        $this->assertNoEscalation($context, $roleKey);

        $role = Role::query()->where('key', $roleKey)->first();

        if ($role === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        // Idempotent: granting a role the membership already holds is a no-op
        // rather than a duplicate row or an error. An operator clicking twice
        // has not done anything wrong.
        $already = DB::table('membership_role')
            ->where('tenant_id', $context->tenantId())
            ->where('membership_id', $membership->id)
            ->where('role_id', $role->id)
            ->exists();

        if ($already) {
            return;
        }

        DB::table('membership_role')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            // Bound to the membership's tenant by composite foreign key, so a
            // cross-tenant grant is refused by PostgreSQL.
            'tenant_id' => $membership->tenant_id,
            'membership_id' => $membership->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record(
            action: AuditAction::MEMBERSHIP_ROLE_ASSIGNED,
            subjectType: Membership::class,
            subjectId: $membership->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: ['role' => $roleKey],
            request: $request,
        );
    }

    public function removeRole(
        TenantContext $context,
        Membership $membership,
        string $roleKey,
        ?Request $request = null,
    ): void {
        $this->assertSameTenant($context, $membership->tenant_id);

        $role = Role::query()->where('key', $roleKey)->first();

        if ($role === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        // REMOVAL IS NOT ESCALATION-GUARDED, and that is deliberate. Taking a
        // capability away never grants the actor anything, and requiring you to
        // hold a role in order to remove it would mean a tenant could be left
        // with a role nobody present is able to revoke.
        //
        // The permission check (`MEMBERSHIP_ROLE_REMOVE`, at the policy) is what
        // gates this; the escalation guard has nothing to protect here.
        $deleted = DB::table('membership_role')
            ->where('tenant_id', $context->tenantId())
            ->where('membership_id', $membership->id)
            ->where('role_id', $role->id)
            ->delete();

        if ($deleted === 0) {
            return;
        }

        $this->audit->record(
            action: AuditAction::MEMBERSHIP_ROLE_REMOVED,
            subjectType: Membership::class,
            subjectId: $membership->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: ['role' => $roleKey],
            request: $request,
        );
    }

    // ------------------------------------------------------------------
    // Guards
    // ------------------------------------------------------------------

    /**
     * INVARIANT A2 — a caller may not grant a capability they do not hold.
     *
     * Compared over PERMISSIONS, never over role names. A role-name comparison
     * would let a role whose grant list widened in a later release silently
     * change who is allowed to hand it out.
     *
     * Recomputed from live membership and role state on every call, so a role
     * removed a moment ago stops conferring the right to delegate it on the very
     * next request (Rule 40 hard rule 3, invariant A4).
     */
    private function assertNoEscalation(TenantContext $context, string $roleKey): void
    {
        $granted = PermissionRegistry::permissionsForTenantRoles([$roleKey]);
        $held = $this->permissions->forContext($context);

        $beyond = array_values(array_diff($granted, $held));

        if ($beyond !== []) {
            // The response names WHICH permissions were beyond the caller, so an
            // operator can ask for the right thing. It discloses nothing they
            // could not already read from the permission matrix endpoint.
            throw ApiException::of(
                ErrorCode::FORBIDDEN,
                'Anda tidak dapat memberikan peran yang memuat izin yang tidak '
                .'Anda miliki sendiri. Minta pemilik tenant untuk memberikan '
                .'peran tersebut.',
                ['role' => $beyond]
            );
        }
    }

    /**
     * Defence in depth. Every query reaching this service is already
     * tenant-scoped, so a foreign record should never arrive — but if one does
     * it fails closed, as a 404 that discloses nothing (Rule 48 hard rule 5).
     */
    private function assertSameTenant(TenantContext $context, ?string $tenantId): void
    {
        if ($tenantId === null || ! hash_equals($context->tenantId(), $tenantId)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
