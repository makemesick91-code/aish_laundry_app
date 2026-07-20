# Aish Laundry App — Canonical Status

**This file is machine-validated. The status words below are exact and must not be paraphrased.**

Baseline date: 19 July 2026 · Master Source version: 1.4.0
Status vocabulary: [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)
Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md)

---

## 1. Step status

| Step | Title | Status |
| --- | --- | --- |
| Step 0 | Master Source and Governance | GO WITH ACCEPTED DEVIATION |
| Step 1 | Product Requirement and Domain Model | GO WITH ACCEPTED DEVIATION |
| Step 2 | Design System and UX Foundation | GO WITH ACCEPTED DEVIATION |
| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | IN PROGRESS |
| Step 4 | Laundry Master Data | PLANNED |
| Step 5 | POS, Order, and Payment Foundation | PLANNED |
| Step 6 | Production Operations | PLANNED |
| Step 7 | Customer Tracking and WhatsApp | PLANNED |
| Step 8 | Pickup and Delivery Operations | PLANNED |
| Step 9 | Unclaimed Laundry and Cashflow Recovery | PLANNED |
| Step 10 | Finance, Reports, and Owner Portfolio | PLANNED |
| Step 11 | Customer Android Experience | PLANNED |
| Step 12 | Subscription and Platform Administration | PLANNED |
| Step 13 | Security, Performance, Backup, and Recovery | PLANNED |
| Step 14 | Pilot and Commercial Launch | PLANNED |

Step 0 reached **GO** on 19 July 2026.

| Closure item | Value |
|---|---|
| Foundation PR | `#1`, merged |
| Foundation merge SHA | `8494bc8543b9301351da6055337832597f1f2d9f` |
| GO tag | `aish-laundry-step-00-master-source-governance-v1.0.0-go` |
| GO tag peeled commit | `8494bc8543b9301351da6055337832597f1f2d9f` |
| Ruleset | ID `19164588`, active, enforcement proven |
| Governance validators | 11 / 11 PASS |
| Security findings | 4 CRITICAL, 6 HIGH — all closed and re-verified |

**One Definition of Done item is NOT satisfied and is recorded as a deviation,
not as a pass:** the repository is **PUBLIC**, whereas the canonical facts
required PRIVATE. GitHub's free plan cannot apply a ruleset to a private
repository, so private visibility and enforced branch protection were mutually
exclusive. The repository owner was shown the tradeoff and chose PUBLIC so that
enforcement could be applied. See `ASSUMPTIONS.md` — `AMENDMENT-0001`.

Step 0 GO therefore means: every technical and governance gate passed, with the
visibility requirement deliberately amended by the owner and documented. It does
not mean the original PRIVATE requirement was met.

The deviation is locked as a decision record —
[`DEC-0016`](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md) — which records that
the **canonical desired visibility remains PRIVATE**, enumerates the binding public-repository authoring
constraints, and states that governance operates in **single-maintainer** mode with independent human
approval **ABSENT**.

---

## 1a. Step 1 closure

Step 1 reached **GO WITH ACCEPTED DEVIATION** on 19 July 2026, conferred by the repository owner
against exact-SHA evidence (DEC-0013).

| Closure item | Value |
|---|---|
| Step 1 PR | `#6`, merged |
| Step 1 merge SHA | `a518ab56e1bee53751fa99b6741b7ae598283fcf` |
| Governance amendment PR | `#7`, merged (DEC-0017, unambiguous CI contexts) |
| **Tagged commit** | `4eadbc73f8bacdc9cd2acfcc62280ac932116089` |
| GO tag | `aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` |
| GO tag object SHA | `faed53c7ed3c5c164e48c861ed065661f6461270` |
| GO tag type | annotated, immutable |
| Ruleset | ID `19164588`, active, 0 bypass actors, 9 required checks |
| Governance validators | 32 / 32 PASS |
| Required CI checks at the tagged SHA | 9 / 9 success |
| Open `CRITICAL` findings | 0 |
| Open `HIGH` findings | 0 |

