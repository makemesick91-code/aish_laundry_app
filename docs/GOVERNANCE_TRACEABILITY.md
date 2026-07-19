# Governance Traceability Matrix — Aish Laundry App

Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §31.2
Baseline date: 19 July 2026

This matrix makes governance auditable. Every foundation area of the Master Source is traced to the rule
file that operationalises it, the decision record that locks it, and the validator that checks it.

If an area has no rule file, it is unenforced. If it has no decision record, it is unlocked. If it has no
validator, it is unverified. Gaps are stated openly rather than hidden.

---

## 1. Primary matrix

| # | Master Source area | Rule file (`.claude/rules/`) | Decision record | Validator |
| --- | --- | --- | --- | --- |
| 1 | §1 Canonical rules — single source of truth, precedence, honesty, naming | [`00-canonical-source.md`](../.claude/rules/00-canonical-source.md) | [DEC-0001](decisions/DEC-0001-official-product-name.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 2 | §24, §25 Status, evidence, and Definition of Done | [`01-status-and-evidence.md`](../.claude/rules/01-status-and-evidence.md) | [DEC-0013](decisions/DEC-0013-exact-sha-evidence-before-go.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 3 | §4 Multi-tenancy hierarchy and thirteen hard rules | [`02-multi-tenancy.md`](../.claude/rules/02-multi-tenancy.md) | [DEC-0002](decisions/DEC-0002-multi-tenant-architecture.md), [DEC-0003](decisions/DEC-0003-multi-laundry-owner-model.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 4 | §15, §17 Security and privacy | [`03-security-and-privacy.md`](../.claude/rules/03-security-and-privacy.md) | [DEC-0012](decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 5 | §16 Financial integrity | [`04-financial-integrity.md`](../.claude/rules/04-financial-integrity.md) | [DEC-0012](decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 6 | §5, §18 Flutter client foundation and design system | [`05-flutter-client-foundation.md`](../.claude/rules/05-flutter-client-foundation.md) | [DEC-0004](decisions/DEC-0004-flutter-client-and-web-console.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 7 | §6 Backend and API foundation | [`06-backend-api-foundation.md`](../.claude/rules/06-backend-api-foundation.md) | [DEC-0005](decisions/DEC-0005-api-first-modular-monolith-backend.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 8 | §13 Offline-first synchronisation | [`07-offline-sync.md`](../.claude/rules/07-offline-sync.md) | [DEC-0005](decisions/DEC-0005-api-first-modular-monolith-backend.md), [DEC-0012](decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 9 | §14 Notifications and WhatsApp | [`08-notification-and-whatsapp.md`](../.claude/rules/08-notification-and-whatsapp.md) | [DEC-0011](decisions/DEC-0011-transparent-third-party-messaging-costs.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 10 | §10 Pickup and delivery | [`09-pickup-and-delivery.md`](../.claude/rules/09-pickup-and-delivery.md) | [DEC-0007](decisions/DEC-0007-pickup-and-delivery-as-core-product.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 11 | §11 Unclaimed laundry | [`10-unclaimed-laundry.md`](../.claude/rules/10-unclaimed-laundry.md) | [DEC-0008](decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 12 | §26 Git and CI | [`11-git-and-ci.md`](../.claude/rules/11-git-and-ci.md) | [DEC-0013](decisions/DEC-0013-exact-sha-evidence-before-go.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 13 | §27, §33 AI development rules and instructions | [`12-autonomous-execution.md`](../.claude/rules/12-autonomous-execution.md) | [DEC-0013](decisions/DEC-0013-exact-sha-evidence-before-go.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 14 | §28, §25 Testing and Definition of Done | [`13-testing-and-definition-of-done.md`](../.claude/rules/13-testing-and-definition-of-done.md) | [DEC-0012](decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 15 | §21 Pricing and commercial guardrails | [`14-pricing-and-commercial.md`](../.claude/rules/14-pricing-and-commercial.md) | [DEC-0009](decisions/DEC-0009-initial-commercial-pricing.md), [DEC-0010](decisions/DEC-0010-no-lifetime-cloud-subscription.md), [DEC-0011](decisions/DEC-0011-transparent-third-party-messaging-costs.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 16 | §22, §23, §24 Current product status, MVP, and non-goals | [`15-current-product-status.md`](../.claude/rules/15-current-product-status.md) | [DEC-0015](decisions/DEC-0015-mvp-focuses-on-laundry-operations.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 17 | §9 Public tracking portal | [`03-security-and-privacy.md`](../.claude/rules/03-security-and-privacy.md), [`15-current-product-status.md`](../.claude/rules/15-current-product-status.md) | [DEC-0006](decisions/DEC-0006-public-tracking-without-app-installation.md), [DEC-0014](decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |
| 18 | §15.8 Public repository authoring constraints and single-maintainer governance | [`00-canonical-source.md`](../.claude/rules/00-canonical-source.md), [`03-security-and-privacy.md`](../.claude/rules/03-security-and-privacy.md), [`11-git-and-ci.md`](../.claude/rules/11-git-and-ci.md) | [DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md) | [`scripts/verify-step-00.sh`](../scripts/verify-step-00.sh) |

---

## 2. Decision record coverage

Every accepted decision is traced to at least one rule file, so that no decision is locked without being
operationalised.

| Decision | Title | Rule file(s) | Governance policy |
| --- | --- | --- | --- |
| [DEC-0001](decisions/DEC-0001-official-product-name.md) | Official Product Name | `00-canonical-source.md` | — |
| [DEC-0002](decisions/DEC-0002-multi-tenant-architecture.md) | Multi-Tenant Architecture | `02-multi-tenancy.md` | [Tenant isolation](governance/TENANT_ISOLATION_POLICY.md) |
| [DEC-0003](decisions/DEC-0003-multi-laundry-owner-model.md) | Multi-Laundry Owner Model | `02-multi-tenancy.md` | [Tenant isolation](governance/TENANT_ISOLATION_POLICY.md) |
| [DEC-0004](decisions/DEC-0004-flutter-client-and-web-console.md) | Flutter Client and Web Console | `05-flutter-client-foundation.md` | — |
| [DEC-0005](decisions/DEC-0005-api-first-modular-monolith-backend.md) | API-First Modular Monolith Backend | `06-backend-api-foundation.md`, `07-offline-sync.md` | — |
| [DEC-0006](decisions/DEC-0006-public-tracking-without-app-installation.md) | Public Tracking Without App Installation | `03-security-and-privacy.md` | [Tenant isolation](governance/TENANT_ISOLATION_POLICY.md) |
| [DEC-0007](decisions/DEC-0007-pickup-and-delivery-as-core-product.md) | Pickup and Delivery as Core Product | `09-pickup-and-delivery.md` | [Financial integrity](governance/FINANCIAL_INTEGRITY_POLICY.md) |
| [DEC-0008](decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md) | H+1 H+3 H+7 Reminder as Core Product | `10-unclaimed-laundry.md`, `08-notification-and-whatsapp.md` | — |
| [DEC-0009](decisions/DEC-0009-initial-commercial-pricing.md) | Initial Commercial Pricing | `14-pricing-and-commercial.md` | — |
| [DEC-0010](decisions/DEC-0010-no-lifetime-cloud-subscription.md) | No Lifetime Cloud Subscription | `14-pricing-and-commercial.md` | — |
| [DEC-0011](decisions/DEC-0011-transparent-third-party-messaging-costs.md) | Transparent Third-Party Messaging Costs | `08-notification-and-whatsapp.md`, `14-pricing-and-commercial.md` | — |
| [DEC-0012](decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) | Tenant Isolation and Financial Integrity Hard Gate | `02-multi-tenancy.md`, `03-security-and-privacy.md`, `04-financial-integrity.md`, `13-testing-and-definition-of-done.md` | [Tenant isolation](governance/TENANT_ISOLATION_POLICY.md), [Financial integrity](governance/FINANCIAL_INTEGRITY_POLICY.md) |
| [DEC-0013](decisions/DEC-0013-exact-sha-evidence-before-go.md) | Exact-SHA Evidence Before GO | `01-status-and-evidence.md`, `11-git-and-ci.md`, `12-autonomous-execution.md` | [Evidence](governance/EVIDENCE_POLICY.md) |
| [DEC-0014](decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md) | Customer Android Does Not Replace Public Tracking | `05-flutter-client-foundation.md`, `15-current-product-status.md` | — |
| [DEC-0015](decisions/DEC-0015-mvp-focuses-on-laundry-operations.md) | MVP Focuses on Laundry Operations | `15-current-product-status.md` | — |
| [DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md) | Public Repository Visibility Accepted Deviation | `00-canonical-source.md`, `03-security-and-privacy.md`, `11-git-and-ci.md` | [Evidence](governance/EVIDENCE_POLICY.md) |

---

## 3. Governance policy coverage

| Policy | Enforces | Rule files |
| --- | --- | --- |
| [`governance/REQUIRED_FILES.md`](governance/REQUIRED_FILES.md) | Completeness of the Step 0 deliverable set | `00-canonical-source.md`, `01-status-and-evidence.md` |
| [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md) | Exact status vocabulary | `01-status-and-evidence.md`, `15-current-product-status.md` |
| [`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md) | Exact-SHA binding, sanitisation, no secrets | `01-status-and-evidence.md`, `11-git-and-ci.md`, `12-autonomous-execution.md` |
| [`governance/TENANT_ISOLATION_POLICY.md`](governance/TENANT_ISOLATION_POLICY.md) | Hard gate 1 | `02-multi-tenancy.md`, `03-security-and-privacy.md` |
| [`governance/FINANCIAL_INTEGRITY_POLICY.md`](governance/FINANCIAL_INTEGRITY_POLICY.md) | Hard gate 2 | `04-financial-integrity.md`, `07-offline-sync.md` |
| [`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md) | Branching, tagging, rollback, destructive-operation bans | `11-git-and-ci.md` |
| [`AI_EXECUTION_POLICY.md`](AI_EXECUTION_POLICY.md) | Autonomous execution boundaries and honesty | `12-autonomous-execution.md` |
| [`TOOLING_POLICY.md`](TOOLING_POLICY.md) | Skills, Graphify, MCP, limit-saver, credentials | `12-autonomous-execution.md` |
| [`DEFINITION_OF_DONE.md`](DEFINITION_OF_DONE.md) | What Done means | `13-testing-and-definition-of-done.md` |

---

## 4. Validator coverage and known gaps

At the Step 0 baseline, `scripts/verify-step-00.sh` is the **only** validator, because there is no
application to validate. It checks governance structure: required files present, status vocabulary exact,
decision records complete and well-formed, no runtime manifest present, and internal links resolving.

**Known gaps, stated honestly:**

| Gap | Why it exists | Closed in |
| --- | --- | --- |
| No automated tenant isolation test | There is no runtime to attack | Step 3 |
| No automated financial integrity test | There is no payment code | Step 5 |
| No automated offline-sync verification | There is no client | Step 6 |
| No automated tracking-token security test | There is no portal | Step 7 |
| No performance measurement | There is nothing to measure | Step 13 |
| No application CI | There is no application — status NOT APPLICABLE | Step 3 |

These gaps are not deficiencies of Step 0; they are the honest consequence of Step 0 creating no runtime.
They become mandatory Definition-of-Done items in the Steps named above.

---

## 5. Maintaining this matrix

- Adding a rule file, a decision record, or a validator requires a corresponding row here in the same
  pull request.
- A decision record with no rule file, or a rule file with no decision record, is a traceability defect
  and must be resolved before the Step closes.
- Superseded decisions remain in the matrix with a note, so that history stays auditable.
