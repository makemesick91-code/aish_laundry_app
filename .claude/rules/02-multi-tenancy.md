# Rule 02 — Multi-Tenancy and Tenant Isolation

## Purpose

Aish Laundry App is a multi-tenant SaaS serving competing laundry businesses on shared
infrastructure. Tenant isolation is the product's central safety property. A single cross-tenant
leak is not a bug to schedule — it is a business-ending event for a tenant whose customer list,
pricing, or revenue becomes visible to a competitor.

Backed by **DEC-0002 (Multi-Tenant Architecture)**, **DEC-0003 (Multi-Laundry Owner Model)**, and
**DEC-0012 (Tenant Isolation and Financial Integrity Hard Gate)**.

## The hierarchy

```
User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet
```

- **User Account** — an identity (phone/OTP or credential based).
- **Membership** — the join between a user and a tenant, carrying role and permissions. Authorization
  is derived from Membership, never from the user account alone.
- **Tenant / Organization** — the isolation boundary and the billing boundary.
- **Laundry Brand** — a commercial brand owned by a tenant.
- **Outlet** — a physical location belonging to a brand.

## The 13 hard rules

1. One user may join **multiple tenants**.
2. One owner may **own or manage multiple tenants**.
3. A tenant may have **multiple brands**.
4. A brand may have **multiple outlets**.
5. A **tenant switcher** must exist wherever a user can belong to more than one tenant.
6. **Subscription and billing operate at the tenant boundary** — not per user, not per outlet.
7. **Every business table has `tenant_id`.** No exceptions for "small" or "lookup" business tables.
8. **All business queries are tenant-scoped.** Scoping is enforced server-side by default, not
   remembered by each developer at each call site.
9. **A client-supplied tenant ID is never authorization proof.** It is an untrusted hint that must be
   validated against the authenticated user's memberships.
10. **The backend verifies membership and permission** on every request that touches tenant data.
11. **Never merge data merely because owner name, email, or phone match.** Identical contact details
    across tenants are expected and must not cause record merging or de-duplication across the
    tenant boundary.
12. **Cross-tenant data exposure is an automatic NO-GO.**
13. **The owner portfolio dashboard must not weaken tenant isolation.** Aggregating across tenants a
    user legitimately belongs to is permitted; widening the query surface to achieve it is not.

## Design consequences

- Isolation is enforced at the **data access layer**, so that forgetting a scope produces no results
  rather than another tenant's results. Fail closed, never fail open.
- Caches, queues, search indexes, exports, report files, uploaded files, and object-storage keys are
  all tenant-scoped. A cache key without a tenant dimension is a cross-tenant leak waiting to happen.
- Local device storage in the Ops app is separated per tenant and per user (see Rule 07).
- Background jobs carry explicit tenant context; they never infer it from "the last request".
- Global/platform administration is a distinct, audited path — it is never implemented by relaxing
  tenant scoping for ordinary roles.
- Platform support has **no silent tenant access**. Support impersonation is time-bound and audited
  (see Rule 03).

## Testing expectation (later steps)

When tenant functionality is built in Step 3 and beyond, isolation must be covered by explicit
negative tests: a member of tenant A must be proven unable to read, list, count, search, export, or
mutate any record of tenant B, including via ID guessing, filter parameters, report endpoints, and
file URLs. Absence of such tests blocks the Definition of Done.

## Step 0 note

No tenant implementation exists. Step 0 records these rules only. Creating any tenant implementation
in Step 0 is forbidden by the Step 0 scope guard.

## Violation handling

- **Any actual or suspected cross-tenant data exposure** — automatic **NO-GO**. Stop feature work
  immediately, notify the repository owner, preserve evidence at the exact SHA, and do not ship
  anything else from the branch until the isolation defect is fixed and covered by a regression test.
- **A business table without `tenant_id`** — the schema change is rejected. Add the column and its
  scoping before any code depends on the table.
- **Authorization derived from a client-supplied tenant ID** — treat as a security defect of the
  highest severity, not a code-style comment.
- **A proposal to relax isolation for convenience, performance, reporting, or a demo** — refuse and
  escalate to the owner. Convenience is never a sufficient reason. There is no staging exemption.
