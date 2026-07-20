# Rule 43 — Database and Migrations

## Purpose

Tenant isolation is only as real as the database that enforces it. This rule fixes which database
engine counts as evidence, and what a migration must prove before it is trusted, so that a passing test
against the wrong engine, or an untested migration, is never mistaken for a verified guarantee.

## Hard rules

1. **PostgreSQL is the authoritative database engine for tenant-isolation evidence.** Any test result
   offered as proof that tenant isolation holds — a negative test, a scoping test, a constraint test —
   must be produced against PostgreSQL, the system of record locked by Rule 06.
2. **SQLite, or any other substitute engine, can never stand in for PostgreSQL evidence.** Different
   engines enforce constraints, locking, and query semantics differently; a test that passes against a
   substitute engine but has not been run against PostgreSQL is not evidence that it passes against
   PostgreSQL, and must not be reported as such.
3. **Every migration is tested for fresh apply, rollback, and re-apply, and this test is captured as
   evidence before the migration is trusted in any environment.** A migration that has only ever been
   applied once, on one database, with no rollback exercised, is unverified in that dimension regardless
   of how simple it looks.

## Supporting expectations

- Migrations are forward-only in normal operation and reviewed before merge; a destructive migration
  requires explicit owner approval and a tested rollback plan (Rule 06, hard rule 10).
- Every business table carries `tenant_id` from its introducing migration; a migration that adds a
  business table without it is rejected before merge (Rule 02, Rule 39).
- Timestamps are stored in UTC and presented in Asia/Jakarta or outlet local time as appropriate at the
  application layer, never converted inside the schema itself (Rule 06, hard rule 11).
- Money columns are integer Rupiah from their first migration; no financial column is ever declared as a
  floating-point type (Rule 04, hard rule 2).
- The local development database runs as a loopback-bound, pinned-image PostgreSQL instance under
  `infrastructure/`, holding only fictional seed data (Rule 37, Rule 45).

## Step 3 note

**No database schema exists yet as application-owned migrations.** The local PostgreSQL and Redis
development services are provisioned (`infrastructure/docker-compose.dev.yml`) so that Step 3 can run
real migrations against the authoritative engine once the backend foundation is scaffolded, rather than
being pushed toward a substitute for convenience. PostgreSQL runtime foundation is recorded as `PRESENT`
in the development environment; no tenant-isolation test evidence exists yet, because no migration or
query has been written (Rule 49).

## Violation handling

- **Tenant-isolation evidence produced against SQLite or any non-PostgreSQL engine** — the evidence is
  void; re-run against PostgreSQL before it may be cited (Rule 01, Rule 48).
- **A migration merged with no captured fresh-apply/rollback/re-apply test** — treat as unverified; run
  the test and capture the output before relying on the migration anywhere.
- **A business table introduced with no `tenant_id` column** — reject the migration outright (Rule 02).
- **A money column declared as `float` or `double`** — reject the migration outright (Rule 04).
- **A destructive migration applied without owner approval and a tested rollback** — treat as a
  governance breach and escalate (Rule 06, Rule 12).
