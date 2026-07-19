# DEC-0002 — Multi-Tenant Architecture

## ID

DEC-0002

## Title

Multi-Tenant Architecture

## Status

ACCEPTED

## Date

19 July 2026

## Context

Aish Laundry App is a SaaS product sold to many independent laundry businesses at UMKM price points
starting at Rp79.000 per month (§21). At that price, per-customer infrastructure is impossible: a
dedicated database or a dedicated deployment per laundry would cost more than the subscription.

At the same time, the businesses served are direct competitors operating in the same neighbourhoods.
Their customer lists, pricing, revenue, and operational performance are commercially sensitive to each
other in a way that is unusual for a general-purpose SaaS.

Three shapes were available:

1. **Single-tenant per customer** — separate database and deployment per laundry. Strongest isolation,
   impossible economics at this price, and operationally unmanageable for a small team.
2. **Multi-tenant with a shared database and enforced tenant scoping** — one deployment, one database,
   every business row owned by a tenant.
3. **Hybrid** — shared application, database per tenant. Better isolation than (2), but connection and
   migration management costs grow linearly with customers and the team is one person plus agents.

The decision also had to accommodate a structural reality of the Indonesian laundry market: a single
owner frequently operates several brands across several outlets, and a staff member frequently works for
more than one business.

## Decision

Aish Laundry App is **multi-tenant with a shared database and tenant scoping enforced by construction**.

The canonical hierarchy is:

```
User Account → Membership → Tenant / Organization → Laundry Brand → Outlet
```

The **tenant** is the isolation boundary and the commercial boundary. Thirteen hard rules apply:

1. One user may join multiple tenants.
2. One owner may own or manage multiple tenants.
3. A tenant may have multiple brands.
4. A brand may have multiple outlets.
5. A tenant switcher exists.
6. Subscription and billing operate at the tenant boundary.
7. Every business table has `tenant_id`.
8. All business queries are tenant-scoped.
9. A client-supplied tenant identifier is never authorisation proof.
10. The backend verifies membership and permission, server-side.
11. Data is never merged merely because owner, email, or phone match.
12. Cross-tenant data exposure is an automatic NO-GO.
13. The portfolio dashboard must not weaken tenant isolation.

Authorisation is always evaluated against a **membership**, never against a bare user account. Brands
and outlets are scoping refinements inside a tenant and are never treated as isolation boundaries.

## Consequences

Every business table gains a non-nullable `tenant_id`. Every business query is scoped by construction —
through a base repository, a global scope, or an equivalent framework mechanism — so that omitting the
scope fails loudly rather than leaking silently. Every request derives its tenant context from the
authenticated session and a verified membership. Client-side storage is partitioned per tenant and per
user. Caches, background jobs, exports, search, and file storage all carry explicit tenant context.

Cross-tenant exposure becomes hard gate 1
([`../governance/TENANT_ISOLATION_POLICY.md`](../governance/TENANT_ISOLATION_POLICY.md)), enforced from
Step 3 by a mandatory always-run test suite.

## Positive consequences

- Economics that support a Rp79.000/bulan entry plan without subsidising infrastructure per customer.
- One deployment, one migration path, one observability surface — operable by a very small team.
- Native support for the real market structure: multi-brand owners, multi-outlet brands, and staff with
  memberships in several tenants.
- Tenant isolation becomes an architectural property rather than a premium feature, which is also a
  pricing guardrail (§21).
- A single tenant switcher gives owners and staff one coherent mental model across all four platforms.

## Negative consequences / trade-offs

- **A single defect can leak across tenants.** Shared-database multi-tenancy concentrates isolation risk
  into the query layer. This is the reason it is a hard gate rather than a review preference.
- Every developer and every agent must think about tenancy on every query, forever. The mitigation is to
  make scoping structural rather than remembered.
- Noisy-neighbour effects are possible: one very large tenant can degrade performance for others. Fair-use
  ceilings (§21.5) and per-tenant rate limiting mitigate this, imperfectly.
- Per-tenant data residency, per-tenant encryption keys, and per-tenant restore are materially harder
  than in a database-per-tenant model. If an enterprise customer ever requires them, a new decision
  record will be needed.
- Testing is heavier: the isolation suite must exercise every business endpoint cross-tenant, and it grows
  with every new endpoint.
- The owner portfolio must consolidate within a tenant only, which is less convenient for an owner who
  deliberately holds several tenants (DEC-0003).

## Verification

- From Step 3: a mandatory tenant isolation test suite that creates at least two tenants with
  deliberately colliding data, exercises every business endpoint cross-tenant, and asserts denial.
- Migration review rejects any business table without `tenant_id`.
- Static or architectural checks assert that business queries go through the tenant-scoped base layer.
- Every evidence pack from Step 3 onward carries a tenant isolation attestation
  ([`../governance/EVIDENCE_POLICY.md`](../governance/EVIDENCE_POLICY.md) §7).
- At the Step 0 baseline, verification status is honest: no runtime exists, so the suite is
  `NOT APPLICABLE`.

## Supersession policy

Superseded only by a new decision record that specifies the replacement isolation model, the migration
path for existing tenants, how existing data is partitioned, and how the isolation hard gate remains
satisfied throughout the migration. Requires a **major** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md). Weakening any of the thirteen hard rules requires its own
decision record and cannot be done implicitly.

## Related Master Source sections

- §4 Multi-tenancy
- §6 Architecture
- §7 Roles
- §12 Owner dashboard and portfolio
- §15 Security
- §21 Pricing
