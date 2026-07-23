# Changelog

All notable changes to **Aish Laundry App** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Version numbers in this file track the **Master Source** document version
([`MASTER_SOURCE.md`](MASTER_SOURCE.md)), not an application release — no application exists yet.

---

## Step 4 — GO — 22 July 2026

Not a Master Source version change; a roadmap-lifecycle milestone.

- **Step 4 (Laundry Master Data) reached `GO`**, owner-conferred against
  exact-SHA evidence after PR #18 merged as merge commit
  `af31ea3b0945b274b249ff21cf30918cb2d17a5f`.
- Immutable annotated tag **`aish-laundry-step-04-laundry-master-data-v1.0.0-go`**
  (object `55ed19761714aea945ecfcc919a78bae769339ac`) peels to the merge commit —
  never to the later evidence commit.
- Post-merge CI: 11/11 workflows green at the exact merge SHA on `main`. A fresh
  clean-checkout re-verification at the merge SHA passed every gate, including
  the two build gates that a disk-exhausted earlier run had left unresolved.
- FR-024 and FR-025 `COMPLETE_AND_VERIFIED`. Seven requirements remain
  `STEP_5_E2E_PENDING`; FR-036 is a mandatory Step 5 financial-integrity
  obligation. Step 4 `GO` does not start Step 5 and does not authorise deployment.
- The Step 3 GO tag is unchanged.

---

## [1.4.4] — 22 July 2026

### Added
- **DEC-0034** — a Step 3 password-reset token-disclosure correction co-delivered
  in PR #18, classified
  `STEP_3_POST_GO_SECURITY_CORRECTION_DISCOVERED_DURING_STEP_4`. The Step 3 `GO`
  tag is not moved and Step 3's original evidence is not rewritten.
- `docs/deployment/DATABASE_ROLE_PREREQUISITE.md` — the non-superuser, non-owner
  application database role the consent and price-list guarantees require at
  deployment time. Marked `REQUIRED_FOR_FUTURE_DEPLOYMENT` /
  `NOT_YET_PROVISIONED` / `NOT_CLAIMED_AS_CURRENT_CONTROL`.
- `scripts/capture-step-04-evidence.sh` — one-command evidence recapture that
  refuses to run on a dirty tree.

### Changed
- Master Source **1.4.3 → 1.4.4**; checksum regenerated through tooling. All
  three derived validators moved with the bump **and were re-run at the same
  SHA** — the omission of that second half is what made `verify-step-04` fail at
  HEAD after the 1.4.3 bump (finding N1).

### Fixed
- Consent append-only triggers were `ENABLE ORIGIN` and therefore bypassable
  under `session_replication_role='replica'`; now `ENABLE ALWAYS`, with a
  behavioural bypass test and a live-schema assertion on `tgenabled='A'`.
- A published price list could be soft-deleted through a query-builder delete,
  which fires no Eloquent model event; the guard moved to engine-level triggers.
- A plaintext password-reset token was written to a log, and its first
  replacement wrote it to a production-reachable file; production now refuses
  outright.
- `internal_notes` was emitted at every masking context including `NONE`; now
  context-gated server-side, with the key absent rather than null.

### Security
- 946 token-bearing lines purged from an untracked local log (never a
  public-repository disclosure).

---

## [Unreleased]

Nothing yet.

---

## [1.3.0] — 2026-07-19

**Step 2 — Design System and UX Foundation.** Documentation only. **No runtime was
created**, and none may be claimed.

### Added

- **Design tokens** — `docs/design/tokens/`, 16 files, 249 tokens in three layers
  (primitive, semantic, component alias) validating against a committed JSON
  Schema. 0 duplicates, 0 unresolved references, 0 circular references. Every
  colour carries its RGB form, its usage contract, and a contrast figure
  **computed from the hex value, never asserted**.
- **Design system documentation** — `docs/design/`: principles, brand foundation,
  colour and contrast, typography, spacing/sizing/density, shape/border/elevation,
  iconography, motion and reduced motion, responsive foundation, platform
  adaptation, accessibility, content design, the Bahasa Indonesia UX copy
  glossary, data visualisation, a 70-component catalog, a component state matrix
  (70 × 17 = 1190 resolved cells, 0 blank), form and validation patterns, a design
  debt register, a design decision log, and design traceability.
- **UX foundation** — `docs/ux/`: information architecture for all four surfaces,
  a role navigation matrix covering all 14 roles, a tenant and outlet context
  model, a global search model, an inventory of **89 screens**, **33 critical
  journeys**, a **20-state UX state model** in which every state carries a
  recovery path, offline and sync UX distinguishing nine sync states, security and
  privacy UX, per-surface UX documents, a usability test plan (`NOT STARTED`), UX
  acceptance criteria, and open questions.
