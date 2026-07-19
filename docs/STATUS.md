# Aish Laundry App — Canonical Status

**This file is machine-validated. The status words below are exact and must not be paraphrased.**

Baseline date: 19 July 2026 · Master Source version: 1.0.0
Status vocabulary: [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)
Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md)

---

## 1. Step status

| Step | Title | Status |
| --- | --- | --- |
| Step 0 | Master Source and Governance | IN PROGRESS |
| Step 1 | Product Requirement and Domain Model | PLANNED |
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

Step 0 remains IN PROGRESS until its pull request is merged into `main`.

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
3. Step 0 must never be recorded here with the release status word; while the foundation pull request is
   open its status is IN PROGRESS, and after validation it may be TESTED or WATCH.
4. A status is never advanced to make a report look better. An honest NO-GO outranks a convenient claim.
5. Any change to this file is reflected in [`CHANGELOG.md`](CHANGELOG.md).
