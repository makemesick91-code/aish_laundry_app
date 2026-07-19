# Aish Laundry App — Canonical Status

**This file is machine-validated. The status words below are exact and must not be paraphrased.**

Baseline date: 19 July 2026 · Master Source version: 1.3.0
Status vocabulary: [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)
Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md)

---

## 1. Step status

| Step | Title | Status |
| --- | --- | --- |
| Step 0 | Master Source and Governance | GO WITH ACCEPTED DEVIATION |
| Step 1 | Product Requirement and Domain Model | GO WITH ACCEPTED DEVIATION |
| Step 2 | Design System and UX Foundation | IN PROGRESS |
| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | PLANNED |
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

## 1b. Step 2 progress

**Step 2 is `IN PROGRESS`.** It is not closed, and **`GO` has not been conferred** — `GO` is the
repository owner's to give and is never self-declared by an agent (Rule 01).

Step 2 delivers the design system and UX foundation as **documentation only**. It creates **no
runtime**: no Flutter workspace, no theme, no screen, no component, no deployment.

| Item | Value |
|---|---|
| Branch | `feature/step-02-design-system-ux-foundation` |
| Master Source | **1.3.0**, checksum regenerated by tool from the final content |
| New decision records | DEC-0018 … DEC-0023 |
| New application rules | 25 … 35 |
| Design tokens | 249 across 16 files — 0 duplicates, 0 unresolved references, 0 circular references |
| Screens specified | 89 (`NOT IMPLEMENTED`) |
| Critical journeys | 33 |
| Components specified | 70, each resolved against 17 states — 1190 cells, 0 blank |
| UX states | 20, each with a recovery path |
| Low-fidelity wireframes | 32 (`NOT IMPLEMENTED`, not final UI) |
| Requirements classified | 498 / 498, 0 unclassified |
| Design and UX threat findings | 36 — 0 `CRITICAL` open, 0 `HIGH` open |
| Canonical verification | `bash scripts/verify-step-02.sh` |

**What Step 2 has NOT produced.** No product feature. All features remain `NOT IMPLEMENTED`. The
backend runtime is `ABSENT`, the Flutter workspace is `ABSENT`, the database is `ABSENT`, deployment is
`ABSENT`, application CI is `NOT APPLICABLE`, and UAT is `NOT STARTED`.

**Accessibility is `DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED`.** Nothing has
been exercised with an assistive technology, because there is nothing to exercise. Runtime
accessibility testing belongs to Step 13 and is `NOT STARTED`.

**Documentation is not implementation.** A design token is not a theme. A component specification is
not a component. A wireframe is not a screen. An accessibility requirement is not a passed audit.

**Step 3 has not begun** and does not begin before Step 2 has `GO`.

---

## 2. System status

| Item | Status |
| --- | --- |
| All product features | NOT IMPLEMENTED |
| Backend runtime | ABSENT |
| Flutter workspace | ABSENT |
| Deployment | ABSENT |
| Application CI | NOT APPLICABLE |
| UAT | NOT STARTED |

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

## 4. Runtime placeholder status

| Path | Status | Runtime |
| --- | --- | --- |
| `apps/customer_android` | NOT IMPLEMENTED | ABSENT |
| `apps/ops_android` | NOT IMPLEMENTED | ABSENT |
| `apps/admin_web` | NOT IMPLEMENTED | ABSENT |
| `backend` | NOT IMPLEMENTED | ABSENT |
| `infrastructure` | NOT IMPLEMENTED | ABSENT |
| `packages/design_system` | NOT IMPLEMENTED | ABSENT |
| `packages/core` | NOT IMPLEMENTED | ABSENT |
| `packages/domain` | NOT IMPLEMENTED | ABSENT |
| `packages/auth` | NOT IMPLEMENTED | ABSENT |
| `packages/networking` | NOT IMPLEMENTED | ABSENT |
| `packages/local_storage` | NOT IMPLEMENTED | ABSENT |
| `packages/offline_sync` | NOT IMPLEMENTED | ABSENT |
| `packages/observability` | NOT IMPLEMENTED | ABSENT |
| `packages/testing` | NOT IMPLEMENTED | ABSENT |

These directories contain a `README.md` only. **An empty folder is never evidence of an implemented
feature.**

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
