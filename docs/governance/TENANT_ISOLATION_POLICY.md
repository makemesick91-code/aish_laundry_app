# Tenant Isolation Policy — Aish Laundry App

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §4 and §15
Locked by [DEC-0002](../decisions/DEC-0002-multi-tenant-architecture.md),
[DEC-0003](../decisions/DEC-0003-multi-laundry-owner-model.md), and
[DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md)
Baseline date: 19 July 2026

**Cross-tenant data exposure is an automatic NO-GO.**

This is hard gate 1. It blocks merge, blocks release, blocks a GO tag, and is never waived, never
deferred, and never traded against a deadline.

---

## 1. Why this is a hard gate

Aish Laundry App holds the customer lists, pricing, revenue, and operational detail of competing laundry
businesses in the same database. A leak between two tenants is not a bug with a severity score; it is the
disclosure of one business's commercial secrets to another. It cannot be apologised for, cannot be
undone, and would end the product's credibility in a market that runs on personal trust.

There is therefore no "small" isolation defect and no acceptable exposure window.

---

## 2. The hierarchy

```
User Account
    └── Membership
            └── Tenant / Organization
                    └── Laundry Brand
                            └── Outlet
```

- **User Account** — a person, identified by phone number. One person, one account across the platform.
- **Membership** — the link between a user account and a tenant, carrying roles and permissions **within
  that tenant**. All authorisation is evaluated against a membership.
- **Tenant / Organization** — the isolation boundary and the commercial boundary. Subscription, billing,
  and plan limits live here.
- **Laundry Brand** — a customer-facing brand belonging to one tenant.
- **Outlet** — a physical location belonging to one brand.

The **tenant** is the isolation boundary. Brands and outlets are scoping refinements *inside* a tenant;
they are not isolation boundaries and must never be relied upon as if they were.

---

## 3. The thirteen hard rules

These are canonical and non-negotiable.

1. **One user may join multiple tenants.**
2. **One owner may own or manage multiple tenants.**
3. **A tenant may have multiple brands.**
4. **A brand may have multiple outlets.**
5. **A tenant switcher exists** in every authenticated client.
6. **Subscription and billing operate at the tenant boundary.**
7. **Every business table has `tenant_id`.**
8. **All business queries are tenant-scoped.**
9. **A client-supplied tenant identifier is never authorisation proof.**
10. **The backend verifies membership and permission**, server-side, on every request.
11. **Data is never merged merely because owner name, email, or phone number match.**
12. **Cross-tenant data exposure is an automatic NO-GO.**
13. **The portfolio dashboard must not weaken tenant isolation.**

---

## 4. Implementation requirements

These become binding from **Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC**.

### 4.1 Data layer

1. Every business table carries a non-nullable `tenant_id` with a foreign key to the tenant.
2. Every index that supports a business query leads with or includes `tenant_id`.
3. Foreign keys never cross a tenant boundary; a child row's tenant always equals its parent's tenant,
   enforced at the database level where possible.
4. A migration that creates a business table without `tenant_id` is rejected in review.
5. Unique constraints on business data are scoped within a tenant, never globally, unless the value is
   genuinely global (for example a user's phone number as an identity).

### 4.2 Query layer

6. Tenant scoping is applied **by construction**, not by discipline: a base query builder, repository
   base class, or global scope applies the tenant filter so that forgetting it is a framework-level
   failure rather than a silent leak.
7. Any query that deliberately bypasses tenant scoping — platform administration, background maintenance
   — is explicitly named, separately located, individually reviewed, and audited.
8. Raw SQL touching business data is exceptional, justified in review, and carries its tenant predicate
   explicitly.

### 4.3 Request layer

9. The tenant context is derived from the authenticated session and the **verified** membership,
   server-side.
10. A `tenant_id` arriving in a URL, header, body, or cookie is a **hint that must be verified**, never a
    grant. Rule 9.
11. Every request verifies **both** membership in the tenant **and** the specific permission required for
    the action. Membership alone is not enough.
12. Authorisation failures return a response that does not disclose whether the requested resource
    exists in another tenant.
13. Identifiers exposed to clients are non-enumerable, so that guessing an identifier is not a viable
    probe.

### 4.4 Client layer

14. Local storage on device is partitioned **per tenant and per user**
    ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §13).
15. Switching tenant clears or partitions in-memory and on-disk caches so no previous-tenant data is
    visible.
