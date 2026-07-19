# Aish Laundry App — Canonical Status

**This file is machine-validated. The status words below are exact and must not be paraphrased.**

Baseline date: 19 July 2026 · Master Source version: 1.2.0
Status vocabulary: [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)
Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md)

---

## 1. Step status

| Step | Title | Status |
| --- | --- | --- |
| Step 0 | Master Source and Governance | GO |
| Step 1 | Product Requirement and Domain Model | IN PROGRESS |
| Step 2 | Design System and UX Foundation | PLANNED |
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
applicable at Step 3. Governance validation is performed by `scripts/verify-step-00.sh`.

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