**Accepted deviation — single-maintainer governance, no independent human review.**
`MASTER_SOURCE.md` §25.1 item 12 requires a Step-closing pull request to be approved by someone other
than the author. Under single-maintainer governance that person does not exist, so the item **cannot be
satisfied**. It is recorded as a standing accepted deviation in
[`DEC-0017`](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md), which names the
compensating controls and states plainly that they are **not equivalent** to independent review: a
defect that both the maintainer and the validators miss is not caught.

Step 1 GO therefore means every technical and governance gate passed, **with the independent-approval
requirement deliberately deviated from and documented**. It does not mean the requirement was met.

**Step 1 delivered documentation only.** No runtime was created. **Documentation is not
implementation**, and a written acceptance criterion is not a passed test.

**26 documented open questions remain open** for the repository owner
([`product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md)). None was
closed by inventing a product decision, and none is a retroactive Step 1 blocker.

---

## 1b. Step 2 closure

Step 2 reached **GO WITH ACCEPTED DEVIATION** on 20 July 2026, conferred by the
repository owner against exact-SHA evidence (DEC-0013).

| Closure item | Value |
|---|---|
| Step 2 PR | `#9`, merged |
| Step 2 merge SHA | `fc4449e922a0effa86b9770f5a2863a99fe776d6` |
| Pre-tag evidence PR | `#11`, merged (part of the verified pre-tag closure) |
| **Tagged commit** | `47c07d360e8802fd78f61d41435cae3f28313137` |
| GO tag | `aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go` |
| GO tag object SHA | `d02598b1e3a43db0ebfb6217d7e1d9ddf8484c3a` |
| GO tag type | annotated, immutable |
| Ruleset | ID `19164588`, active, 0 bypass actors, **12** required checks |
| Governance validators | 53 / 53 PASS |
| Adversarial mutations caught | 30 / 30 |
| Required CI checks at the tagged SHA | 12 / 12 success |
| Relationship orphans | 0 across 12 checked classes |
| Requirements classified | 498 / 498, 0 unclassified |
| Open `CRITICAL` findings | 0 |
| Open `HIGH` findings | 0 |

**Accepted deviations recorded at Step 2 GO.** Four, none of them silent:

1. **PUBLIC repository visibility** — an accepted deviation from a canonical
   desired PRIVATE ([DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
2. **Single-maintainer governance.**
3. **No independent human review** — `MASTER_SOURCE.md` §25.1 item 12 requires a
   Step-closing pull request to be approved by someone other than the author.
   Under single-maintainer governance that person does not exist, so the item
   **cannot be satisfied**
   ([DEC-0017](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md)).
4. **Design-only accessibility** — the position is `DESIGNED TO MEET WCAG 2.2 AA
   REQUIREMENTS — NOT YET RUNTIME-TESTED`
   ([DEC-0021](decisions/DEC-0021-wcag-22-aa-aligned-accessibility-target.md)).

**The compensating controls are not equivalent to independent peer review or to
runtime accessibility testing.** A defect that both the maintainer and the
validators miss is not caught, and no assistive technology has exercised
anything. Both residual risks are accepted, not eliminated.

Step 2 GO therefore means every technical and governance gate passed, **with
those four deviations deliberately taken and documented**. It does not mean the
deviated requirements were met.

**Step 2 delivered documentation only.** No runtime was created. **Documentation
is not implementation:** a design token is not a theme, a component
specification is not a component, a wireframe is not a screen, and an
accessibility requirement is not a passed audit.

Enforcement was proved rather than assumed. Temporary pull request `#10` at
`0694ca726ac9f549f2638f0b09266bd5790d7ad4` made exactly one required context
(`ux-foundation`) fail; `mergeStateStatus` was `BLOCKED`; the pull request was
closed unmerged. The temporary branch `test/step-02-negative-enforcement-proof`
remains on the remote — branch deletion is blocked by the destructive-operations
guard and is owner territory. It is **not** a Step 2 blocker.

**26 documented open questions remain open** for the repository owner
([`product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md)),
plus the Step 2 design open questions in
[`ux/UX_OPEN_QUESTIONS.md`](ux/UX_OPEN_QUESTIONS.md). None was closed by
inventing a product decision.

**Step 3 is IN PROGRESS.** Runtime exists and is authorised by
[DEC-0024](decisions/DEC-0024-step-3-runtime-introduction-and-runtime-scope-guard-transition.md).
Runtime existing is not runtime working: see §2 for exactly what is verified and
what is not.

---

## 2. System status

| Item | Status |
| --- | --- |
| All product business features | NOT IMPLEMENTED |
| Backend runtime | PRESENT — STEP 3 FOUNDATION ONLY |
| PostgreSQL runtime foundation | PRESENT |
| Redis runtime foundation | PRESENT |
| Flutter workspace | PRESENT |
| Customer Android | FOUNDATION IMPLEMENTED AND DEBUG BUILD VERIFIED |
| Ops Android | FOUNDATION IMPLEMENTED AND DEBUG BUILD VERIFIED |
| Admin Web | FOUNDATION IMPLEMENTED AND BUILD VERIFIED |
| Deployment | ABSENT |
| Application CI | NOT APPLICABLE |
| UAT | NOT STARTED |

**Runtime existing is not runtime working.** The backend boots, migrates against
authoritative PostgreSQL 18.4, and passes 202 tests; the Dart workspace analyses
clean and passes 187 tests. No Android or Web artefact has been built, so no build
result is claimed.

<!-- CANONICAL_STEP_STATE_BEGIN -->
<!--
Machine-readable canonical step state. This block is the DETERMINISTIC source for
tooling; the tables above are the human-readable form. scripts/validate-status.py
fails if the two disagree, so neither can drift silently.

Exactly one block. Duplicate blocks, duplicate keys, missing markers, or an
unknown status value are all failures, and a parse error fails CLOSED.
Transitions remain governed by the ordinary canonical process — editing this
block does not by itself advance a step.
-->
STEP_00_STATUS=GO
STEP_01_STATUS=GO
STEP_02_STATUS=GO
STEP_03_STATUS=IN_PROGRESS
STEP_04_STATUS=PLANNED
STEP_05_STATUS=PLANNED
STEP_06_STATUS=PLANNED
STEP_07_STATUS=PLANNED
STEP_08_STATUS=PLANNED
STEP_09_STATUS=PLANNED
STEP_10_STATUS=PLANNED
STEP_11_STATUS=PLANNED
STEP_12_STATUS=PLANNED
STEP_13_STATUS=PLANNED
STEP_14_STATUS=PLANNED
<!-- CANONICAL_STEP_STATE_END -->

---

## 3. Feature status

Every product feature is **NOT IMPLEMENTED**.

| Feature | Status |
| --- | --- |
| Authentication and phone + OTP login | NOT IMPLEMENTED |
| Tenancy, brands, outlets, memberships, tenant switcher | NOT IMPLEMENTED |
| RBAC and server-side authorisation | NOT IMPLEMENTED |
| Customer management | NOT IMPLEMENTED |
| Service and price list master data | NOT IMPLEMENTED |
| POS and order intake | NOT IMPLEMENTED |
| Payment, refund, and void | NOT IMPLEMENTED |
| Production operations and quality control | NOT IMPLEMENTED |
| Public tracking portal | NOT IMPLEMENTED |
| WhatsApp and notifications | NOT IMPLEMENTED |
| Pickup and delivery | NOT IMPLEMENTED |
| Unclaimed laundry H+1/H+3/H+7/H+14 | NOT IMPLEMENTED |
| Finance, reporting, and owner portfolio | NOT IMPLEMENTED |
| Customer Android experience | NOT IMPLEMENTED |
| Subscription and platform administration | NOT IMPLEMENTED |
| Offline-first synchronisation | NOT IMPLEMENTED |
| Observability | NOT IMPLEMENTED |

---

## 4. Runtime status by path

Step 3 introduced runtime into the approved roots (DEC-0024). These paths are no
longer placeholders. **Runtime present is not runtime working** — the third column
records what has actually been executed, not what exists.

| Path | Runtime | Verified |
| --- | --- | --- |
| `backend` | PRESENT | Laravel 13.20.0 boots; migrate fresh/rollback/re-apply on PostgreSQL 18.4; 202 tests, 1292 assertions |
| `apps/customer_android` | PRESENT | analyse clean, 20 widget tests; **debug APK built, exit 0** |
| `apps/ops_android` | PRESENT | analyse clean, 28 widget tests; **debug APK built, exit 0** |
| `apps/admin_web` | PRESENT | analyse clean, 20 widget tests; **release web build, exit 0** |
| `packages/design_system` | PRESENT | deterministic token generation, drift test |
| `packages/core` | PRESENT | pure Dart, unit tests |
| `packages/domain` | PRESENT | pure Dart, unit tests |
| `packages/auth` | PRESENT | auth-state tests |
| `packages/networking` | PRESENT | error-mapping tests |
| `packages/local_storage` | PRESENT | secure-storage abstraction tests |
| `packages/offline_sync` | PRESENT — interfaces only | no business queue exists |
| `packages/observability` | PRESENT | redaction tests |
| `packages/testing` | PRESENT | fakes and helpers |
| `infrastructure` | PRESENT — development only | PostgreSQL 18.4 and Redis 8.2.7 connectivity proven; **no deployment artefact** |

All three artefacts have been compiled and their evidence recorded in
`evidence/step-03/`. **No deployment exists**, and no artefact is committed.

---

## 5. Testing and quality status

| Item | Status |
| --- | --- |
| Unit tests | NOT APPLICABLE |
| Integration tests | NOT APPLICABLE |
| Tenant isolation test suite | NOT APPLICABLE |
| Financial integrity test suite | NOT APPLICABLE |
| End-to-end tests | NOT APPLICABLE |
| Application CI | NOT APPLICABLE |
| UAT | NOT STARTED |

There is no application code, therefore there is nothing to build or test. Application CI becomes
applicable at Step 3. Governance validation is performed by `scripts/verify-step-01.sh`, which runs the
Step 0 gates still in force plus the Step 1 gates (32 gates in total).

**A written acceptance criterion is not a passed test.** Step 1 defined acceptance criteria; it executed
none of them, because there is nothing to execute them against.

---

## 6. Environment status

| Environment | Status |
| --- | --- |
| Local development runtime | ABSENT |
| Staging | ABSENT |
| Production | ABSENT |
| Database | ABSENT |
| Redis | ABSENT |
| Object storage | ABSENT |
| Deployment pipeline | ABSENT |

---

## 7. Rules for updating this file

1. Only the canonical vocabulary in [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md) may be used.
2. A status may only be advanced with evidence bound to an exact commit SHA
   ([`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)).
3. **`GO` is conferred by the repository owner and is never self-declared by an agent.** While a Step's
   pull request is open, its status is IN PROGRESS, and after validation it may be TESTED or WATCH.
   `GO` is written here only after the owner confers it against exact-SHA evidence and the Step has
   merged — as happened for Step 0 on 19 July 2026. An agent that writes `GO` for a Step whose pull
   request is still open has committed a status-inflation violation and the wording is reverted.
4. A status is never advanced to make a report look better. An honest NO-GO outranks a convenient claim.
5. Any change to this file is reflected in [`CHANGELOG.md`](CHANGELOG.md).