- **32 low-fidelity SVG wireframes** — each labelled `LOW-FIDELITY — NOT
  IMPLEMENTED`, valid XML, with no script, no remote reference, no embedded
  binary, and no personal data.
- **Design and UX threat review** — `docs/security/DESIGN_AND_UX_THREAT_REVIEW.md`,
  36 findings across 32 review areas. **0 `CRITICAL` open, 0 `HIGH` open.**
- **UX requirement classification** — all **498 / 498** requirements classified
  `UI-DIRECT`, `UI-INDIRECT`, `NON-UI` or `DEFERRED-UX`. **0 unclassified**, and
  every security, tenancy, financial, offline and tracking requirement remains
  visible in the mapping.
- **Decision records** DEC-0018 … DEC-0023.
- **Application rules** 25 … 35.
- **23 Step 2 validators** plus `scripts/verify-step-02.sh`, and an adversarial
  mutation harness (`scripts/test-step-02-validators.sh`) that breaks the
  repository in 30 specific ways and requires each break to be caught.
- **Three CI workflows** publishing the unique contexts `design-system`,
  `ux-foundation` and `accessibility-privacy`.
- **Master Source §18.5 and §35**, recording the locked foundation decisions and
  making the Step 2 artefacts canonical for their subject matter.

### Changed

- Master Source **1.2.0 → 1.3.0** (MINOR: additive). Checksum regenerated by tool
  from the final file content, never hand-edited.
- `docs/STATUS.md` and `docs/ROADMAP.md` — Step 2 moved from `PLANNED` to
  `IN PROGRESS`; Step 1 restated as `GO`.
- `CLAUDE.md` — status table and the rule index extended to rules 25 … 35; the
  no-runtime boundary extended to Step 2.

### Not changed, and deliberately so

- No product decision was reversed, no pricing figure altered, no roadmap number
  renumbered, and no architectural lock touched.
- **Every product feature remains `NOT IMPLEMENTED`.** The backend runtime is
  `ABSENT`, the Flutter workspace is `ABSENT`, the database is `ABSENT`,
  deployment is `ABSENT`, application CI is `NOT APPLICABLE`, and UAT is
  `NOT STARTED`.
- Dark mode is `PLANNED` and `NOT IMPLEMENTED`. **LOGO STATUS: NOT APPROVED.**
- Accessibility is **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
  RUNTIME-TESTED**. Runtime accessibility testing belongs to Step 13 and is
  `NOT STARTED`.
- The 26 open product questions from Step 1 remain open. **None was closed by
  inventing a product decision.**

---

## Step 2 closure — 2026-07-20

Evidence-only synchronization after the Step 2 GO tag. **No product decision, no
design decision, no requirement, and no Master Source content was changed by
this entry**; the Master Source remains v1.3.0 with an unchanged checksum
(`92039ba7b54362615c003390b3d5dd80da174869c27d0dc1325c48bde8fe1b1a`).

### Changed

- `docs/STATUS.md` — Step 2 moved from `IN PROGRESS` to
  `GO WITH ACCEPTED DEVIATION`, with the closure SHAs recorded and all four
  deviations stated explicitly.
- `docs/ROADMAP.md` — Step 2 status and tag recorded.
- `.claude/rules/35-current-step-02-status.md` — Step 2 snapshot moved to `GO`
  with the four accepted deviations named.
- `.claude/rules/15-current-product-status.md` — Step 1 and Step 2 statuses
  corrected to `GO`.
- `CLAUDE.md`, `README.md` — status tables updated.

### Added

- `evidence/step-02/post-tag-evidence.md` — the annotated tag object and peeled
  commit, all three GO tags verified unmoved on the remote, the fifteen pre-tag
  checks, the closure chain, the four accepted deviations, and the residual
  items disclosed rather than resolved.

### Step 2 closure facts

| Item | Value |
|---|---|
| Step 2 PR | `#9`, merged at `fc4449e922a0effa86b9770f5a2863a99fe776d6` |
| Pre-tag evidence PR | `#11`, merged at `47c07d360e8802fd78f61d41435cae3f28313137` |
| Tagged commit | `47c07d360e8802fd78f61d41435cae3f28313137` |
| GO tag | `aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go` |
| GO tag object SHA | `d02598b1e3a43db0ebfb6217d7e1d9ddf8484c3a` |
| Ruleset | `19164588` — active, 0 bypass actors, strict, **12** required contexts |
| Governance validators | 53 / 53 PASS |
| Adversarial mutations | 30 / 30 caught |
| Required CI at the tagged SHA | 12 / 12 success |
| Requirements classified | 498 / 498, 0 unclassified |
| Open `CRITICAL` / `HIGH` findings | 0 / 0 |
| Relationship orphans | 0 across 12 classes |

