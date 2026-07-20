# `app/Modules/` — backend module boundaries

The backend is a **modular monolith**: one deployable, internally divided into modules that mirror
the canonical bounded contexts (Rule 06, Rule 17). Modules communicate through defined interfaces
and domain events. **A module never reaches into another module's tables.** Shared database access
across a module boundary is what turns a modular monolith into a mud ball.

## Status

**Step 3 Phase A creates the directory boundaries only.** These directories are structural
placeholders. No authentication, tenancy resolution, RBAC enforcement, or audit implementation
exists in them yet — that work is later in Step 3 and is not claimed here. An empty directory is
never an implemented feature (Rule 01).

## The seven Step 3 modules

| Module | Boundary |
|---|---|
| [`SharedKernel`](./SharedKernel/README.md) | Types and contracts shared by every module |
| [`Identity`](./Identity/README.md) | Who the actor is — accounts, credentials, sessions, devices |
| [`Tenancy`](./Tenancy/README.md) | The isolation boundary — tenants and memberships |
| [`Authorization`](./Authorization/README.md) | What the actor may do — roles and permissions |
| [`Organization`](./Organization/README.md) | Structure inside a tenant — brands and outlets |
| [`Audit`](./Audit/README.md) | What happened — the append-only record |
| [`Platform`](./Platform/README.md) | Operating the runtime itself — health, readiness, support access |

No module beyond these seven exists in Step 3. A module for orders, payments, production, tracking,
delivery, unclaimed laundry, reporting, or subscription belongs to Step 4 or later and creating one
here is a scope breach (Rule 12, DEC-0024).

## Rules that bind every module

1. **Every business table carries `tenant_id`; every business query is tenant-scoped** (Rule 02).
2. **A client-supplied tenant identifier is never authorization proof** (Rule 02, hard rule 9).
3. **Authorization is server-side on every request.** Hiding a control is a UX affordance, never an
   access control (Rule 03).
4. **Money is integer Rupiah** and floating point is forbidden in any financial path (Rule 04). No
   money-bearing table exists in Step 3.
5. **Cross-tenant data exposure is an automatic NO-GO** (Rule 02, hard rule 12).
