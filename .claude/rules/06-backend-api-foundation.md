# Rule 06 — Backend and API Foundation

## Purpose

To fix the server-side architecture of Aish Laundry App so that every later step extends one
coherent system instead of accumulating incompatible services.

Backed by **DEC-0005 — API-First Modular Monolith Backend**.

## Locked architecture

| Concern | Decision |
|---|---|
| Backend framework | **Laravel modular monolith** |
| API | **REST JSON, versioned `/api/v1`** |
| Database | **PostgreSQL** |
| Cache, queue, locks, rate limiting | **Redis** |
| File storage | **S3-compatible object storage** |
| Style | **API-first modular monolith** |
| Repository | **Monorepo** |

These are locked. Changing any of them requires a **new ADR in a later step** — not a pull request
comment, not an agent's preference.

## API-first

1. Every client surface (Customer Android, Ops Android, Console Web, public tracking portal) consumes
   the same versioned HTTP API. No surface gets a private back channel or direct database access.
2. The API contract is defined before or alongside the implementation, not reverse-engineered from
   a client afterwards.
3. **Versioning**: the current version is `/api/v1`. Breaking changes require a new version; they are
   never shipped by mutating `v1` semantics under existing clients. Mobile clients update slowly —
   assume old versions stay in the field.
4. Responses are JSON with a consistent envelope, consistent error shape, and stable machine-readable
   error codes. Error messages presented to users are in Bahasa Indonesia (Rule 05).

## Modular monolith discipline

5. The backend is **one deployable**, internally divided into modules aligned with the roadmap
   domains (auth and tenancy, master data, POS/order/payment, production, tracking and messaging,
   pickup and delivery, unclaimed laundry, finance and reporting, subscription and platform admin).
6. Modules communicate through defined interfaces, not by reaching into each other's tables. Shared
   database access across module boundaries is what turns a modular monolith into a mud ball.
7. Do **not** split into microservices. That is not the decided architecture; proposing it requires
   an ADR.

## Data layer

8. **PostgreSQL** is the system of record. Every business table carries `tenant_id` and every business
   query is tenant-scoped (Rule 02) — enforced by default at the data access layer so that a missing
   scope yields nothing rather than another tenant's rows.
9. Money columns are **integer Rupiah** (Rule 04).
10. Migrations are forward-only and reviewed. Destructive migrations require explicit owner approval
    and a tested rollback plan.
11. Timestamps are stored in UTC and presented in Asia/Jakarta or outlet local time as appropriate.

## Redis

12. Redis serves cache, queue, distributed locks, and rate limiting. It is **not** a system of
    record; nothing financially or legally significant lives only in Redis.
13. **Every cache key carries a tenant dimension.** A tenant-less cache key is a cross-tenant leak.
14. Locks guard operations that must not run concurrently — payment application, shift closing,
    stock/status transitions.

## Object storage

15. S3-compatible storage holds uploads (laundry photographs, proof of pickup and delivery,
    signatures, exports).
16. Buckets are **not publicly readable or listable** for tenant data. Private files are served via
    **signed, expiring URLs** (Rule 03).
17. Object keys are tenant-scoped and unguessable. A sequential or predictable key is an enumeration
    vulnerability.

## Cross-cutting requirements

- Server-side authorization on every protected endpoint; the client never asserts its own permissions.
- Rate limiting and brute-force protection on authentication, OTP, and tracking-token endpoints.
- Idempotency support on write endpoints that clients may retry (Rule 07).
- Structured logs that never contain passwords, OTPs, tokens, or credentials (Rule 03).
- Configuration and secrets come from the environment, never from committed files.

## Step 0 note

**Backend runtime: ABSENT.** No Laravel application, `composer.json`, `artisan`, schema, migration,
or endpoint exists. In Step 0 it is forbidden to run `laravel new` or `composer create-project`, or
to create `composer.json` or any migration. `backend/` contains only `README` or `.gitkeep`. Runtime
work begins in Step 3.

## Violation handling

- **Backend scaffolding, `composer.json`, or migrations created during Step 0** — remove and report
  the scope breach.
- **A client bypassing the API** (direct DB access, private channel) — reject the change.
- **A breaking change made in place to `/api/v1`** — revert; ship it as a new version instead.
- **A tenant-less cache key, publicly readable bucket, or predictable object key** — treat as a
  security defect under Rule 03, and as a potential cross-tenant exposure under Rule 02 (automatic
  NO-GO if exposure actually occurred).
- **A proposed change to the locked stack without an ADR** — refuse and escalate to the owner.
