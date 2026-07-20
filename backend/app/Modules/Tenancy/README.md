# Module — Tenancy

Bounded context: **Tenant and Organization** (Rule 17). This module owns the product's central
safety property.

## Boundary

Owns the **isolation boundary** and the join between a user and a tenant.

```
User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet
```

`Tenancy` owns the first three levels: it owns `tenants` and `memberships`, and it owns the
resolution and enforcement of tenant scope for every request. `Organization` owns brands and
outlets beneath the boundary this module defines.

## In scope

- Tenants (`tenants`) — the isolation boundary and the billing boundary.
- Memberships (`memberships`) — the join carrying a user's relationship to a tenant. Unique on
  `(tenant_id, user_id)`.
- Tenant context resolution for a request, and the default-on scoping applied at the data access
  layer.
- Tenant switching for a user who belongs to more than one tenant.

## Out of scope

- **Roles and permissions attached to a membership** — `Authorization` owns those, including
  `membership_role`.
- **Subscription and billing.** They operate *at* the tenant boundary (Rule 02, hard rule 6) but
  belong to Step 12.

## The hard gate

**Any cross-tenant data exposure is an automatic NO-GO.** No exceptions, no temporary bypass, no
staging exemption (Rule 02, hard rule 12).

1. **Every business table carries `tenant_id`.** No exception for "small" or "lookup" business
   tables.
2. **Scoping is enforced server-side by default at the data access layer**, so that a forgotten
   scope yields *no rows* rather than another tenant's rows. Fail closed, never fail open.
3. **A client-supplied tenant ID is never authorization proof.** It is an untrusted hint validated
   against the authenticated user's memberships.
4. **The backend verifies membership and permission on every request** that touches tenant data.
5. **Caches, queues, search indexes, exports, report files, and object-storage keys are all
   tenant-scoped.** A cache key without a tenant dimension is a cross-tenant leak waiting to happen.
6. **Background jobs carry explicit tenant context.** They never infer it from "the last request".
7. **Denial and absence are indistinguishable across a tenant boundary.** A denial never confirms
   that another tenant's record exists.

## Status

`NOT IMPLEMENTED` — directory boundary only. No tenancy enforcement exists; the migrations in this
Step create the structure that later Phases will enforce. **A schema constraint is not an enforced
invariant.**
