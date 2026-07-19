# DEC-0005 — API-First Modular Monolith Backend

## ID

DEC-0005

## Title

API-First Modular Monolith Backend

## Status

ACCEPTED

## Date

19 July 2026

## Context

Aish Laundry App has four client surfaces (DEC-0004) that all need the same business capabilities:
orders, production, payments, tracking, pickup and delivery, reminders, and reporting. The backend shape
determines whether that is one coherent contract or four divergent implementations.

The domain is unusually transactional for its size. Creating a single laundry order touches the customer
record, the price list, tenant and outlet scoping, the order and its items, a payment or deposit, an
audit entry, a tracking token, and a notification — all of which must succeed or fail together. Splitting
that across services would introduce distributed transactions, eventual consistency, and compensating
actions into a product whose two hard gates are *tenant isolation* and *financial integrity*.

The operating context is equally decisive: one owner plus AI agents, UMKM pricing from Rp79.000/bulan,
and a requirement to be operable without a platform team.

Options considered:

1. **Microservices** — independent deployability, at the cost of distributed transactions, network
   failure modes, multiple deployments, and cross-service tenant scoping.
2. **Unstructured monolith** — fastest to start, degenerates into an unmaintainable ball of mud where
   every module reaches into every table.
3. **Modular monolith with an API-first contract** — one deployment, one database, one transaction
   boundary, with enforced internal module boundaries.
4. **Serverless functions** — attractive cost curve at low volume, poor fit for long-running production
   workflows, queue processing, and consistent transactional guarantees.

## Decision

The backend is a **Laravel modular monolith**, exposed **API-first** through a versioned REST JSON API at
`/api/v1`.

Locked stack:

| Layer | Technology |
| --- | --- |
| Backend framework | Laravel |
| API | REST JSON, versioned `/api/v1` |
| Database | PostgreSQL |
| Cache, queue, locks, rate limiting | Redis |
| Files | S3-compatible object storage |
| Style | API-first modular monolith |
| Repository | Monorepo |

Rules:

1. **API-first.** Every capability is exposed through the versioned API before any client consumes it.
2. **No client touches the database.** Ever.
3. **No business logic lives only in a client.** A rule enforced only on the client is not enforced.
4. **The API is the contract.** All four surfaces are consumers of the same contract.
5. **Breaking changes require a new API version**, never a silent change to `/api/v1`.
6. **Module boundaries are enforced in code** — module directories, namespaces, and explicit public
   interfaces. A module owns its data and does not reach into another module's tables.
7. **PostgreSQL is the system of record.** Redis is never the system of record for business data; losing
   Redis degrades performance and must never lose money or orders.
8. **Files are private by default** in object storage and are served only through signed URLs.
9. **Background work belongs in queues** — notifications, reminders, and reports never block a user
   request.

## Consequences

One deployment, one database, one migration path, and one transaction boundary for the order lifecycle.
Tenant scoping is enforced in one query layer rather than replicated across services (DEC-0002).
Financial operations occur inside a single database transaction, which makes the idempotency and
reversal requirements of DEC-0012 straightforward rather than distributed-systems research. Module
boundaries keep future extraction into services a refactor rather than a rewrite. The API contract makes
the offline queue in §13 well-defined, because there is exactly one server-side entry point per
operation.

## Positive consequences

- Transactional correctness for the order-plus-payment path, which directly supports hard gate 2.
- Tenant scoping enforced in one place, which directly supports hard gate 1.
- Operational cost proportionate to a Rp79.000/bulan product: one application, one database, one Redis.
- A single contract that all four clients share, eliminating divergent behaviour between Ops, Customer,
  Console, and Portal.
- Laravel's queue, scheduling, and validation facilities cover the reminder ladder (§11), notification
  dispatch (§14), and background reporting without additional infrastructure.
- Debugging a customer's order means reading one log stream, not correlating six.

## Negative consequences / trade-offs

- **A single deployment is a single blast radius.** A bad deploy affects every tenant at once. Mitigated
  by CI discipline (§26), exact-SHA evidence (DEC-0013), and revert-only rollback — not eliminated.
- **Scaling is coarse.** A heavy reporting workload and a latency-sensitive POS workload scale together.
  Queue separation helps; independent scaling does not exist.
- **Module boundaries rely on discipline plus tooling**, not on network boundaries. Without review and
  architectural checks, a modular monolith degrades into an unstructured one.
- **One language and one framework for the backend** narrows the ability to adopt a better-suited tool
  for a specific subproblem.
- **API-first costs time up front.** Exposing a capability through a versioned contract before a client
  consumes it is slower than letting a client reach directly for what it needs — and that slowness is the
  point.
- **Database migrations affect all tenants simultaneously**, so a migration mistake is a
  platform-wide incident.

## Verification

- Step 3: the versioned API exists at `/api/v1`; the modular structure is in place; no client accesses
  the database.
- Architectural checks assert that modules do not reach into one another's tables and that public
  interfaces are respected.
- Contract tests assert that API responses match the published contract, so clients cannot be silently
  broken (§28).
- Integration tests run against a real PostgreSQL and a real Redis, not mocks (§28).
- Review rejects any business rule implemented only in a client.
- At the Step 0 baseline: the backend runtime is `ABSENT`; there is no `composer.json`, no `artisan`, no
  schema, and no API.

## Supersession policy

Superseded only by an architecture decision record that names the replacement backend architecture,
justifies it with measured evidence of a limit actually reached, and specifies the migration path
including how both hard gates remain satisfied throughout. Extracting a single module into a service is
itself a new decision record and does not implicitly supersede this one. Requires a **major** version
bump of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §6 Architecture
- §8 Product modules
- §13 Offline-first
- §16 Financial integrity
- §19 Performance
- §20 Observability
