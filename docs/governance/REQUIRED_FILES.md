# Required Files — Step 0

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §25.2
Baseline date: 19 July 2026

This is the **authoritative inventory** of every file Step 0 must produce. `scripts/verify-step-00.sh`
validates the tree against this list. A missing required file is a **NO-GO**.

Paths are relative to the repository root.

---

## 1. Root governance files

| Path | Purpose | Owner |
| --- | --- | --- |
| `README.md` | Project overview, canonical status table, monorepo layout, no-runtime statement, validator instructions | Governance docs |
| `CONTRIBUTING.md` | Branch naming, conventional commits, PR requirements, validator requirement, no-secrets rule, Step scope discipline | Governance docs |
| `SECURITY.md` | Vulnerability reporting, no-secrets policy, security hard gates, supported-scope statement | Governance docs |
| `CLAUDE.md` | AI agent operating contract for this repository | Configuration |

## 2. Canonical documents (`docs/`)

| Path | Purpose |
| --- | --- |
| `docs/MASTER_SOURCE.md` | The single canonical source of truth, version 1.0.0, baseline 19 July 2026, thirty-three sections |
| `docs/CHANGELOG.md` | Keep a Changelog record; 1.0.0 entry dated 19 July 2026 |
| `docs/STATUS.md` | Machine-validated canonical status |
| `docs/ROADMAP.md` | Step 0 to Step 14 with status and scope summary |
| `docs/DEFINITION_OF_DONE.md` | General DoD plus the Step 0 checklist |
| `docs/ASSUMPTIONS.md` | ASSUMPTION-0001 and AMENDMENT-0001 |
| `docs/GOVERNANCE_TRACEABILITY.md` | Area → rule file → decision record → validator matrix |
| `docs/GIT_AND_RELEASE_POLICY.md` | Branch model, PR-only, exact-SHA CI, tags, rollback, forbidden operations |
| `docs/AI_EXECUTION_POLICY.md` | Autonomous execution, no false claims, evidence, NO-GO conditions |
| `docs/TOOLING_POLICY.md` | Skills, Graphify, MCP, limit-saver protocol, credential rules |

## 3. Governance policies (`docs/governance/`)

| Path | Purpose |
| --- | --- |
| `docs/governance/REQUIRED_FILES.md` | This file |
| `docs/governance/STATUS_MODEL.md` | The exclusive status vocabulary |
| `docs/governance/EVIDENCE_POLICY.md` | Evidence pack rules, exact-SHA discipline, sanitisation |
| `docs/governance/TENANT_ISOLATION_POLICY.md` | Hard gate 1 |
| `docs/governance/FINANCIAL_INTEGRITY_POLICY.md` | Hard gate 2 |

## 4. Decision records (`docs/decisions/`)

All fifteen are required. Each carries status **ACCEPTED** and date **19 July 2026**.

| Path |
| --- |
| `docs/decisions/DEC-0001-official-product-name.md` |
| `docs/decisions/DEC-0002-multi-tenant-architecture.md` |
| `docs/decisions/DEC-0003-multi-laundry-owner-model.md` |
| `docs/decisions/DEC-0004-flutter-client-and-web-console.md` |
| `docs/decisions/DEC-0005-api-first-modular-monolith-backend.md` |
| `docs/decisions/DEC-0006-public-tracking-without-app-installation.md` |
| `docs/decisions/DEC-0007-pickup-and-delivery-as-core-product.md` |
| `docs/decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md` |
| `docs/decisions/DEC-0009-initial-commercial-pricing.md` |
| `docs/decisions/DEC-0010-no-lifetime-cloud-subscription.md` |
| `docs/decisions/DEC-0011-transparent-third-party-messaging-costs.md` |
| `docs/decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md` |
| `docs/decisions/DEC-0013-exact-sha-evidence-before-go.md` |
| `docs/decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md` |
| `docs/decisions/DEC-0015-mvp-focuses-on-laundry-operations.md` |

Required headings in every decision record: **ID, Title, Status, Date, Context, Decision, Consequences,
Positive consequences, Negative consequences / trade-offs, Verification, Supersession policy, Related
Master Source sections.**

## 5. Rule files (`.claude/rules/`)

All sixteen are required.

| Path |
| --- |
| `.claude/rules/00-canonical-source.md` |
| `.claude/rules/01-status-and-evidence.md` |
| `.claude/rules/02-multi-tenancy.md` |
| `.claude/rules/03-security-and-privacy.md` |
| `.claude/rules/04-financial-integrity.md` |
| `.claude/rules/05-flutter-client-foundation.md` |
| `.claude/rules/06-backend-api-foundation.md` |
| `.claude/rules/07-offline-sync.md` |
| `.claude/rules/08-notification-and-whatsapp.md` |
| `.claude/rules/09-pickup-and-delivery.md` |
| `.claude/rules/10-unclaimed-laundry.md` |
| `.claude/rules/11-git-and-ci.md` |
| `.claude/rules/12-autonomous-execution.md` |
| `.claude/rules/13-testing-and-definition-of-done.md` |
| `.claude/rules/14-pricing-and-commercial.md` |
| `.claude/rules/15-current-product-status.md` |

## 6. Scripts

| Path | Purpose |
| --- | --- |
| `scripts/verify-step-00.sh` | The Step 0 governance validator. Exit code 0 means the structure is valid. |

## 7. Runtime placeholder READMEs

Each of these directories contains a `README.md` stating `Status: NOT IMPLEMENTED`, `Runtime: ABSENT`,
`Creation deferred to the relevant canonical step.`, and the canonical Step that will create it.

| Path |
| --- |
| `apps/customer_android/README.md` |
| `apps/ops_android/README.md` |
| `apps/admin_web/README.md` |
| `backend/README.md` |
| `infrastructure/README.md` |
| `packages/design_system/README.md` |
| `packages/core/README.md` |
| `packages/domain/README.md` |
| `packages/auth/README.md` |
| `packages/networking/README.md` |
| `packages/local_storage/README.md` |
| `packages/offline_sync/README.md` |
| `packages/observability/README.md` |
| `packages/testing/README.md` |

## 8. Evidence

| Path | Purpose |
| --- | --- |
| `evidence/step-00/` | The Step 0 evidence pack, bound to the exact commit SHA under review, sanitised per [`EVIDENCE_POLICY.md`](EVIDENCE_POLICY.md) |

## 9. CI and repository configuration

| Path | Purpose |
| --- | --- |
| `.github/workflows/` | Governance CI: documentation and validator checks only. No application CI — status NOT APPLICABLE. |
| `.github/ISSUE_TEMPLATE/` | Issue templates enforcing honest status reporting |

---

## 10. Files that must NOT exist in Step 0

Presence of any of these is an automatic **NO-GO**
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §24.1):

- `pubspec.yaml` anywhere in the tree
- `composer.json` anywhere in the tree
- `artisan`
- any Dart source file
- any PHP application source file
- any database schema, migration, or seed file
- any Dockerfile or compose file constituting an application runtime
- any deployment manifest
- any `.env` containing real values
- any file containing a secret, token, credential, or private key

---

## 11. Maintaining this inventory

1. This file must match the real tree. A file added to Step 0's scope is added here in the same pull
   request.
2. `scripts/verify-step-00.sh` is the mechanical check; this document is the human-readable contract.
   If they disagree, the disagreement is a defect and is resolved before the Step closes.
3. Removing a required file requires a decision record.
4. Later Steps define their own required-file inventories; this document covers Step 0 only.
