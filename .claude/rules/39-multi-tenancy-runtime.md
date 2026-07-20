# Rule 39 — Multi-Tenancy at Runtime

## Purpose

Rule 02 fixed the tenant hierarchy and its thirteen hard rules as documentation. Step 3 is where a
Membership row, a Tenant row, and a server-side scoping check actually exist for the first time. This
rule extends Rule 02 into the runtime the Step 3 backend actually builds, so that "tenant-scoped" stops
being a design intention and starts being an enforced property of every request.

Backed by **DEC-0002, DEC-0003, DEC-0012**, and Rule 02.

## Hard rules

1. **A client-supplied tenant identifier is never authorization proof at runtime.** On every request,
   the server re-derives the caller's tenant scope from the authenticated user's verified membership; a
   `tenant_id` supplied in a header, a body, or a query parameter is treated as an untrusted hint only,
   validated against — never substituted for — the server's own record (Rule 02, hard rule 9).
2. **Active membership is checked server-side on every request that touches tenant data.** A membership
   check is not cached indefinitely, and it is never inferred solely from a long-lived token claim; the
   server confirms the membership is still active at the time of the request.
3. **Membership revocation blocks active access immediately**, across every session and device the
   affected user currently holds — not at next login, not after a token naturally expires.
4. **An owner may hold multiple simultaneously authorized tenant memberships**, and a tenant switcher is
   available wherever a signed-in user belongs to more than one tenant (Rule 02, hard rules 1–2 and 5).
   Switching is a deliberate, confirmed action that clears the previously loaded working set (Rule 28).
5. **Laundry Brand and Outlet records never cross a tenant boundary.** A brand belongs to exactly one
   tenant and an outlet belongs to exactly one brand within that same tenant; no runtime path attaches a
   brand or outlet to more than one tenant, however the data was imported or migrated.

## Supporting expectations

- Tenant scoping is enforced at the data-access layer by default, so a missing scope produces no results
  rather than another tenant's results — fail closed, never fail open (Rule 02).
- Background jobs and queued work carry explicit tenant context; they never infer it from "the last
  request that ran" (Rule 02, Rule 20).
- A customer profile, like every other business record, is tenant-scoped: the same phone number in two
  tenants is two unrelated profiles, never merged (Rule 02, hard rule 11; Rule 18, invariant 9).
- Negative tests prove isolation across every access path — direct ID, list, filter, search, export, and
  file URL — before this rule is considered satisfied for a given surface (Rule 13, Rule 48).

## Step 3 note

**No tenancy runtime exists yet to test.** This rule records the requirement the first authentication
and tenancy backend must satisfy. The tenant-isolation hard gate and its required negative-test evidence
are recorded separately in Rule 48 and remain the controlling gate for Step 3 `GO`.

## Violation handling

- **Authorization derived from a client-supplied tenant identifier** — security defect of the highest
  severity; fix before the code path ships (Rule 02).
- **A revoked membership that still grants access on an existing session or device** — treat as a
  tenant-isolation defect; fix and add a regression test.
- **A brand or outlet record found attached to more than one tenant** — treat with the same severity as
  a cross-tenant data leak (Rule 02, Rule 48) and correct the data through an audited path.
- **A tenant switcher absent where a user holds more than one membership** — the surface specification
  is incomplete (Rule 28).
- **Any actual or suspected cross-tenant exposure discovered while implementing this rule** — automatic
  `NO-GO` under Rule 48; stop, preserve evidence at the exact SHA, and notify the owner.
