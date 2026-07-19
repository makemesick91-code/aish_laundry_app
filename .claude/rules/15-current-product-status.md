# Rule 15 — Current Product Status (Canonical Snapshot)

## Purpose

To hold one authoritative, honest statement of what exists in Aish Laundry App today, so that no
document, pull request, report, or agent response can drift into describing a product that has not
been built.

Master Source version **1.1.0**, baseline date **19 July 2026**.

## Canonical status snapshot

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | **GO** (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 1 — Product Requirement and Domain Model | **PLANNED** |
| Step 2 — Design System and UX Foundation | **PLANNED** |
| Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC | **PLANNED** |
| Step 4 — Laundry Master Data | **PLANNED** |
| Step 5 — POS, Order, and Payment Foundation | **PLANNED** |
| Step 6 — Production Operations | **PLANNED** |
| Step 7 — Customer Tracking and WhatsApp | **PLANNED** |
| Step 8 — Pickup and Delivery Operations | **PLANNED** |
| Step 9 — Unclaimed Laundry and Cashflow Recovery | **PLANNED** |
| Step 10 — Finance, Reports, and Owner Portfolio | **PLANNED** |
| Step 11 — Customer Android Experience | **PLANNED** |
| Step 12 — Subscription and Platform Administration | **PLANNED** |
| Step 13 — Security, Performance, Backup, and Recovery | **PLANNED** |
| Step 14 — Pilot and Commercial Launch | **PLANNED** |
| **All product features** | **NOT IMPLEMENTED** |
| **Backend runtime** | **ABSENT** |
| **Flutter workspace** | **ABSENT** |
| **Deployment** | **ABSENT** |
| **Application CI** | **NOT APPLICABLE** |
| **UAT** | **NOT STARTED** |

## What this means concretely

- There is **no** Laravel application, `composer.json`, `artisan`, database, schema, migration, or
  API endpoint. Backend runtime is **ABSENT**.
- There is **no** Flutter or Dart project, `pubspec.yaml`, screen, widget, or design system
  implementation. Flutter workspace is **ABSENT**.
- There is **no** authentication, tenancy implementation, POS, payment, production workflow, tracking
  portal, WhatsApp integration, pickup/delivery, unclaimed-laundry reminder, reporting, or
  subscription code. All product features are **NOT IMPLEMENTED**.
- Nothing is deployed anywhere. Deployment is **ABSENT**.
- There is no application build or test pipeline; Step 0 automation is governance validation only.
  Application CI is **NOT APPLICABLE**.
- No user acceptance testing has occurred. UAT is **NOT STARTED**.

## What does exist

Governance and documentation artifacts only: the Master Source, decision records, this rule set,
`CLAUDE.md`, the destructive-operations guard, governance validation scripts, and placeholder
directory structure.

**Runtime folders (`apps/`, `backend/`, `packages/`, `infrastructure/`) contain only `README` or
`.gitkeep` files. An empty folder is never an implemented feature and must never be described as
one.**

## Repository facts

- Remote repository: `makemesick91-code/aish_laundry_app`; default branch `main`.
- Local monorepo root directory: `aish_laundry` (ASSUMPTION-0001 — resolved and accepted; the local
  directory name and the remote repository name differ intentionally).
- Repository visibility: **PUBLIC**, by deliberate owner decision recorded in AMENDMENT-0001, taken
  because GitHub's free plan cannot apply rulesets or branch protection to private repositories
  (verified HTTP 403). The accepted consequence is that commercial pricing and product decisions in
  this repository are publicly visible. **Never claim this repository is private.**

## Maintenance rules

1. This snapshot is updated **only** when reality changes, and only alongside the Master Source.
2. Statuses move forward on **exact-SHA evidence** only (Rule 01).
3. **`GO` is owner-conferred and never self-declared by an agent.** While a Step's pull request is open,
   the maximum permissible status is `IN PROGRESS`, `TESTED`, or `WATCH`. Step 0 carries `GO` because the
   owner conferred it on 19 July 2026 against exact-SHA evidence after merge, with the PUBLIC-visibility
   deviation recorded rather than hidden (DEC-0016).
4. Use the approved status vocabulary only (Rule 01). No synonyms, no softening adjectives.
5. If another document contradicts this snapshot, the other document is wrong — unless the Master
   Source itself has moved, in which case update this file to match it.

## Violation handling

- **Any document, comment, PR description, or agent response claiming a feature, runtime, deployment,
  CI pipeline, or UAT result that this snapshot marks otherwise** — correct it immediately and
  visibly; state that the earlier claim was wrong (Rule 01).
- **An empty folder or README presented as an implemented feature** — remove the claim.
- **This snapshot advanced without exact-SHA evidence** — revert the advancement.
- **`GO` written by an agent for a Step whose pull request is still open** — revert the wording before
  the pull request proceeds. `GO` is the owner's to confer.
- **This repository described as private** — correct it to PUBLIC and cite AMENDMENT-0001 and DEC-0016.
  Note also that PUBLIC is an accepted *deviation*: describing it as the canonical desired state is a
  different error, and equally wrong.
- Repeated status inflation is grounds for the owner to reject the branch entirely.