16. Switching user clears everything belonging to the previous user.
17. The offline queue is partitioned per tenant; a queued operation can never be replayed into the wrong
    tenant.

### 4.5 Cross-cutting surfaces

18. **Search** never returns results outside the active tenant.
19. **Exports and reports** are tenant-scoped, and the export file states the tenant it belongs to.
20. **File storage** paths are tenant-partitioned, and signed URLs are issued only after a tenant-scoped
    authorisation check.
21. **Background jobs and queued work** carry the tenant context explicitly; a job never infers the
    tenant from ambient state.
22. **Notifications** are dispatched from a tenant-scoped context; a template never renders another
    tenant's data.
23. **Telemetry and logs** may record a tenant identifier for filtering, never personal data, and are not
    a bypass around isolation ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §20).
24. **Caches** key on tenant; a cache key collision across tenants is an isolation defect.

### 4.6 The owner portfolio

25. The portfolio consolidates **within a single tenant** across that tenant's brands and outlets.
26. An owner with several tenants **switches tenants**; consolidation across tenants is never achieved by
    relaxing the tenant filter.
27. If a cross-tenant view is ever built, it requires its own decision record, explicit consent, separate
    authorisation, and full audit. It is not built by weakening rule 8.

### 4.7 Support access

28. **Platform support has no silent tenant access.**
29. Support impersonation is explicit, time-bound, reason-recorded, and fully audited
    ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15.5).
30. Impersonation sessions are visibly distinct in the interface and terminate automatically.

### 4.8 Identity, never merging

31. Two customer records are never merged because a phone number, an email address, or an owner name
    matches. Rule 11.
32. A person legitimately existing in two tenants has two independent customer records with independent
    histories. This is correct, not duplication.
33. Any merge feature operates strictly within a single tenant and requires an explicit, permissioned,
    audited action by a human.

---

## 5. Verification

### 5.1 Mandatory test suite

From **Step 3** onward, a dedicated tenant isolation suite runs on every pull request and every merge.
It must:

1. Create at least two tenants with overlapping-looking data — same owner name, same customer phone
   number, same outlet name.
2. Exercise **every** business endpoint with a valid token from tenant A against identifiers belonging to
   tenant B, and assert denial.
3. Assert that denial responses do not disclose the existence of the other tenant's resource.
4. Assert that search, export, report, and file-download surfaces never cross the boundary.
5. Assert that a tenant identifier supplied by the client cannot override the verified context.
6. Assert that background jobs and queued operations execute in the correct tenant context.
7. Assert that a tenant switch clears client-side state.
8. Fail the build on any violation.

### 5.2 Review checklist

Every pull request that touches business data is reviewed for:

- [ ] New tables have `tenant_id`, non-nullable, indexed.
- [ ] New queries are tenant-scoped by construction.
- [ ] No new code path accepts a client-supplied tenant identifier as authorisation.
- [ ] New endpoints verify membership **and** permission.
- [ ] New cache keys include the tenant.
- [ ] New background jobs carry explicit tenant context.
- [ ] New client-side storage is partitioned per tenant and user.
- [ ] The isolation suite covers the new surface.

### 5.3 Evidence

Every evidence pack from Step 3 onward contains a tenant isolation attestation recording which endpoints
were exercised, the results, and the unedited test output
([`EVIDENCE_POLICY.md`](EVIDENCE_POLICY.md) §7).

---

## 6. Incident response

If cross-tenant exposure is suspected or confirmed:

1. **Declare NO-GO immediately.** Do not merge, do not release, do not tag.
2. **Stop the exposure** — disable the affected surface if it is deployed.
3. **Determine scope**: which tenants, which data, what period, whether it was accessed.
4. **Preserve evidence**, sanitised, in the evidence pack.
5. **Notify the affected tenants honestly.** Concealment is not an option
   ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §3.1).
6. **Fix the cause, not the symptom.** A defect that reached production means the isolation suite had a
   gap; the suite is extended in the same pull request as the fix.
7. **Record the incident** and its remediation.

---

## 7. Status at the Step 0 baseline

**No runtime exists**, so no tenant isolation implementation exists and none is claimed
([`../STATUS.md`](../STATUS.md)):

| Item | Status |
| --- | --- |
| Tenant isolation implementation | NOT IMPLEMENTED |
| Tenant isolation test suite | NOT APPLICABLE |
| Backend runtime | ABSENT |

This policy is a binding constraint on future work, not a description of an existing control. It becomes
enforceable and testable at **Step 3**.
