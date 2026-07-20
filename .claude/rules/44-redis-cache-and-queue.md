# Rule 44 — Redis Cache and Queue

## Purpose

Redis serves cache, queue, distributed locks, and rate limiting for the Step 3 backend (Rule 06). It is
fast precisely because it is simple, and that simplicity is exactly what makes a tenant-less key
dangerous: nothing about Redis itself stops one tenant's cached value from being read under another
tenant's request. This rule fixes the runtime discipline that keeps Redis safe to use.

## Hard rules

1. **Every cache key is tenant-partitioned.** A cache key that does not carry an explicit tenant
   dimension is a cross-tenant leak waiting to happen, and it is rejected on discovery regardless of how
   low the practical risk looked (Rule 02, hard rule 7; Rule 06, hard rule 13).

## Supporting expectations

- Redis is never a system of record. Nothing financially or legally significant lives only in Redis;
  anything Redis holds can be lost or evicted without corrupting the ledger (Rule 06, hard rule 12).
- Distributed locks guard operations that must not run concurrently for the same tenant and record —
  payment application, shift closing, membership or role changes, status transitions — and are
  themselves scoped so a lock for one tenant's operation can never block or be released by another
  tenant's (Rule 04, hard rule of concurrency; Rule 18, invariant 26).
- Rate-limiting counters used by authentication and OTP endpoints (Rule 38) are keyed by identity and
  endpoint, never globally, so one tenant's traffic cannot exhaust another tenant's allowance.
- Queue jobs carry explicit tenant context in their payload; a worker never infers tenant from "the last
  job that ran" (Rule 02, Rule 20).
- The local development Redis instance runs loopback-bound with a pinned image and holds only fictional
  data, exactly as the local PostgreSQL instance does (Rule 37, Rule 43, Rule 45).

## Step 3 note

**Redis runtime foundation is present only as a local development service.** No cache key, lock, or
queue job has been implemented by application code yet, because no backend module consumes Redis. This
rule fixes the requirement the first module that does use Redis must satisfy from its first commit,
rather than retrofitting tenant partitioning after a leak is found.

## Violation handling

- **A cache key with no tenant dimension** — reject the change outright; add the tenant dimension before
  the key is used anywhere (Rule 02).
- **Financial or legally significant state that exists only in Redis with no PostgreSQL backing** — treat
  as a financial-integrity defect (Rule 04) and correct before the feature ships.
- **A lock or rate-limit counter shared across tenants** — treat as a tenant-isolation defect (Rule 02,
  Rule 48).
- **A queued job that infers its tenant from ambient state rather than its own payload** — reject;
  require explicit tenant context on every job.
