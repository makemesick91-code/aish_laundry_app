# backend — Aish Laundry Laravel Modular Monolith

**Step:** 3 — Runtime, Authentication, Multi-Tenancy, and RBAC (Phase A — runtime foundation)

## What exists

| Item | Status |
|---|---|
| Laravel runtime boots | `TESTED` — `php artisan --version` → `Laravel Framework 13.20.0` |
| PostgreSQL migrations | `TESTED` — `migrate:fresh` → `migrate:rollback` → `migrate` all succeeded against PostgreSQL 18.4 |
| `/api/v1/health` (liveness) | `TESTED` — HTTP 200 |
| `/api/v1/readiness` (readiness) | `TESTED` — HTTP 200 when PostgreSQL and Redis answer; HTTP 503 when either does not |
| Module directory boundaries | `IN PROGRESS` — seven directories with boundary READMEs, no implementation |

Every status above is bound to the exact commit SHA recorded in the Step 3 evidence pack (Rule 01,
DEC-0013). Evidence produced at one SHA never carries over to another.

## What does NOT exist

| Item | Status |
|---|---|
| Authentication | `NOT IMPLEMENTED` |
| Tenant resolution and scoping enforcement | `NOT IMPLEMENTED` |
| RBAC enforcement | `NOT IMPLEMENTED` |
| Audit writing | `NOT IMPLEMENTED` |
| All product features | `NOT IMPLEMENTED` |
| Deployment | `ABSENT` |
| Application CI | `NOT APPLICABLE` |
| UAT | `NOT STARTED` |

**A migration is not an enforced invariant.** The schema created here carries composite foreign keys
that make a cross-tenant relation structurally impossible, but *nothing enforces tenant scope at the
query layer yet*, because no query layer exists. A schema constraint is a floor, not a feature.

**An endpoint answering is not a product feature.** `health` and `readiness` are operational probes.
They are evidence that the runtime boots and that its dependencies answer — nothing more.

## Locked architecture (Rule 06, DEC-0005)

| Concern | Decision |
|---|---|
| Framework | Laravel modular monolith — **one deployable** |
| API | REST JSON, versioned `/api/v1` |
| Database | PostgreSQL — the system of record |
| Cache, queue, locks, rate limiting | Redis — **never** a system of record |
| File storage | S3-compatible object storage (not yet configured) |

Changing any of these requires a new decision record, not a pull request comment.

## Layout

```
app/
  Http/Controllers/   HealthController — operational probes only
  Modules/            the seven Step 3 module boundaries (see app/Modules/README.md)
bootstrap/app.php     routing, middleware, exception configuration
config/               framework configuration
database/migrations/  the Step 3 schema
routes/api.php        the /api/v1 surface
tests/                PHPUnit; runs against real PostgreSQL, never SQLite
```

## Local development

Services (PostgreSQL 18.4, Redis 8.2) are managed by the repository scripts, not by this directory:

```bash
bash scripts/start-dev-services.sh
bash scripts/check-dev-services.sh     # proves connectivity only
```

Then, from `backend/`:

```bash
cp .env.example .env                   # .env is git-ignored and never committed
php artisan key:generate
php artisan migrate
./vendor/bin/phpunit
```

`.env.example` carries **placeholders only**. This repository is `PUBLIC` (AMENDMENT-0001,
DEC-0016), so every committed example datum is fictional and recognisably so, and **deletion is not
remediation** — a secret is compromised the moment it is pushed (Rule 23).

## Testing constraint

**PostgreSQL is the authoritative database for every integration and tenant-isolation test. SQLite is
never an acceptable substitute** and must never be presented as evidence of tenant isolation: it does
not enforce the composite foreign keys that carry the isolation guarantee.

## Scope boundary

Step 4 and later business features are forbidden here. No table, route, model, or module for
customers, services, price lists, orders, payments, receipts, production, tracking, pickup, delivery,
unclaimed laundry, reporting, or subscription may be created in Step 3. The guard is
`scripts/validate-runtime-scope.py`, which classifies scope structurally rather than by keyword.

See [`../docs/MASTER_SOURCE.md`](../docs/MASTER_SOURCE.md) and [`../docs/STATUS.md`](../docs/STATUS.md).
