# Rule 48 — Tenant Isolation Hard Gate at Runtime

## Purpose

Rule 02 fixed tenant isolation as the product's central safety property while there was no runtime to
test it against. Step 3 is where that property first becomes something a real request can actually
violate. This rule restates the hard gate at the point it stops being theoretical, and fixes what
evidence is required before anyone may say it holds.

Backed by **DEC-0012 — Tenant Isolation and Financial Integrity Hard Gate**.

## Hard rules

1. **Cross-tenant data exposure is an automatic `NO-GO`, with no exception for severity, convenience,
   schedule, or "only in a demo."** The moment any actual or suspected cross-tenant exposure is found in
   Step 3 runtime — a member of tenant A able to read, list, count, search, export, or mutate any record
   of tenant B by any path — all further feature work on the affected surface stops immediately (Rule
   02, hard rule 12; Rule 12, NO-GO condition 1).

## Required negative-test coverage

2. Before any Step 3 authentication, tenancy, or RBAC surface is considered done, negative tests prove a
   member of tenant A cannot reach a record of tenant B through **every** access path that exists for
   that surface: direct ID lookup, list endpoint, filter parameter, free-text search, export, and signed
   or unsigned file URL (Rule 02, testing expectation; Rule 13).
3. A test that only proves isolation on the "obvious" path (direct ID) and skips list, filter, search,
   export, and file-URL paths does not satisfy this gate — each path is a distinct, independently
   exploitable surface and each is tested independently.
4. Isolation evidence is produced against PostgreSQL, bound to an exact commit SHA, and re-verified any
   time the tenant-scoping code it tests changes (Rule 01, Rule 43, Rule 47).
5. A `DENIED` or `not found` response never distinguishes "this record belongs to another tenant" from
   "this record does not exist." The two cases render identically to the caller (Rule 29, hard rule 12;
   Rule 32, hard rule 2).

## Step 3 note

**No runtime exists yet against which this gate can be exercised.** This rule fixes the evidence bar the
first authentication, tenancy, and RBAC implementation must clear. Step 3 `GO` is not conferred while any
required negative-test path is missing, unverified, or produced against a substitute database engine
(Rule 43, Rule 47, Rule 49).

## Violation handling

- **Any actual or suspected cross-tenant exposure** — automatic `NO-GO`. Stop all further feature work on
  the affected surface immediately, preserve evidence at the exact SHA, and notify the repository owner
  before anything else proceeds (Rule 02, Rule 12).
- **A tenant-isolation claim covering only the direct-ID access path** — the claim is incomplete; the
  remaining paths (list, filter, search, export, file URL) are untested and the surface is not done.
- **Isolation evidence produced against SQLite or any non-PostgreSQL engine** — the evidence is void;
  re-run against PostgreSQL (Rule 43).
- **A denial response that reveals another tenant's record exists** — treat as a cross-tenant disclosure
  path and escalate under this rule's hard rule 1, not as a minor UX inconsistency.
- **A proposal to relax isolation testing for a demo, a deadline, or a "temporary" exception** — refuse
  and escalate to the owner; there is no staging exemption for this gate (Rule 02).
