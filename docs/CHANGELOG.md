# Changelog

All notable changes to **Aish Laundry App** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Version numbers in this file track the **Master Source** document version
([`MASTER_SOURCE.md`](MASTER_SOURCE.md)), not an application release — no application exists yet.

---

## [Unreleased]

Nothing yet.

---

## [1.0.1] — 2026-07-19

Master Source amendment codifying the public-repository deviation as a canonical,
re-examinable decision rather than an unexamined default. **No product decision,
no pricing figure, no roadmap number, and no architectural lock was changed.**
Classified MINOR under [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §1.2 because §15.8
is new canonical scope rather than a clarification.

### Added

- **`DEC-0016` — Public Repository Visibility Accepted Deviation**
  ([record](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
  Records that the **canonical desired visibility remains PRIVATE** and that
  PUBLIC is an accepted deviation taken to obtain platform-enforced branch
  protection on a free plan; enumerates the binding authoring constraints; states
  that governance operates in **single-maintainer** mode with independent human
  approval **ABSENT**; and defines the upgrade path, including re-verification of
  the ruleset **after** any future visibility change.
- **`MASTER_SOURCE.md` §15.8 — Public repository authoring constraints.** Every
  file is world-readable and permanently so; deletion is not remediation. No
  customer data, credentials, tokens, OTP values, private keys, `.env` files,
  production configuration, dumps, backups, sensitive server addresses, internal
  incident data containing personal data, raw authentication output, or provider
  and billing secrets. Evidence packs are sanitised and say so; every example
  datum is fictional; only `PUBLIC` and sanitised `INTERNAL` material is
  committed.

### Changed

- `MASTER_SOURCE.md` header, footer, and §21.6 — visibility is now stated as
  PUBLIC **as an accepted deviation from a canonical desired PRIVATE**, rather
  than as a bare fact.
- `MASTER_SOURCE.md` §24, `ROADMAP.md`, and `STATUS.md` — Step 0 is recorded as
  **GO**, conferred by the owner on 19 July 2026 against exact-SHA evidence,
  carrying the visibility deviation explicitly. Previously these files still read
  `IN PROGRESS`, which had become stale after the Step 0 tag.
- `MASTER_SOURCE.md` §31 — decision-record count moved from fifteen to sixteen.
- `STATUS.md` §7 rule 3 — reworded from "Step 0 must never be recorded with the
  release status word" to the accurate rule: `GO` is owner-conferred, never
  self-declared, and is written only after the owner confers it against
  exact-SHA evidence and the Step has merged.
- `ASSUMPTIONS.md` — AMENDMENT-0001 now points at DEC-0016 as the record that
  locks and extends it. The amendment text itself is unedited.
- `scripts/validate-master-source.py` — the version assertion is now anchored to
  the document header **and** footer and requires them to agree. The previous
  loose substring search would have passed even after a botched version bump,
  because historical versions are quoted in the changelog section.
- `scripts/validate-decisions.py` and `scripts/validate-required-files.py` —
  decision-record range extended to DEC-0016.

### Fixed

- The changelog footer previously stated *"no release tag has been created yet"*.
  That became false when
  `aish-laundry-step-00-master-source-governance-v1.0.0-go` was created. The
  statement is corrected below rather than quietly deleted.

---

## Step 0 closure — 2026-07-19

Evidence-only synchronization after the GO tag. No product decision, no canonical
fact, and no Master Source content was changed by this entry; the Master Source
remains v1.0.0 with an unchanged checksum.

### Changed

- `docs/STATUS.md` — Step 0 status moved from `IN PROGRESS` to `GO`, with the
  closure SHAs recorded and the visibility deviation stated explicitly.

### Added

- Step 0 evidence pack populated: exact-SHA CI, ruleset verification,
  clean-checkout verification, merge verification, GO tag verification, post-tag
  evidence, and final closure.

### Closure record

| Item | Value |
|---|---|
| Foundation PR | `#1` |
| Foundation candidate SHA | `b1bd1549b50f828b009c2241a0836ae23fcf4608` |
| Foundation merge SHA | `8494bc8543b9301351da6055337832597f1f2d9f` |
| GO tag | `aish-laundry-step-00-master-source-governance-v1.0.0-go` |
| GO tag object SHA | `e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9` |
| GO tag peeled commit | `8494bc8543b9301351da6055337832597f1f2d9f` |
| Ruleset ID | `19164588` |

Steps 1–14 remain `PLANNED`. All product features remain `NOT IMPLEMENTED`. No
runtime exists. No Step 1 work has started.

---

## [1.0.0] — 2026-07-19

Step 0 — Master Source and Governance. Baseline of the canonical governance foundation for
Aish Laundry App. **No runtime, no application, no deployment was created.**

### Added

- **`docs/MASTER_SOURCE.md` version 1.0.0**, baseline date 19 July 2026 — the single canonical source of
  truth, covering canonical rules, vision, product values, multi-tenancy, platforms, architecture, roles,
  product modules, the public tracking portal, pickup and delivery, unclaimed laundry, the owner
  dashboard, offline-first, notifications and WhatsApp, security, financial integrity, privacy, UX,
  performance, observability, pricing, MVP, non-goals, roadmap, Definition of Done, git and CI, AI
  development rules, testing, success metrics, positioning, decision records, changelog policy, and AI
  instructions.
- **Locked roadmap Step 0 to Step 14** ([`ROADMAP.md`](ROADMAP.md)), with Step 0 IN PROGRESS and
  Steps 1–14 PLANNED.
- **Canonical status file** ([`STATUS.md`](STATUS.md)) recording that all product features are
  NOT IMPLEMENTED, the backend runtime and Flutter workspace are ABSENT, deployment is ABSENT,
  application CI is NOT APPLICABLE, and UAT is NOT STARTED.
- **Status vocabulary** ([`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)) defining PLANNED,
  IN PROGRESS, TESTED, WATCH, GO, NO-GO, NOT IMPLEMENTED, ABSENT, NOT APPLICABLE, and NOT STARTED.
- **Fifteen accepted decision records** DEC-0001 … DEC-0015 in [`decisions/`](decisions/), each dated
  19 July 2026 with status ACCEPTED.
- **Tenant isolation hard-gate policy**
  ([`governance/TENANT_ISOLATION_POLICY.md`](governance/TENANT_ISOLATION_POLICY.md)) — thirteen
  non-negotiable rules; cross-tenant exposure is an automatic NO-GO.
- **Financial integrity hard-gate policy**
  ([`governance/FINANCIAL_INTEGRITY_POLICY.md`](governance/FINANCIAL_INTEGRITY_POLICY.md)) — integer
  Rupiah, idempotent payments, server-verified callbacks, corrections by reversal only.
- **Evidence policy** ([`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)) requiring
  exact-SHA binding and sanitisation of every evidence pack.
- **Required-files inventory** ([`governance/REQUIRED_FILES.md`](governance/REQUIRED_FILES.md)) listing
  every artefact Step 0 must produce.
- **Definition of Done** ([`DEFINITION_OF_DONE.md`](DEFINITION_OF_DONE.md)) — general DoD plus the Step 0
  checklist.
- **Git and release policy** ([`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md)) — PR-only to
  `main`, exact-SHA CI, annotated immutable tags, GO tag naming
  `aish-laundry-step-NN-<slug>-vX.Y.Z-go`, rollback by revert only, and the list of forbidden destructive
  operations.
- **AI execution policy** ([`AI_EXECUTION_POLICY.md`](AI_EXECUTION_POLICY.md)) — autonomous execution
  boundaries, the no-false-claims rule, evidence requirements, and the NO-GO stop conditions.
- **Tooling policy** ([`TOOLING_POLICY.md`](TOOLING_POLICY.md)) — skills, Graphify, MCP, the limit-saver
  protocol, and credential rules.
- **Governance traceability matrix**
  ([`GOVERNANCE_TRACEABILITY.md`](GOVERNANCE_TRACEABILITY.md)) mapping each foundation area to its rule
  file, decision record, and validator.
- **Assumptions register** ([`ASSUMPTIONS.md`](ASSUMPTIONS.md)) recording ASSUMPTION-0001 as
  RESOLVED / ACCEPTED and AMENDMENT-0001 for repository visibility.
- **Root governance files** — `README.md`, `CONTRIBUTING.md`, and `SECURITY.md`.
- **Runtime placeholder READMEs** for `apps/customer_android`, `apps/ops_android`, `apps/admin_web`,
  `backend`, `infrastructure`, and the nine shared packages — each stating `Status: NOT IMPLEMENTED` and
  `Runtime: ABSENT`.

### Changed

- **Repository visibility is PUBLIC** (AMENDMENT-0001). The canonical facts originally specified PRIVATE.
  GitHub's free plan cannot apply rulesets or branch protection to a private repository, verified by an
  HTTP 403 response stating "Upgrade to GitHub Pro or make this repository public". The repository owner
  was presented with the tradeoff and explicitly elected PUBLIC visibility so that branch protection could
  be enforced. Consequence recorded: commercial pricing and product decisions in this repository are
  publicly visible.

### Security

- Established the no-secrets rule across the repository, commit messages, pull requests, issues, and
  evidence packs, with rotation-first remediation for any committed credential
  ([`../SECURITY.md`](../SECURITY.md)).
- Established tenant isolation and financial integrity as automatic NO-GO hard gates (DEC-0012).
- Established the public tracking portal security rules: high-entropy hashed tokens that are not the
  order number, revocable and expiring, `noindex`, masked personal data, no full address, and OTP for
  sensitive actions (DEC-0006).
- Established exact-SHA evidence as a precondition for any GO (DEC-0013).

### Not included — stated explicitly to avoid false impressions

- No Flutter workspace, no `pubspec.yaml`, no Dart source.
- No Laravel application, no `composer.json`, no `artisan`.
- No database schema, migrations, authentication, tenancy implementation, or REST API runtime.
- No Android UI, no Flutter Web UI, no tracking portal implementation.
- No payment, WhatsApp, pickup-delivery, or H+1/H+3/H+7 implementation.
- No Docker application runtime and no deployment of any environment.
- No application CI and no tests, because there is no application code to build or test.

---

[Unreleased]: https://github.com/makemesick91-code/aish_laundry_app/compare/main...HEAD
[1.0.0]: https://github.com/makemesick91-code/aish_laundry_app/releases
[1.0.1]: https://github.com/makemesick91-code/aish_laundry_app/releases

**Tag status.** One annotated tag exists:
`aish-laundry-step-00-master-source-governance-v1.0.0-go`, tag object
`e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9`, peeled to commit
`8494bc8543b9301351da6055337832597f1f2d9f`. It is immutable and is never moved,
deleted, or re-pointed.

A tag is created only when a Step satisfies its Definition of Done with exact-SHA
evidence, following the naming convention in
[`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md). Master Source version
1.0.1 is a document amendment, not a Step closure, and therefore carries **no
tag of its own**.
