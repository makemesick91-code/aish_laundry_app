# DEC-0025 — Platform-Managed Role Catalogues and Tenant-Scoped Authorization

**ID:** DEC-0025
**Title:** Platform-Managed Role Catalogues and Tenant-Scoped Authorization
**Status:** ACCEPTED
**Date:** 20 July 2026

---

## Context

Step 3 introduces the first runtime authorization model. The schema delivered at
`448b28c1e87d571d2e9de60f998e18f1fbe35b9a` places `roles` and `permissions` in tables that carry
**no `tenant_id`**, while the tenant-scoped authorization fact lives in `membership_role`, bound by a
composite foreign key to `memberships(tenant_id, id)`.

That asymmetry is deliberate, but it was a modelling judgement made during implementation rather than
a recorded product decision, and Rule 17 requires aggregate ownership to be decided before code
depends on it. Left unrecorded, two opposite readings are available to a future contributor:

- that `roles` is a shared catalogue and adding a tenant's custom role is simply a new row — which
  would silently make one tenant's role visible to every other tenant; or
- that `roles` is missing a `tenant_id` by oversight — which would invite someone to "fix" it by
  adding the column, changing the meaning of every existing assignment.

Both readings are wrong, and both are damaging. This decision fixes the intended one.

The owner has authorised the architecture recorded below.

## Options considered

**Option 1 — Tenant-owned role rows (`roles.tenant_id`).**
Each tenant gets its own role records. Rejected for Step 3: it multiplies the catalogue by tenant
count, makes a canonical permission registry impossible to state, and creates a cross-tenant leak
surface on a table that has no business carrying tenant data yet. It also front-loads a custom-role
feature nobody has asked for.

**Option 2 — Roles as free-text strings on the membership.**
Rejected outright. A free-text authorization value cannot be validated, cannot be enumerated for a
permission matrix, and turns every typo into a silent privilege change.

**Option 3 — Platform-managed canonical catalogues, tenant-scoped assignment.**
**Adopted.** `roles` and `permissions` are fixed, platform-defined catalogues. The tenant-scoped fact
is *which membership holds which role*, and that record is tenant-bound by database constraint.

## Decision

1. **`roles` and `permissions` are canonical platform-managed catalogues.** Their contents are
   defined by the platform, not by tenants, and are seeded and versioned as part of the application.

2. **Tenant authorization is granted through an active tenant-scoped `Membership` and its
   `MembershipRole` assignments.** There is no other path to a tenant permission.

3. **A role record without `tenant_id` does not itself grant access.** A row in `roles` is a *name for
   a capability set*, never an entitlement. Reading `roles` conveys nothing about who may do what in
   which tenant.

4. **A role assignment must be bound to the same tenant as the membership it grants.** This is
   enforced in the database by the composite foreign key
   `membership_role_tenant_membership_foreign` referencing `memberships(tenant_id, id)`, so a
   cross-tenant assignment is rejected with `SQLSTATE 23503` regardless of what application code
   attempts.

5. **Effective permissions are recomputed from current active membership and current role
   assignments** on every authorization decision. They are never cached into a long-lived token and
   never trusted from the client.

6. **Membership revocation immediately invalidates tenant access.** The next authorization decision
   returns zero effective tenant permissions. Nothing waits for a token to expire.

7. **Role removal immediately invalidates the affected authorization.** Same mechanism, same
   immediacy.

8. **Platform roles and tenant roles are separate categories** and are never interchangeable. A
   platform role is not assignable through `membership_role`, and a tenant role confers no platform
   capability.

9. **Platform Super Admin and Platform Support do not silently inherit tenant access.** Platform
   Support defaults to **no tenant-data access**. Any future support access to tenant data must be
   explicit, time-bound, reason-bound, and audited (Rule 03). A platform role that could read tenant
   data by default would be the silent back door Rule 02 exists to prevent.