### Not changed, and deliberately so

- **Step 2 created no runtime.** All product features remain `NOT IMPLEMENTED`;
  the backend runtime, Flutter workspace, database and deployment remain
  `ABSENT`; application CI remains `NOT APPLICABLE`; UAT remains `NOT STARTED`.
- Steps 3–14 remain `PLANNED`. **Step 3 has not begun.**
- Accessibility remains **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
  RUNTIME-TESTED**. Runtime accessibility testing belongs to Step 13 and is
  `NOT STARTED`.
- Dark mode remains `PLANNED` / `NOT IMPLEMENTED`. **LOGO STATUS: NOT APPROVED.**
- Dependabot PR #2 remains `OPEN` and unmerged, outside Step 2 scope.
- No GO tag was moved, deleted, recreated, or force-pushed.

---

## Step 1 closure — 2026-07-19

Evidence-only synchronization after the Step 1 GO tag. **No product decision, no
canonical fact, and no Master Source content was changed by this entry**; the
Master Source remains v1.2.0 with an unchanged checksum.

### Changed

- `docs/STATUS.md` — Step 1 moved from `IN PROGRESS` to
  `GO WITH ACCEPTED DEVIATION`, with the closure SHAs recorded and the deviation
  stated explicitly. Step 0 restated as `GO WITH ACCEPTED DEVIATION` for
  consistency with how its own deviation was recorded.
- `docs/ROADMAP.md` — Step 1 status and tag reference.
- `evidence/step-01/security-review.md` — **SR-09 closed** (the owner applied the
  ruleset change; it was then re-read independently through the API rather than
  accepted on report). **SR-11 added and fixed**: two workflows each named their
  job `validate`, so the new gates could not have been required separately —
  adding them to the ruleset would have produced an ambiguous rule rather than
  real enforcement.

### Added

- `evidence/step-01/post-tag-evidence.md` — tag facts, the ten independent
  pre-tag verifications, and the ruleset state at tag time.
- `evidence/step-01/final-closure.md` — the Step 1 closure record.

### Closure record

| Item | Value |
|---|---|
| Step 1 PR | `#6` |
| Step 1 merge SHA | `a518ab56e1bee53751fa99b6741b7ae598283fcf` |
| Governance amendment PR | `#7` |
| **Tagged commit** | `4eadbc73f8bacdc9cd2acfcc62280ac932116089` |
| GO tag | `aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` |
| GO tag object SHA | `faed53c7ed3c5c164e48c861ed065661f6461270` |
| Ruleset | ID `19164588`, active, 0 bypass actors, **9 required checks** |
| Governance gates | 32 / 32 PASS |
| Open `CRITICAL` / `HIGH` findings | 0 / 0 |

**Accepted deviation:** single-maintainer governance, no independent human review
(DEC-0017). Step 1 GO means every technical and governance gate passed **with
that requirement deliberately deviated from and documented** — not that it was
met.

Steps 2–14 remain `PLANNED`. All product features remain `NOT IMPLEMENTED`. No
runtime exists. **No Step 2 work has started.** The 26 documented open questions
remain open and are not retroactive Step 1 blockers.

---

## [1.2.0] — 2026-07-19

Records single-maintainer approval as a standing accepted deviation, and gives the
Step 1 CI checks unambiguous names so branch protection can require them
individually. **No runtime was created. No product decision, pricing figure,
roadmap number, or architectural lock was changed.**

### Added

- **`DEC-0017` — Single-Maintainer Approval Standing Deviation**
  ([record](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md)).
  `MASTER_SOURCE.md` §25.1 item 12 requires approval by someone other than the
  author. Under single-maintainer governance the second person it presupposes
  does not exist, so the item **cannot be satisfied**. It is recorded once as a
  standing deviation rather than waived silently or re-reported as a fresh
  failure at every Step. The compensating controls are named — active ruleset,
  exact-SHA CI, deterministic validators, adversarial validator testing, and
  recorded internal re-verification — together with the honest limitation that
  they are **not equivalent** to independent review: a defect that both the
  maintainer and the validators miss is not caught.
- `MASTER_SOURCE.md` §25.1 item 12 gains the corresponding note. The item is
  **not** deleted; it states the correct requirement and becomes binding the
  moment a second maintainer exists.

### Changed

- CI job names made unambiguous: `Product Requirements / product-requirements`
  and `Domain Model / domain-model`, replacing two further jobs named
  `validate`. Three workflows each publishing a context called `validate` meant
  a required-status-check entry naming `validate` could not distinguish them,
  so the new gates could not have been required separately. The existing
  `Governance / validate` context is unchanged.
- `MASTER_SOURCE.md` §31 — decision-record count moved from sixteen to
  seventeen.

### Not included

