# Rule 24 — Current Step 1 Status

## Purpose

To hold one honest statement of what Step 1 is, what it produces, and — most importantly — what it does
**not** produce, so that a large body of documentation is never mistaken for a working product.

Canonical status: [`../../docs/STATUS.md`](../../docs/STATUS.md). Master Source version **1.0.1**.

## Status snapshot

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | **GO** (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 1 — Product Requirement and Domain Model | **IN PROGRESS** |
| Steps 2–14 | **PLANNED** |
| All product features | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Database | **ABSENT** |
| Deployment | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

Step 1 may carry `IN PROGRESS`, and after validation `TESTED` or `WATCH`. **`GO` is conferred by the
repository owner and is never self-declared by an agent** (Rule 01).

## What Step 1 produces

**Documentation only.** Specifically: product requirements with stable IDs; personas; jobs to be done;
user and operational journeys; a use-case catalogue; MVP scope; success metrics; a domain glossary;
bounded contexts and a context map; aggregates, entities, and value objects; domain invariants; domain
events, commands, and policies; tenant boundaries and data ownership; per-domain models; state machines;
an initial threat model; abuse cases; data classification; trust boundaries; privacy requirements;
non-functional requirements; acceptance criteria; the requirement traceability matrix; the Step 1
Definition of Done; the governance rules 16–24; Step 1 validators; and an exact-SHA evidence pack.

## What Step 1 does NOT produce

Step 1 creates **no runtime**. It is forbidden in this Step to run or create:

`flutter create` · `dart create` · `laravel new` · `composer create-project` · `npm create` ·
`pubspec.yaml` · `composer.json` · `package.json` · `artisan` · database schema · migrations · Eloquent
models · REST API runtime · authentication · tenant middleware · Flutter widgets · Android project ·
Flutter Web project · payment integration · WhatsApp integration · tracking portal runtime ·
pickup-delivery runtime · queue worker · Redis runtime · Docker application stack · any deployment.

Runtime folders (`apps/`, `backend/`, `packages/`, `infrastructure/`) continue to contain only a
`README` or a `.gitkeep`.

## The claim that must never be made

**Documentation is not implementation.** A requirement, a domain model, an invariant, a state machine, a
threat, or an acceptance criterion describes an obligation — never an achievement.

Specifically, it is a false claim under Rule 01 to say or imply that Step 1 delivered: a working feature,
a database, an API, a screen, a test suite, a build, a deployment, a CI pipeline for the application, or
any UAT result. **A written acceptance criterion is not a passed test.** A documented invariant is not an
enforced invariant.

The only executable verification in Step 1 is the governance validator set
(`bash scripts/verify-step-01.sh`), and its output is bound to an exact commit SHA (DEC-0013).

## Step boundary

- **Step 2 does not begin until Step 1 has `GO`.** Design-system work, component libraries, colour
  tokens, and screen designs belong to Step 2 and are not pulled forward.
- Runtime, authentication, tenancy implementation, and RBAC belong to **Step 3**.
- Step numbers are locked and are never reused, renumbered, swapped, merged, or split without an accepted
  decision record (Master Source §24).

## Maintenance

1. This snapshot is updated only when reality changes, alongside `docs/STATUS.md`.
2. Statuses move forward on **exact-SHA evidence** only (Rule 01).
3. Use the approved status vocabulary only: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`,
   `NOT IMPLEMENTED`, `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`, `GO`. No synonyms, no softening
   adjectives.
4. If another document contradicts this snapshot, the other document is wrong — unless the Master Source
   itself has moved, in which case this file is updated to match it.

## Violation handling

- **Any claim that a Step 1 artefact is an implemented feature** — correct it immediately and visibly,
  and state that the earlier claim was wrong (Rule 01).
- **An acceptance criterion reported as a passed test** — correct it; writing a criterion is not running
  one (Rule 22).
- **Any runtime artefact created during Step 1** — remove it and report the scope breach.
- **Step 2 work performed before Step 1 `GO`** — stop, revert the forward-leaked work, and report.
- **`GO` written for Step 1 by an agent while its pull request is open** — revert the wording; `GO` is
  the owner's to confer.
- **A status advanced without exact-SHA evidence** — revert the advancement.