10. **Tenant-defined custom roles require a future schema change and a new canonical decision.** They
    are `DEFERRED` and are **`NOT IMPLEMENTED`** in Step 3.

11. **No custom-role feature may be claimed in Step 3**, in documentation, UI copy, release notes, or
    a status table.

## Consequences

The permission registry is a single canonical artefact that can be enumerated, matrixed, and tested.
Cross-tenant role assignment is structurally impossible rather than conventionally discouraged.
Tenants cannot define their own roles until a later step decides that deliberately.

### Positive consequences

- One source of truth for the RBAC matrix, generated from the registry rather than hand-maintained.
- Cross-tenant assignment is rejected by PostgreSQL, so an application-layer bug cannot produce it.
- Membership and role changes take effect immediately, because nothing durable caches the decision.
- The platform/tenant boundary is a category distinction, not a naming convention.

### Negative consequences / trade-offs

- **Tenants cannot express bespoke roles.** A tenant whose org chart does not match the canonical
  roles must approximate it. That is a real product limitation, accepted for Step 3.
- **Recomputing permissions per decision costs work per request.** The alternative — embedding
  authorization facts in a token — is what makes revocation slow, so the cost is accepted
  deliberately.
- **Adding a role later is a platform change**, requiring a release rather than tenant
  self-service.
- Under single-maintainer governance (DEC-0017), a modelling defect that both the maintainer and the
  validators miss is not caught. That residual risk is accepted, not eliminated.

## Verification

The structural guarantee is proven against **authoritative PostgreSQL 18.4**, not a substitute, with
a control case and a violation case for each constraint. A rejection counts as evidence only when the
control case is accepted, every required column is supplied, and the asserted `SQLSTATE` and
constraint name match the intended control.

Proven at the schema level:

- same-tenant `outlets` row — **ACCEPTED**;
- `outlets` row in tenant B referencing tenant A's brand — **REJECTED**, `SQLSTATE 23503`,
  `outlets_tenant_brand_foreign`;
- `membership_role` bound across tenants — **REJECTED**, `SQLSTATE 23503`,
  `membership_role_tenant_membership_foreign`.

**Two earlier proofs of this property were INVALID and are recorded rather than deleted.** The first
failed on a missing `slug` `NOT NULL` column and the second on a missing `id`; both produced a
rejection, and neither rejection was caused by the tenant constraint. Only the third revision is
authoritative. Neither invalid attempt may be cited as tenant-isolation evidence.

The governing principle, applied to every proof in Step 3: **a rejection is evidence only when the
expected operation was reached and the expected security control caused the rejection.**

## Requirement references

`TEN-` class requirements generally, and the tenant-isolation hard gate recorded in the Master
Source. `SEC-` class requirements covering server-side authorization and least privilege.

## Threat references

Addresses: cross-tenant role assignment; privilege escalation through a client-supplied role field;
stale authorization surviving membership revocation; silent platform access to tenant data; and role
enumeration disclosing another tenant's structure.

## Rule references

Rule 02 (tenant isolation and the 13 hard rules), Rule 03 (least privilege, server-side
authorization, audited support access), Rule 17 (aggregate ownership decided before code depends on
it), Rule 18 (invariants enforced server-side at every entry point), Rule 39 (multi-tenancy runtime),
Rule 40 (RBAC and authorization), Rule 48 (tenant-isolation hard gate).

## Supersession policy

Superseded only by a later accepted decision record that names DEC-0025 explicitly and states what
replaces it. Introducing tenant-defined custom roles requires such a record **and** a schema change;
it is not covered by this decision and may not be added incrementally. Narrowing the model needs no
new decision — authorization may always be made stricter.

## Related Master Source sections

§4 multi-tenancy; §7 roles; §15 security; §24 roadmap and step locking. Recorded at Master Source
version **1.4.0**; this decision documents an authorization model introduced in Step 3 and changes no
existing product decision, so it carries no further version bump.
