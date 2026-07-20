# Rule 42 — Backend API Runtime Foundation

## Purpose

Rule 06 locked the backend architecture as a decision: Laravel modular monolith, `/api/v1`, PostgreSQL,
Redis, S3-compatible storage. Step 3 is where that decision first becomes an actual `backend/`
directory with actual code in it. This rule fixes what the first backend code may and may not contain,
so that "the backend now exists" is never mistaken for "the product now exists."

## Hard rules

1. **POS, orders, payments, production operations, customer tracking, pickup and delivery, the
   unclaimed-laundry reminder ladder, and finance/reporting remain `NOT IMPLEMENTED`**, no matter how
   much authentication, tenancy, and RBAC foundation the Step 3 backend contains. These are Step 4 and
   later scope (Master Source roadmap, Rule 16), and their absence is a fact about the product, not a
   gap in this rule.

## Supporting expectations

- The backend is one deployable, internally divided into modules aligned with the roadmap domains (Rule
  06, hard rule 5). Step 3 populates only the auth-and-tenancy module; other module directories, if they
  exist at all in `backend/`, contain no business logic ahead of their own step.
- The API is REST JSON, versioned at `/api/v1` from its first endpoint, with a consistent response
  envelope and error shape, in Bahasa Indonesia for user-facing error text (Rule 06, hard rules 1–4).
- Modules communicate through defined interfaces, never by reaching into another module's tables; this
  applies from the very first two modules that exist, not only once there are many (Rule 06, hard rule
  6; Rule 17, hard rule 6).
- Every business table introduced carries `tenant_id` from its first migration, and every business query
  against it is tenant-scoped by default at the data-access layer (Rule 02, Rule 39, Rule 43).
- Configuration and secrets are read from the environment, never committed (Rule 06, Rule 45).

## Step 3 note

**Backend runtime remains `ABSENT`** until `backend/` contains an actual `composer.json` and application
source rather than only a `README.md`. Once it exists, it is confined to the auth, tenancy, and RBAC
foundation this step authorizes (Rule 36). A `classify` check reporting runtime within scope is not a
claim that any endpoint has been tested, that authentication works, or that tenant isolation holds
(Rule 36, hard rule 6) — those claims require their own executed evidence (Rule 47).

## Violation handling

- **A migration, table, route, or model matching a Step 4+ business feature, however named** — reject
  outright and remove it; renaming to evade structural detection (Rule 36, hard rule 4) does not make it
  acceptable.
- **A module reaching directly into another module's tables** — reject; route it through the owning
  module's interface or a domain event (Rule 06, Rule 17).
- **A business table introduced without `tenant_id`, or a query against it that is not tenant-scoped** —
  reject; treat as a tenant-isolation defect (Rule 02, Rule 48).
- **A claim that the backend "implements POS" or any Step 4+ capability because the tenancy foundation
  exists** — false claim under Rule 01; correct it immediately.
- **A secret or production configuration value found committed in backend code or config** — treat as
  compromised; rotate first, then remove (Rule 03, Rule 45).
