# Aish Laundry App

**Aish Laundry App** is a multi-tenant laundry operations, customer tracking, and pickup-and-delivery
SaaS product owned by **Aish Tech Solution**. It targets laundry UMKM and laundry chains in Indonesia.

- Primary language: Bahasa Indonesia
- Currency: Rupiah
- Timezone: Asia/Jakarta
- Master Source version: **1.0.0**
- Baseline date: **19 July 2026**

> **This repository currently contains governance documentation only.**
> No application runtime exists yet. See the status table below.

---

## 1. Canonical status

| Item | Status |
| --- | --- |
| Step 0 — Master Source and Governance | GO (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 1 — Product Requirement and Domain Model | GO (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 2 — Design System and UX Foundation | IN PROGRESS |
| Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC | PLANNED |
| Step 4 — Laundry Master Data | PLANNED |
| Step 5 — POS, Order, and Payment Foundation | PLANNED |
| Step 6 — Production Operations | PLANNED |
| Step 7 — Customer Tracking and WhatsApp | PLANNED |
| Step 8 — Pickup and Delivery Operations | PLANNED |
| Step 9 — Unclaimed Laundry and Cashflow Recovery | PLANNED |
| Step 10 — Finance, Reports, and Owner Portfolio | PLANNED |
| Step 11 — Customer Android Experience | PLANNED |
| Step 12 — Subscription and Platform Administration | PLANNED |
| Step 13 — Security, Performance, Backup, and Recovery | PLANNED |
| Step 14 — Pilot and Commercial Launch | PLANNED |
| All product features | NOT IMPLEMENTED |
| Backend runtime | ABSENT |
| Flutter workspace | ABSENT |
| Deployment | ABSENT |
| Application CI | NOT APPLICABLE |
| UAT | NOT STARTED |

The machine-validated copy of this table lives in [`docs/STATUS.md`](docs/STATUS.md).
Status vocabulary is defined in [`docs/governance/STATUS_MODEL.md`](docs/governance/STATUS_MODEL.md).

---

## 2. No-runtime statement

Step 0 deliberately creates **no runtime of any kind**. Specifically, this repository contains:

- no `pubspec.yaml`, no Dart source, no Flutter workspace;
- no `composer.json`, no `artisan`, no Laravel application;
- no database schema, no migrations, no seeders;
- no authentication, no tenant implementation, no REST API;
- no Android UI, no Flutter Web UI;
- no Docker application runtime and no deployment of any environment.

Directories such as `backend/`, `apps/*`, `packages/*`, and `infrastructure/` exist as **placeholders only**
and contain a `README.md` that states `Status: NOT IMPLEMENTED` and `Runtime: ABSENT`.
**An empty folder is never evidence of an implemented feature.**

---

## 3. Monorepo layout

```
aish_laundry/
├── README.md                     # this file
├── CONTRIBUTING.md               # contribution and PR rules
├── SECURITY.md                   # vulnerability reporting and security policy
├── CLAUDE.md                     # AI agent operating instructions
├── .claude/
│   ├── rules/                    # 00..15 canonical rule files
│   ├── skills/                   # repository skills
│   └── hooks/                    # local automation hooks
├── .github/
│   ├── workflows/                # governance CI (docs validation only)
│   └── ISSUE_TEMPLATE/
├── apps/
│   ├── customer_android/         # Aish Laundry Customer Android (Flutter)   — ABSENT
│   ├── ops_android/              # Aish Laundry Ops Android (Flutter)        — ABSENT
│   └── admin_web/                # Aish Laundry Console Web (Flutter Web)    — ABSENT
├── backend/                      # Laravel modular monolith                  — ABSENT
├── infrastructure/               # environment and deployment assets         — ABSENT
├── packages/
│   ├── design_system/  core/  domain/  auth/  networking/
│   ├── local_storage/  offline_sync/  observability/  testing/              — ABSENT
├── docs/
│   ├── MASTER_SOURCE.md          # the canonical single source of truth
│   ├── CHANGELOG.md
│   ├── STATUS.md
│   ├── ROADMAP.md
│   ├── DEFINITION_OF_DONE.md
│   ├── ASSUMPTIONS.md
│   ├── GOVERNANCE_TRACEABILITY.md
│   ├── GIT_AND_RELEASE_POLICY.md
│   ├── AI_EXECUTION_POLICY.md
│   ├── TOOLING_POLICY.md
│   ├── governance/               # required files, status model, policies
│   └── decisions/                # DEC-0001 .. DEC-0015
├── evidence/
│   └── step-00/                  # exact-SHA evidence pack for Step 0
└── scripts/
    └── verify-step-00.sh         # Step 0 validator
```

---

## 4. The Master Source

[`docs/MASTER_SOURCE.md`](docs/MASTER_SOURCE.md) is the **single canonical source of truth** for
Aish Laundry App. Every other document, rule file, decision record, and future implementation must
be consistent with it. Where any document disagrees with the Master Source, the Master Source wins.

Supporting canonical documents:

- [`docs/ROADMAP.md`](docs/ROADMAP.md) — Step 0 to Step 14
- [`docs/STATUS.md`](docs/STATUS.md) — machine-validated current status
- [`docs/DEFINITION_OF_DONE.md`](docs/DEFINITION_OF_DONE.md) — general and Step 0 DoD
- [`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md) — assumptions and amendments
- [`docs/GOVERNANCE_TRACEABILITY.md`](docs/GOVERNANCE_TRACEABILITY.md) — area → rule → decision → validator
- [`docs/GIT_AND_RELEASE_POLICY.md`](docs/GIT_AND_RELEASE_POLICY.md)
- [`docs/AI_EXECUTION_POLICY.md`](docs/AI_EXECUTION_POLICY.md)
- [`docs/TOOLING_POLICY.md`](docs/TOOLING_POLICY.md)
- [`docs/CHANGELOG.md`](docs/CHANGELOG.md)

---

## 5. How to verify Step 0

Step 0 has no application build. The only executable check is the governance validator:

```bash
bash scripts/verify-step-00.sh
```

The validator checks that every required Step 0 file exists, that the canonical status vocabulary
in [`docs/STATUS.md`](docs/STATUS.md) is exact, that all 15 decision records are present and
well-formed, and that internal markdown links resolve. A non-zero exit code means Step 0 is **NO-GO**.

Required-file inventory: [`docs/governance/REQUIRED_FILES.md`](docs/governance/REQUIRED_FILES.md).
Evidence rules: [`docs/governance/EVIDENCE_POLICY.md`](docs/governance/EVIDENCE_POLICY.md).

---

## 6. Repository visibility notice

This repository is **PUBLIC** by deliberate decision of the repository owner, so that GitHub branch
protection could be enforced on the free plan. Commercial pricing and product decisions recorded here
are therefore publicly visible. The full record is in
[`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md) (AMENDMENT-0001).

**No secrets, tokens, credentials, or customer data may ever be committed to this repository.**
See [`SECURITY.md`](SECURITY.md).

---

## 7. Contributing

Read [`CONTRIBUTING.md`](CONTRIBUTING.md) before opening a pull request. In short: branch from `main`,
use conventional commits, keep changes inside the scope of the current Step, run the validator, and
never claim work that does not exist.