- No change to any Definition-of-Done gate other than item 12. The deviation
  excuses exactly one item: it lowers no validator threshold, permits no
  self-declared `GO`, and authorises no merge without green required checks.

---

## [1.1.0] — 2026-07-19

Step 1 — Product Requirement and Domain Model. **Documentation only. No runtime,
no schema, no migration, no API, no UI, and no deployment was created.** Every
product feature remains `NOT IMPLEMENTED`; the backend runtime and Flutter
workspace remain `ABSENT`; application CI remains `NOT APPLICABLE`; UAT remains
`NOT STARTED`.

Classified MINOR under [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §1.2: §34 is new
canonical scope. **No existing product decision, pricing figure, roadmap number,
or architectural lock was changed**, so no new decision record was required.

### Added

- **`MASTER_SOURCE.md` §34 — Step 1 artefacts.** Makes the Step 1 documents
  canonical for their subject matter, and fixes the requirement-identifier
  scheme, the fifteen order statuses, the eleven pickup/delivery statuses, the
  four quality-control statuses, and the twenty bounded contexts.
- **Product documentation** (`docs/product/`) — product requirements, MVP scope,
  fourteen personas, jobs to be done, user and operational journeys, use-case
  catalogue, success metrics, assumptions and open questions, and the
  requirement traceability matrix.
- **Domain model** (`docs/domain/`) — binding glossary, twenty bounded contexts,
  context map, thirty-one aggregates, entity and value-object catalogue, domain
  invariants, domain events, commands and policies, tenant boundaries, data
  ownership, and nine per-domain models. Conceptual only, carrying the marker
  `CONCEPTUAL DOMAIN MODEL — NOT DATABASE SCHEMA`.
- **State machines** (`docs/state-machines/`) — ten canonical lifecycles, each
  with a Mermaid diagram and a written transition table naming actors,
  preconditions, emitted events, and forbidden transitions.
- **Security and quality** (`docs/security/`, `docs/quality/`) — STRIDE threat
  model with fifty threats, abuse cases, five-class data classification, trust
  boundaries, privacy requirements, non-functional requirements, acceptance
  criteria, and the Step 1 Definition of Done.
- **Requirement identifiers** — 498 across twelve canonical series, each defined
  once in its authoritative register and never reused.
- **Rules 16–24** under `.claude/rules/`, binding the Step 1 foundations on all
  later work.
- **Step 1 validators** and `scripts/verify-step-01.sh`, plus
  `scripts/test-step-01-validators.sh`, an adversarial harness that breaks the
  corpus deliberately and asserts the responsible validator fails.
- **CI checks** `Product Requirements / validate`, `Domain Model / validate`, and
  `Security / threat-model`. Existing check names are unchanged.

### Changed

- Step 1 moved from `PLANNED` to `IN PROGRESS` in `MASTER_SOURCE.md` §24,
  `ROADMAP.md`, `STATUS.md`, and `CLAUDE.md`. Steps 2–14 remain `PLANNED`.
- `validate-roadmap.py` and `validate-status.py` now read the status a step
  *declares* rather than substring-matching its prose, and additionally assert
  that no step later than the current one carries a working status.

### Fixed

- Four defects in newly written validators, found by the adversarial harness
  rather than by inspection: threat severity read from prose instead of the
  declared `Severity` field; threat records split on cross-references; the
  requirement-definition pattern not accepting backticked identifiers; and
  acceptance-criteria documents wrongly excluded as definition sources, which
  had erased all sixty-eight `SEC-` definitions. All four would have let a real
  defect through.

### Security

- Initial threat model records fifty threats — 11 `CRITICAL`, 23 `HIGH`, 14
  `MEDIUM`, 1 `LOW`, 1 `INFORMATIONAL`. **Every `CRITICAL` and `HIGH` threat
  carries an explicit mitigation and is referenced by an acceptance criterion**,
  both enforced by validators rather than asserted.
- Public-repository safety scanning added for phone numbers, private keys,
  provider tokens, connection strings, `.env` files, and database dumps.

### Not included — stated explicitly to avoid false impressions

- No application code, framework scaffolding, dependency manifest, schema,
  migration, API endpoint, screen, or deployment.
- **No application tests.** Step 1 defines acceptance criteria; it executes
  none of them, because there is nothing to execute them against. A written
  acceptance criterion is not a passed test.
- No performance measurement. Every non-functional target is recorded as a
  target that has **not** been measured; baselines are set at Step 14.
- No independent human review. Governance is single-maintainer and independent
  human approval is `ABSENT` (DEC-0016).

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
[1.3.0]: https://github.com/makemesick91-code/aish_laundry_app/releases
[1.2.0]: https://github.com/makemesick91-code/aish_laundry_app/releases
[1.1.0]: https://github.com/makemesick91-code/aish_laundry_app/releases
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
