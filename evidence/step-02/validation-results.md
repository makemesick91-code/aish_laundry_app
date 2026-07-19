# Step 2 — Validation Results

| Field | Value |
|---|---|
| Exact commit SHA | `1af62cb60d2559d2a235ccaa8da91026c9381233` |
| Branch | `feature/step-02-design-system-ux-foundation` |
| Timestamp | 2026-07-19 21:50:34 WIB |
| Environment | Linux 7.0.0-27-generic, Python 3.14.4, bash 5.3.9(1)-release |
| Sanitisation | Performed. No secret, token, credential, OTP or personal datum appears in this capture. Output is reproduced in full and unedited. |

> **Evidence produced at this SHA does not carry over to any other SHA.** If the
> tree changes, this capture is void and verification must be re-run (DEC-0013).
>
> These are **governance validators**, not application tests. There is no
> application, so there are no unit, widget, integration or end-to-end tests,
> and none may be claimed. Application CI is `NOT APPLICABLE`.
>
> **Method note.** Both runs were captured to a path outside the repository and
> assembled afterwards. An earlier attempt wrote the capture inside the tree
> while the validators were still scanning it, and the partially written file —
> which at that instant had an unclosed code fence — caused the `mermaid-blocks`
> gate to fail. That failure was an artefact of the capture method, not a defect
> in the repository. It is recorded here rather than quietly discarded.


## Command

```bash
bash scripts/verify-step-02.sh
```

## Captured output

```text
########################################################################
# AISH LAUNDRY APP — STEP 2 VERIFICATION
# repo root : /home/fikri/Projects/aish_laundry
# python    : Python 3.14.4
# bash      : 5.3.9(1)-release
# git sha   : 1af62cb60d2559d2a235ccaa8da91026c9381233
# branch    : feature/step-02-design-system-ux-foundation
# started   : 2026-07-19T14:49:59Z
########################################################################

========================================================================
VALIDATOR: required-files
========================================================================
PASS  required file exists: README.md
PASS  required file exists: CLAUDE.md
PASS  required file exists: CONTRIBUTING.md
PASS  required file exists: SECURITY.md
PASS  required file exists: .editorconfig
PASS  required file exists: .gitignore
PASS  required file exists: docs/MASTER_SOURCE.md
PASS  required file exists: docs/MASTER_SOURCE.sha256
PASS  required file exists: docs/CHANGELOG.md
PASS  required file exists: docs/DEFINITION_OF_DONE.md
PASS  required file exists: docs/ROADMAP.md
PASS  required file exists: docs/STATUS.md
PASS  required file exists: docs/ASSUMPTIONS.md
PASS  required file exists: docs/GOVERNANCE_TRACEABILITY.md
PASS  required file exists: docs/GIT_AND_RELEASE_POLICY.md
PASS  required file exists: docs/AI_EXECUTION_POLICY.md
PASS  required file exists: docs/TOOLING_POLICY.md
PASS  required file exists: docs/governance/REQUIRED_FILES.md
PASS  required file exists: docs/governance/STATUS_MODEL.md
PASS  required file exists: docs/governance/EVIDENCE_POLICY.md
PASS  required file exists: docs/governance/TENANT_ISOLATION_POLICY.md
PASS  required file exists: docs/governance/FINANCIAL_INTEGRITY_POLICY.md
PASS  required file exists: .claude/hooks/guard-destructive-operations.sh
PASS  required file exists: .claude/skills/aish-laundry-governance/SKILL.md
PASS  required file exists: .github/pull_request_template.md
PASS  required file exists: .github/CODEOWNERS
PASS  required file exists: .github/dependabot.yml
PASS  required file exists: .github/ISSUE_TEMPLATE/bug.yml
PASS  required file exists: .github/ISSUE_TEMPLATE/feature.yml
PASS  required file exists: .github/ISSUE_TEMPLATE/governance.yml
PASS  required file exists: .github/ISSUE_TEMPLATE/config.yml
PASS  required directory exists: .claude/rules/
PASS  rule files present: 36 distinct numbers (expected 36)
PASS  rule 00 present exactly once: .claude/rules/00-canonical-source.md
PASS  rule 01 present exactly once: .claude/rules/01-status-and-evidence.md
PASS  rule 02 present exactly once: .claude/rules/02-multi-tenancy.md
PASS  rule 03 present exactly once: .claude/rules/03-security-and-privacy.md
PASS  rule 04 present exactly once: .claude/rules/04-financial-integrity.md
PASS  rule 05 present exactly once: .claude/rules/05-flutter-client-foundation.md
PASS  rule 06 present exactly once: .claude/rules/06-backend-api-foundation.md
PASS  rule 07 present exactly once: .claude/rules/07-offline-sync.md
PASS  rule 08 present exactly once: .claude/rules/08-notification-and-whatsapp.md
PASS  rule 09 present exactly once: .claude/rules/09-pickup-and-delivery.md
PASS  rule 10 present exactly once: .claude/rules/10-unclaimed-laundry.md
PASS  rule 11 present exactly once: .claude/rules/11-git-and-ci.md
PASS  rule 12 present exactly once: .claude/rules/12-autonomous-execution.md
PASS  rule 13 present exactly once: .claude/rules/13-testing-and-definition-of-done.md
PASS  rule 14 present exactly once: .claude/rules/14-pricing-and-commercial.md
PASS  rule 15 present exactly once: .claude/rules/15-current-product-status.md
PASS  rule 16 present exactly once: .claude/rules/16-product-requirements.md
PASS  rule 17 present exactly once: .claude/rules/17-domain-model-and-bounded-contexts.md
PASS  rule 18 present exactly once: .claude/rules/18-domain-invariants.md
PASS  rule 19 present exactly once: .claude/rules/19-state-machines.md
PASS  rule 20 present exactly once: .claude/rules/20-domain-events-and-idempotency.md
PASS  rule 21 present exactly once: .claude/rules/21-threat-model-and-data-classification.md
PASS  rule 22 present exactly once: .claude/rules/22-acceptance-criteria-and-traceability.md
PASS  rule 23 present exactly once: .claude/rules/23-public-repository-safety.md
PASS  rule 24 present exactly once: .claude/rules/24-current-step-01-status.md
PASS  rule 25 present exactly once: .claude/rules/25-design-system-foundation.md
PASS  rule 26 present exactly once: .claude/rules/26-design-token-governance.md
PASS  rule 27 present exactly once: .claude/rules/27-accessibility-foundation.md
PASS  rule 28 present exactly once: .claude/rules/28-platform-adaptive-navigation.md
PASS  rule 29 present exactly once: .claude/rules/29-ux-state-model.md
PASS  rule 30 present exactly once: .claude/rules/30-content-design-and-localization.md
PASS  rule 31 present exactly once: .claude/rules/31-responsive-and-device-foundation.md
PASS  rule 32 present exactly once: .claude/rules/32-security-and-privacy-ux.md
PASS  rule 33 present exactly once: .claude/rules/33-design-traceability.md
PASS  rule 34 present exactly once: .claude/rules/34-component-and-screen-governance.md
PASS  rule 35 present exactly once: .claude/rules/35-current-step-02-status.md
PASS  required directory exists: docs/decisions/
PASS  decision DEC-0001 present exactly once: DEC-0001-official-product-name.md
PASS  decision DEC-0002 present exactly once: DEC-0002-multi-tenant-architecture.md
PASS  decision DEC-0003 present exactly once: DEC-0003-multi-laundry-owner-model.md
PASS  decision DEC-0004 present exactly once: DEC-0004-flutter-client-and-web-console.md
PASS  decision DEC-0005 present exactly once: DEC-0005-api-first-modular-monolith-backend.md
PASS  decision DEC-0006 present exactly once: DEC-0006-public-tracking-without-app-installation.md
PASS  decision DEC-0007 present exactly once: DEC-0007-pickup-and-delivery-as-core-product.md
PASS  decision DEC-0008 present exactly once: DEC-0008-h1-h3-h7-reminder-as-core-product.md
PASS  decision DEC-0009 present exactly once: DEC-0009-initial-commercial-pricing.md
PASS  decision DEC-0010 present exactly once: DEC-0010-no-lifetime-cloud-subscription.md
PASS  decision DEC-0011 present exactly once: DEC-0011-transparent-third-party-messaging-costs.md
PASS  decision DEC-0012 present exactly once: DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md
PASS  decision DEC-0013 present exactly once: DEC-0013-exact-sha-evidence-before-go.md
PASS  decision DEC-0014 present exactly once: DEC-0014-customer-android-does-not-replace-public-tracking.md
PASS  decision DEC-0015 present exactly once: DEC-0015-mvp-focuses-on-laundry-operations.md
PASS  decision DEC-0016 present exactly once: DEC-0016-public-repository-visibility-accepted-deviation.md
PASS  decision DEC-0017 present exactly once: DEC-0017-single-maintainer-approval-standing-deviation.md
PASS  decision DEC-0018 present exactly once: DEC-0018-two-layer-design-token-architecture.md
PASS  decision DEC-0019 present exactly once: DEC-0019-light-theme-canonical-mvp-dark-mode-deferred.md
PASS  decision DEC-0020 present exactly once: DEC-0020-system-first-typography-no-font-binary.md
PASS  decision DEC-0021 present exactly once: DEC-0021-wcag-22-aa-aligned-accessibility-target.md
PASS  decision DEC-0022 present exactly once: DEC-0022-canonical-ux-state-taxonomy-and-role-adaptive-navigation.md
PASS  decision DEC-0023 present exactly once: DEC-0023-low-fidelity-wireframes-and-no-final-logo-fabrication.md
------------------------------------------------------------------------
SUMMARY [required-files]: 93/93 checks passed, 0 failed
RESULT: PASS (required-files)

========================================================================
VALIDATOR: master-source
========================================================================
PASS  docs/MASTER_SOURCE.md exists
PASS  header declares Master Source version 1.3.0 (found 1.3.0)
PASS  footer declares Master Source version 1.3.0 (found 1.3.0)
PASS  header and footer declare the same Master Source version
PASS  declares baseline date 19 July 2026 (matched /19\s+July\s+2026/)
PASS  contains canonical product name "Aish Laundry App"
PASS  no competing canonical product name
PASS  covers topic: multi-tenancy
PASS  covers topic: platforms
PASS  covers topic: architecture
PASS  covers topic: roles
PASS  covers topic: tracking
PASS  covers topic: pickup and delivery
PASS  covers topic: unclaimed laundry
PASS  covers topic: offline-first
PASS  covers topic: notifications
PASS  covers topic: security
PASS  covers topic: financial integrity
PASS  covers topic: privacy
PASS  covers topic: UX
PASS  covers topic: performance
PASS  covers topic: observability
PASS  covers topic: pricing
PASS  covers topic: MVP
PASS  covers topic: non-goals
PASS  covers topic: roadmap
PASS  covers topic: definition of done
PASS  covers topic: git policy
PASS  covers topic: testing
PASS  covers topic: metrics
PASS  covers topic: positioning
PASS  covers topic: changelog
PASS  is substantial: 1942 lines (minimum 400)
PASS  docs/MASTER_SOURCE.sha256 exists
      recorded path: MASTER_SOURCE.md
PASS  digest line refers to MASTER_SOURCE.md (got: MASTER_SOURCE.md)
PASS  SHA-256 of docs/MASTER_SOURCE.md matches recorded digest (92039ba7b5436261...)
------------------------------------------------------------------------
SUMMARY [master-source]: 36/36 checks passed, 0 failed
RESULT: PASS (master-source)

========================================================================
VALIDATOR: decisions
========================================================================
PASS  docs/decisions/ exists
PASS  DEC-0001 present exactly once (DEC-0001-official-product-name.md)
PASS  DEC-0002 present exactly once (DEC-0002-multi-tenant-architecture.md)
PASS  DEC-0003 present exactly once (DEC-0003-multi-laundry-owner-model.md)
PASS  DEC-0004 present exactly once (DEC-0004-flutter-client-and-web-console.md)
PASS  DEC-0005 present exactly once (DEC-0005-api-first-modular-monolith-backend.md)
PASS  DEC-0006 present exactly once (DEC-0006-public-tracking-without-app-installation.md)
PASS  DEC-0007 present exactly once (DEC-0007-pickup-and-delivery-as-core-product.md)
PASS  DEC-0008 present exactly once (DEC-0008-h1-h3-h7-reminder-as-core-product.md)
PASS  DEC-0009 present exactly once (DEC-0009-initial-commercial-pricing.md)
PASS  DEC-0010 present exactly once (DEC-0010-no-lifetime-cloud-subscription.md)
PASS  DEC-0011 present exactly once (DEC-0011-transparent-third-party-messaging-costs.md)
PASS  DEC-0012 present exactly once (DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md)
PASS  DEC-0013 present exactly once (DEC-0013-exact-sha-evidence-before-go.md)
PASS  DEC-0014 present exactly once (DEC-0014-customer-android-does-not-replace-public-tracking.md)
PASS  DEC-0015 present exactly once (DEC-0015-mvp-focuses-on-laundry-operations.md)
PASS  DEC-0016 present exactly once (DEC-0016-public-repository-visibility-accepted-deviation.md)
PASS  DEC-0017 present exactly once (DEC-0017-single-maintainer-approval-standing-deviation.md)
PASS  DEC-0018 present exactly once (DEC-0018-two-layer-design-token-architecture.md)
PASS  DEC-0019 present exactly once (DEC-0019-light-theme-canonical-mvp-dark-mode-deferred.md)
PASS  DEC-0020 present exactly once (DEC-0020-system-first-typography-no-font-binary.md)
PASS  DEC-0021 present exactly once (DEC-0021-wcag-22-aa-aligned-accessibility-target.md)
PASS  DEC-0022 present exactly once (DEC-0022-canonical-ux-state-taxonomy-and-role-adaptive-navigation.md)
PASS  DEC-0023 present exactly once (DEC-0023-low-fidelity-wireframes-and-no-final-logo-fabrication.md)
PASS  DEC-0001 status is ACCEPTED
PASS  DEC-0001 has all 12 required headings
PASS  DEC-0002 status is ACCEPTED
PASS  DEC-0002 has all 12 required headings
PASS  DEC-0003 status is ACCEPTED
PASS  DEC-0003 has all 12 required headings
PASS  DEC-0004 status is ACCEPTED
PASS  DEC-0004 has all 12 required headings
PASS  DEC-0005 status is ACCEPTED
PASS  DEC-0005 has all 12 required headings
PASS  DEC-0006 status is ACCEPTED
PASS  DEC-0006 has all 12 required headings
PASS  DEC-0007 status is ACCEPTED
PASS  DEC-0007 has all 12 required headings
PASS  DEC-0008 status is ACCEPTED
PASS  DEC-0008 has all 12 required headings
PASS  DEC-0009 status is ACCEPTED
PASS  DEC-0009 has all 12 required headings
PASS  DEC-0010 status is ACCEPTED
PASS  DEC-0010 has all 12 required headings
PASS  DEC-0011 status is ACCEPTED
PASS  DEC-0011 has all 12 required headings
PASS  DEC-0012 status is ACCEPTED
PASS  DEC-0012 has all 12 required headings
PASS  DEC-0013 status is ACCEPTED
PASS  DEC-0013 has all 12 required headings
PASS  DEC-0014 status is ACCEPTED
PASS  DEC-0014 has all 12 required headings
PASS  DEC-0015 status is ACCEPTED
PASS  DEC-0015 has all 12 required headings
PASS  DEC-0016 status is ACCEPTED
PASS  DEC-0016 has all 12 required headings
PASS  DEC-0017 status is ACCEPTED
PASS  DEC-0017 has all 12 required headings
PASS  DEC-0018 status is ACCEPTED
PASS  DEC-0018 has all 12 required headings
PASS  DEC-0019 status is ACCEPTED
PASS  DEC-0019 has all 12 required headings
PASS  DEC-0020 status is ACCEPTED
PASS  DEC-0020 has all 12 required headings
PASS  DEC-0021 status is ACCEPTED
PASS  DEC-0021 has all 12 required headings
PASS  DEC-0022 status is ACCEPTED
PASS  DEC-0022 has all 12 required headings
PASS  DEC-0023 status is ACCEPTED
PASS  DEC-0023 has all 12 required headings
------------------------------------------------------------------------
SUMMARY [decisions]: 70/70 checks passed, 0 failed
RESULT: PASS (decisions)

========================================================================
VALIDATOR: roadmap
========================================================================
PASS  docs/ROADMAP.md exists
PASS  Step 0 declared exactly once (line 35)
PASS  Step 1 declared exactly once (line 59)
PASS  Step 2 declared exactly once (line 86)
PASS  Step 3 declared exactly once (line 106)
PASS  Step 4 declared exactly once (line 123)
PASS  Step 5 declared exactly once (line 135)
PASS  Step 6 declared exactly once (line 149)
PASS  Step 7 declared exactly once (line 163)
PASS  Step 8 declared exactly once (line 178)
PASS  Step 9 declared exactly once (line 191)
PASS  Step 10 declared exactly once (line 206)
PASS  Step 11 declared exactly once (line 219)
PASS  Step 12 declared exactly once (line 230)
PASS  Step 13 declared exactly once (line 245)
PASS  Step 14 declared exactly once (line 259)
PASS  Step 0 title matches canonical: Master Source and Governance
PASS  Step 1 title matches canonical: Product Requirement and Domain Model
PASS  Step 2 title matches canonical: Design System and UX Foundation
PASS  Step 3 title matches canonical: Runtime, Authentication, Multi-Tenancy, and RBAC
PASS  Step 4 title matches canonical: Laundry Master Data
PASS  Step 5 title matches canonical: POS, Order, and Payment Foundation
PASS  Step 6 title matches canonical: Production Operations
PASS  Step 7 title matches canonical: Customer Tracking and WhatsApp
PASS  Step 8 title matches canonical: Pickup and Delivery Operations
PASS  Step 9 title matches canonical: Unclaimed Laundry and Cashflow Recovery
PASS  Step 10 title matches canonical: Finance, Reports, and Owner Portfolio
PASS  Step 11 title matches canonical: Customer Android Experience
PASS  Step 12 title matches canonical: Subscription and Platform Administration
PASS  Step 13 title matches canonical: Security, Performance, Backup, and Recovery
PASS  Step 14 title matches canonical: Pilot and Commercial Launch
PASS  Step 2 carries an allowed working status (declared: IN PROGRESS)
PASS  Step 3 is marked PLANNED
PASS  Step 4 is marked PLANNED
PASS  Step 5 is marked PLANNED
PASS  Step 6 is marked PLANNED
PASS  Step 7 is marked PLANNED
PASS  Step 8 is marked PLANNED
PASS  Step 9 is marked PLANNED
PASS  Step 10 is marked PLANNED
PASS  Step 11 is marked PLANNED
PASS  Step 12 is marked PLANNED
PASS  Step 13 is marked PLANNED
PASS  Step 14 is marked PLANNED
------------------------------------------------------------------------
SUMMARY [roadmap]: 44/44 checks passed, 0 failed
RESULT: PASS (roadmap)

========================================================================
VALIDATOR: status
========================================================================
PASS  docs/STATUS.md exists
PASS  Step 0 status is one of ['IN PROGRESS', 'TESTED', 'WATCH', 'GO'] (found: GO)
PASS  Step 2 carries an allowed working status (declared: IN PROGRESS, NOT IMPLEMENTED)
PASS  Step 3 declared PLANNED
PASS  Step 4 declared PLANNED
PASS  Step 5 declared PLANNED
PASS  Step 6 declared PLANNED
PASS  Step 7 declared PLANNED
PASS  Step 8 declared PLANNED
PASS  Step 9 declared PLANNED
PASS  Step 10 declared PLANNED
PASS  Step 11 declared PLANNED
PASS  Step 12 declared PLANNED
PASS  Step 13 declared PLANNED
PASS  Step 14 declared PLANNED
PASS  no feature is marked IMPLEMENTED
PASS  declares backend runtime is ABSENT
PASS  declares Flutter workspace is ABSENT
PASS  declares deployment is ABSENT
PASS  declares UAT is NOT STARTED
PASS  declares application CI is NOT APPLICABLE
PASS  uses status vocabulary NOT STARTED
------------------------------------------------------------------------
SUMMARY [status]: 22/22 checks passed, 0 failed
RESULT: PASS (status)

========================================================================
VALIDATOR: pricing
========================================================================
      inspecting 178 markdown files
PASS  canonical figure present somewhere: Rp79.000
PASS  canonical figure present somewhere: Rp199.000
PASS  canonical figure present somewhere: Rp399.000
PASS  canonical figure present somewhere: Rp999.000
PASS  canonical figure present somewhere: Rp790.000
PASS  canonical figure present somewhere: Rp1.990.000
PASS  canonical figure present somewhere: Rp3.990.000
PASS  canonical trial length present: 14 hari
PASS  starter outlet limit present: 1 outlet
PASS  starter staff limit present: 5 staff
PASS  starter order limit present: 1.000 order
PASS  growth outlet limit present: 3 outlet
PASS  growth staff limit present: 20 staff
PASS  growth order limit present: 5.000 order
PASS  scale outlet limit present: 10 outlet
PASS  scale staff limit present: 75 staff
PASS  scale order limit present: 20.000 order
PASS  no conflicting plan price or plan limit found on any plan line
PASS  no conflicting trial length found
PASS  no cloud plan is offered as 'lifetime'
------------------------------------------------------------------------
SUMMARY [pricing]: 20/20 checks passed, 0 failed
RESULT: PASS (pricing)

========================================================================
VALIDATOR: rules-traceability
========================================================================
PASS  .claude/rules/ exists
PASS  all 36 rule files exist (found 36)
PASS  rule 00 file exists
PASS  rule 01 file exists
PASS  rule 02 file exists
PASS  rule 03 file exists
PASS  rule 04 file exists
PASS  rule 05 file exists
PASS  rule 06 file exists
PASS  rule 07 file exists
PASS  rule 08 file exists
PASS  rule 09 file exists
PASS  rule 10 file exists
PASS  rule 11 file exists
PASS  rule 12 file exists
PASS  rule 13 file exists
PASS  rule 14 file exists
PASS  rule 15 file exists
PASS  rule 16 file exists
PASS  rule 17 file exists
PASS  rule 18 file exists
PASS  rule 19 file exists
PASS  rule 20 file exists
PASS  rule 21 file exists
PASS  rule 22 file exists
PASS  rule 23 file exists
PASS  rule 24 file exists
PASS  rule 25 file exists
PASS  rule 26 file exists
PASS  rule 27 file exists
PASS  rule 28 file exists
PASS  rule 29 file exists
PASS  rule 30 file exists
PASS  rule 31 file exists
PASS  rule 32 file exists
PASS  rule 33 file exists
PASS  rule 34 file exists
PASS  rule 35 file exists
PASS  rule 02 (02-multi-tenancy.md) covers tenant isolation hard gate
PASS  rule 04 (04-financial-integrity.md) covers financial integrity hard gate
PASS  rule 07 (07-offline-sync.md) covers offline-first
PASS  rule 08 (08-notification-and-whatsapp.md) covers WhatsApp
PASS  rule 09 (09-pickup-and-delivery.md) covers pickup and delivery
PASS  rule 10 (10-unclaimed-laundry.md) covers aging / H+1 H+3 H+7
PASS  rule 11 (11-git-and-ci.md) covers git and CI
PASS  rule 12 (12-autonomous-execution.md) covers autonomous execution
PASS  rule 14 (14-pricing-and-commercial.md) covers pricing
PASS  rule 15 (15-current-product-status.md) covers status
PASS  rule 02 states tenant isolation as a hard gate / automatic NO-GO
PASS  rule 04 states financial integrity as a hard gate / automatic NO-GO
PASS  tracking foundation covered by: 03-security-and-privacy.md
PASS  docs/GOVERNANCE_TRACEABILITY.md exists
PASS  docs/GOVERNANCE_TRACEABILITY.md references 00-canonical-source.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 01-status-and-evidence.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 02-multi-tenancy.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 03-security-and-privacy.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 04-financial-integrity.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 05-flutter-client-foundation.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 06-backend-api-foundation.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 07-offline-sync.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 08-notification-and-whatsapp.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 09-pickup-and-delivery.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 10-unclaimed-laundry.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 11-git-and-ci.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 12-autonomous-execution.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 13-testing-and-definition-of-done.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 14-pricing-and-commercial.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 15-current-product-status.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 16-product-requirements.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 17-domain-model-and-bounded-contexts.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 18-domain-invariants.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 19-state-machines.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 20-domain-events-and-idempotency.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 21-threat-model-and-data-classification.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 22-acceptance-criteria-and-traceability.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 23-public-repository-safety.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 24-current-step-01-status.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 25-design-system-foundation.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 26-design-token-governance.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 27-accessibility-foundation.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 28-platform-adaptive-navigation.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 29-ux-state-model.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 30-content-design-and-localization.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 31-responsive-and-device-foundation.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 32-security-and-privacy-ux.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 33-design-traceability.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 34-component-and-screen-governance.md
PASS  docs/GOVERNANCE_TRACEABILITY.md references 35-current-step-02-status.md
------------------------------------------------------------------------
SUMMARY [rules-traceability]: 88/88 checks passed, 0 failed
RESULT: PASS (rules-traceability)

========================================================================
VALIDATOR: no-runtime
========================================================================
PASS  no Flutter/Dart manifest or artifact present
PASS  no Laravel/PHP/Composer manifest present
PASS  no Node/package.json manifest present
PASS  no .dart/.php/.kt/.java/.swift source file present
PASS  no database migration directory present
PASS  no application Dockerfile or docker-compose present
PASS  no symlink points outside the repository
------------------------------------------------------------------------
SUMMARY [no-runtime]: 7/7 checks passed, 0 failed
RESULT: PASS (no-runtime)

========================================================================
VALIDATOR: markdown-links
========================================================================
      checking 226 markdown files
PASS  all 1610 relative markdown links resolve to existing paths
------------------------------------------------------------------------
SUMMARY [markdown-links]: 1/1 checks passed, 0 failed
RESULT: PASS (markdown-links)

========================================================================
VALIDATOR: secrets
========================================================================
      scanning 356 files (excluding scripts/validate-secrets.sh)
PASS  no match for credential pattern: private key block
PASS  no match for credential pattern: PGP/private key armor
PASS  no match for credential pattern: AWS access key id
PASS  no match for credential pattern: AWS secret access key
PASS  no match for credential pattern: GitHub token
PASS  no match for credential pattern: GitHub fine-grained PAT
PASS  no match for credential pattern: Slack token
PASS  no match for credential pattern: Slack incoming webhook
PASS  no match for credential pattern: generic credential assignment
PASS  no .env, id_rsa, .pem, keystore, or service-account file is tracked
------------------------------------------------------------------------
SUMMARY [secrets]: 10/10 checks passed, 0 failed
RESULT: PASS (secrets)

========================================================================
VALIDATOR: destructive-guard
========================================================================
PASS  hook file exists: .claude/hooks/guard-destructive-operations.sh
PASS  hook file is executable
PASS  hook --self-test exits 0
PASS  blocked (exit 2) as expected: rm -rf /
PASS  blocked (exit 2) as expected: rm -rf ~
PASS  blocked (exit 2) as expected: git push --force origin main
PASS  blocked (exit 2) as expected: git reset --hard HEAD~5
PASS  blocked (exit 2) as expected: git clean -fdx
PASS  blocked (exit 2) as expected: flutter create .
PASS  blocked (exit 2) as expected: composer create-project laravel/laravel backend
PASS  blocked (exit 2) as expected: chmod -R 777 /
PASS  allowed (exit 0) as expected: git status
PASS  allowed (exit 0) as expected: ls -la
PASS  allowed (exit 0) as expected: python3 scripts/validate-required-files.py
PASS  allowed (exit 0) as expected: git log --oneline -n 5
PASS  allowed (exit 0) as expected: cat README.md
------------------------------------------------------------------------
SUMMARY [destructive-guard]: 16/16 checks passed, 0 failed
RESULT: PASS (destructive-guard)

========================================================================
VALIDATOR: product-requirements
========================================================================
PASS  exists: docs/product/PRODUCT_REQUIREMENTS.md
PASS  exists: docs/product/MVP_SCOPE.md
PASS  exists: docs/product/PERSONAS.md
PASS  exists: docs/product/JOBS_TO_BE_DONE.md
PASS  exists: docs/product/USER_JOURNEYS.md
PASS  exists: docs/product/OPERATIONAL_JOURNEYS.md
PASS  exists: docs/product/USE_CASE_CATALOG.md
PASS  exists: docs/product/SUCCESS_METRICS.md
PASS  exists: docs/product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md
PASS  exists: docs/product/REQUIREMENT_TRACEABILITY.md
PASS  PRD declares a document version
PASS  PRD uses the canonical product name "Aish Laundry App"
PASS  PRD covers topic: product vision
PASS  PRD covers topic: problem statement
PASS  PRD covers topic: goals
PASS  PRD covers topic: non-goals
PASS  PRD covers topic: personas
PASS  PRD covers topic: MVP scope
PASS  PRD covers topic: functional requirements
PASS  PRD covers topic: security requirements
PASS  PRD covers topic: privacy requirements
PASS  PRD covers topic: multi-tenancy requirements
PASS  PRD covers topic: financial requirements
PASS  PRD covers topic: offline requirements
PASS  PRD covers topic: tracking requirements
PASS  PRD covers topic: pickup and delivery requirements
PASS  PRD covers topic: unclaimed laundry requirements
PASS  PRD covers topic: reporting requirements
PASS  PRD covers topic: subscription requirements
PASS  PRD covers topic: acceptance criteria
PASS  PRD covers topic: traceability
PASS  PRD covers topic: assumptions
PASS  PRD covers topic: open questions
PASS  PRD covers topic: risks
PASS  pricing reproduced exactly: Rp79.000
PASS  pricing reproduced exactly: Rp199.000
PASS  pricing reproduced exactly: Rp399.000
PASS  pricing reproduced exactly: Rp999.000
PASS  pricing reproduced exactly: Rp790.000
PASS  pricing reproduced exactly: Rp1.990.000
PASS  pricing reproduced exactly: Rp3.990.000
PASS  pricing reproduced exactly: 14 hari gratis
PASS  PRD is substantial: 1146 lines (minimum 300)
PASS  PRD marks nothing as IMPLEMENTED
PASS  PRD never claims tests passed
PASS  PRD never claims CI is green
PASS  PRD never claims a deployment
PASS  PRD never claims UAT completed
------------------------------------------------------------------------
SUMMARY [product-requirements]: 48/48 checks passed, 0 failed
RESULT: PASS (product-requirements)

========================================================================
VALIDATOR: requirement-ids
========================================================================
PASS  Step 1 documents exist to validate
      scanned 48 Step 1 documents
PASS  corpus defines at least 120 requirements (found 498)
PASS  every requirement ID is defined exactly once
PASS  every requirement ID uses a canonical prefix
PASS  no malformed requirement identifiers
PASS  prefix FR- has at least one requirement (found 120)
PASS  prefix NFR- has at least one requirement (found 50)
PASS  prefix SEC- has at least one requirement (found 68)
PASS  prefix TEN- has at least one requirement (found 30)
PASS  prefix FIN- has at least one requirement (found 40)
PASS  prefix OFF- has at least one requirement (found 25)
PASS  prefix TRK- has at least one requirement (found 30)
PASS  prefix DEL- has at least one requirement (found 35)
PASS  prefix UCL- has at least one requirement (found 30)
PASS  prefix NOT- has at least one requirement (found 30)
PASS  prefix SUB- has at least one requirement (found 20)
PASS  prefix RPT- has at least one requirement (found 20)
PASS  every referenced requirement ID is defined somewhere
------------------------------------------------------------------------
SUMMARY [requirement-ids]: 18/18 checks passed, 0 failed
RESULT: PASS (requirement-ids)

========================================================================
VALIDATOR: personas
========================================================================
PASS  docs/product/PERSONAS.md exists
PASS  persona documented: Platform Super Admin
PASS  persona documented: Platform Support
PASS  persona documented: Tenant Owner
PASS  persona documented: Tenant Admin
PASS  persona documented: Outlet Manager
PASS  persona documented: Cashier
PASS  persona documented: Production Operator
PASS  persona documented: Quality Control
PASS  persona documented: Courier Internal
PASS  persona documented: External Local Courier
PASS  persona documented: Finance
PASS  persona documented: Customer
PASS  persona documented: Corporate Customer Contact
PASS  persona documented: Authorized Order Recipient
PASS  the canonical persona set is fourteen (declared 14)
PASS  personas document attribute: goals
PASS  personas document attribute: pain points
PASS  personas document attribute: responsibilities
PASS  personas document attribute: devices
PASS  personas document attribute: connectivity context
PASS  personas document attribute: frequency of use
PASS  personas document attribute: sensitive data exposure
PASS  personas document attribute: critical actions
PASS  personas document attribute: prohibited actions
PASS  personas document attribute: success metrics
PASS  personas document attribute: accessibility considerations
PASS  External Local Courier is documented as using a guest job link
PASS  External Local Courier: the guest link expires
PASS  External Local Courier: the guest link is revocable
PASS  External Local Courier is documented as having no tenant membership
PASS  personas document is substantial: 463 lines (minimum 150)
------------------------------------------------------------------------
SUMMARY [personas]: 32/32 checks passed, 0 failed
RESULT: PASS (personas)

========================================================================
VALIDATOR: use-cases
========================================================================
PASS  exists: docs/product/USE_CASE_CATALOG.md
PASS  exists: docs/product/MVP_SCOPE.md
PASS  exists: docs/product/USER_JOURNEYS.md
PASS  exists: docs/product/OPERATIONAL_JOURNEYS.md
PASS  exists: docs/product/SUCCESS_METRICS.md
PASS  exists: docs/product/JOBS_TO_BE_DONE.md
PASS  exists: docs/product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md
PASS  use-case catalogue is substantial (~173 entries, minimum 30)
PASS  use cases name their actors (14/14 personas referenced)
PASS  use cases cite requirement IDs
PASS  MVP scope names the MVP
PASS  MVP scope states what is explicitly out of scope
PASS  MVP scope maps capability to Step 3
PASS  MVP scope maps capability to Step 5
PASS  MVP scope maps capability to Step 7
PASS  MVP scope maps capability to Step 9
PASS  MVP scope maps capability to Step 10
PASS  user journeys include a Mermaid diagram
PASS  user journeys have written narrative alongside diagrams
PASS  operational journeys include a Mermaid diagram
PASS  operational journeys have written narrative alongside diagrams
PASS  success metrics state what will be measured, not what was achieved
PASS  success metrics defer baselines and targets to Step 14
PASS  success metrics never claims a measured result
PASS  success metrics never claims a current metric value
PASS  success metrics never claims an established baseline
PASS  open questions register records assumptions and open questions
PASS  open questions are directed to the repository owner
------------------------------------------------------------------------
SUMMARY [use-cases]: 28/28 checks passed, 0 failed
RESULT: PASS (use-cases)

========================================================================
VALIDATOR: domain-glossary
========================================================================
PASS  docs/domain/DOMAIN_GLOSSARY.md exists
PASS  glossary defines term: Tenant
PASS  glossary defines term: Membership
PASS  glossary defines term: Outlet
PASS  glossary defines term: Brand
PASS  glossary defines term: Aggregate
PASS  glossary defines term: Bounded Context
PASS  glossary defines term: Money
PASS  glossary defines term: Idempoten
PASS  glossary defines term: Client Reference
PASS  glossary defines term: Tracking Token
PASS  glossary defines term: Aging
PASS  glossary defines term: Rework
PASS  glossary defines term: Quiet Hours
PASS  glossary defines term: Kiloan
PASS  glossary defines term: Satuan
PASS  glossary defines term: Nota
PASS  glossary defines term: Courier
PASS  glossary defines term: Proof
PASS  glossary defines term: Reversal
PASS  glossary defines term: Shift
PASS  glossary lists the canonical order statuses
PASS  glossary references the aggregate vocabulary (10/31)
PASS  glossary is substantial: ~119 entries (minimum 40)
PASS  glossary states that its terms are binding
------------------------------------------------------------------------
SUMMARY [domain-glossary]: 25/25 checks passed, 0 failed
RESULT: PASS (domain-glossary)

========================================================================
VALIDATOR: bounded-contexts
========================================================================
PASS  docs/domain/BOUNDED_CONTEXTS.md exists
PASS  the canonical context set is twenty (declared 20)
PASS  bounded context documented: Identity and Access
PASS  bounded context documented: Tenant and Organization
PASS  bounded context documented: Subscription and Entitlement
PASS  bounded context documented: Customer Management
PASS  bounded context documented: Service Catalog and Pricing
PASS  bounded context documented: Order Intake and POS
PASS  bounded context documented: Production Operations
PASS  bounded context documented: Quality Control and Rework
PASS  bounded context documented: Payment and Receivables
PASS  bounded context documented: Customer Tracking
PASS  bounded context documented: Pickup and Delivery
PASS  bounded context documented: Courier Assignment and Settlement
PASS  bounded context documented: Notification and Communication
PASS  bounded context documented: Unclaimed Laundry Recovery
PASS  bounded context documented: Loyalty, Membership, and Deposit
PASS  bounded context documented: Reporting and Owner Portfolio
PASS  bounded context documented: Audit and Compliance
PASS  bounded context documented: Platform Administration
PASS  bounded context documented: Offline Synchronization
PASS  bounded context documented: File and Evidence Management
PASS  contexts document facet: purpose
PASS  contexts document facet: primary actors
PASS  contexts document facet: aggregates
PASS  contexts document facet: commands
PASS  contexts document facet: events
PASS  contexts document facet: upstream contexts
PASS  contexts document facet: downstream contexts
PASS  contexts document facet: tenant boundary
PASS  contexts document facet: sensitive data
PASS  contexts document facet: failure impact
PASS  contexts document facet: implementation step
PASS  docs/domain/CONTEXT_MAP.md exists
PASS  context map contains at least one Mermaid diagram
PASS  context map has a textual explanation alongside its diagram
PASS  context map references every bounded context
------------------------------------------------------------------------
SUMMARY [bounded-contexts]: 37/37 checks passed, 0 failed
RESULT: PASS (bounded-contexts)

========================================================================
VALIDATOR: aggregates
========================================================================
PASS  docs/domain/AGGREGATE_CATALOG.md exists
PASS  the canonical aggregate set is thirty-one (declared 31)
PASS  aggregate catalogued: Tenant
PASS  aggregate catalogued: Membership
PASS  aggregate catalogued: LaundryBrand
PASS  aggregate catalogued: Outlet
PASS  aggregate catalogued: Customer
PASS  aggregate catalogued: CustomerAddress
PASS  aggregate catalogued: ServiceCatalog
PASS  aggregate catalogued: PriceList
PASS  aggregate catalogued: PriceRule
PASS  aggregate catalogued: LaundryOrder
PASS  aggregate catalogued: OrderLine
PASS  aggregate catalogued: OrderConditionEvidence
PASS  aggregate catalogued: ProductionJob
PASS  aggregate catalogued: QualityControlInspection
PASS  aggregate catalogued: Payment
PASS  aggregate catalogued: Refund
PASS  aggregate catalogued: Receivable
PASS  aggregate catalogued: CashierShift
PASS  aggregate catalogued: PickupDeliveryJob
PASS  aggregate catalogued: CourierAssignment
PASS  aggregate catalogued: DeliveryProof
PASS  aggregate catalogued: CourierSettlement
PASS  aggregate catalogued: TrackingAccess
PASS  aggregate catalogued: Notification
PASS  aggregate catalogued: ReminderSchedule
PASS  aggregate catalogued: UnclaimedLaundryCase
PASS  aggregate catalogued: Subscription
PASS  aggregate catalogued: AuditEntry
PASS  aggregate catalogued: Attachment
PASS  aggregate catalogued: OfflineOperation
PASS  aggregate catalogued: SyncConflict
PASS  catalogue documents facet: aggregate root
PASS  catalogue documents facet: entities
PASS  catalogue documents facet: value objects
PASS  catalogue documents facet: commands
PASS  catalogue documents facet: invariants
PASS  catalogue documents facet: domain events
PASS  catalogue documents facet: tenant ownership
PASS  catalogue documents facet: concurrency
PASS  catalogue documents facet: idempotency
PASS  catalogue documents facet: retention
PASS  catalogue documents facet: sensitive fields
PASS  catalogue documents facet: deletion or reversal policy
PASS  catalogue states tenant ownership explicitly
PASS  docs/domain/ENTITY_AND_VALUE_OBJECT_CATALOG.md exists
PASS  all 25 canonical value objects catalogued
PASS  conceptual model carries the marker: CONCEPTUAL DOMAIN MODEL — NOT DATABASE SCHEMA
PASS  Step 1 domain model contains no SQL CREATE TABLE
PASS  Step 1 domain model contains no SQL ALTER TABLE
PASS  Step 1 domain model contains no SQL column type
PASS  Step 1 domain model contains no a migration file
------------------------------------------------------------------------
SUMMARY [aggregates]: 53/53 checks passed, 0 failed
RESULT: PASS (aggregates)

========================================================================
VALIDATOR: domain-invariants
========================================================================
PASS  docs/domain/DOMAIN_INVARIANTS.md exists
PASS  invariant catalogued: every business record carries a tenant identifier
PASS  invariant catalogued: a client-supplied tenant ID is not authorisation
PASS  invariant catalogued: money is integer Rupiah
PASS  invariant catalogued: floating point is forbidden in money paths
PASS  invariant catalogued: payments are idempotent
PASS  invariant catalogued: historical prices are immutable
PASS  invariant catalogued: financial records are not hard-deleted
PASS  invariant catalogued: corrections are reversal-based
PASS  invariant catalogued: the first-ready timestamp is immutable and aging never restarts
PASS  invariant catalogued: order status is one of the canonical set
PASS  invariant catalogued: only enumerated transitions occur
PASS  invariant catalogued: the tracking token is not the order number
PASS  invariant catalogued: only the token hash is stored
PASS  invariant catalogued: custody transfer requires proof
PASS  invariant catalogued: courier cash variance is recorded
PASS  invariant catalogued: a retry reuses its client reference
PASS  invariant catalogued: duplicate orders and payments are unacceptable
PASS  invariant catalogued: invariants are enforced server-side
PASS  invariant catalogued: concurrent operations are serialised
PASS  catalogue enumerates at least 20 invariants (found ~231)
PASS  invariants name their owning aggregate
PASS  invariants name the roadmap Step that enforces them
PASS  catalogue states that no invariant is enforced yet
------------------------------------------------------------------------
SUMMARY [domain-invariants]: 24/24 checks passed, 0 failed
RESULT: PASS (domain-invariants)

========================================================================
VALIDATOR: domain-events
========================================================================
PASS  docs/domain/DOMAIN_EVENTS.md exists
PASS  docs/domain/COMMANDS_AND_POLICIES.md exists
PASS  catalogue enumerates at least 25 domain events (found 139)
PASS  event records carry field: event name
PASS  event records carry field: version
PASS  event records carry field: occurrence timestamp
PASS  event records carry field: tenant
PASS  event records carry field: actor
PASS  event records carry field: aggregate identity
PASS  event records carry field: correlation identifier
PASS  events reference their source aggregates (31/31 aggregates named)
PASS  catalogue states that every event has a source aggregate
PASS  event model states: events are immutable
PASS  event model states: events are versioned
PASS  event model states: events carry tenant context explicitly
PASS  event model states: events never carry secrets or plaintext tokens
PASS  event model states: idempotency is a server contract
PASS  event model states: client reference is reused on retry
PASS  event model states: consumers are idempotent
PASS  event model states: delivery is at least once
PASS  event model states: deduplication keys are documented
PASS  event model states: retries use exponential backoff
PASS  event model states: a failed handler is not silently dropped
PASS  event model states: commands are distinguished from events
PASS  event model states: policies react to events
------------------------------------------------------------------------
SUMMARY [domain-events]: 25/25 checks passed, 0 failed
RESULT: PASS (domain-events)

========================================================================
VALIDATOR: state-machines
========================================================================
PASS  exists: docs/state-machines/ORDER_STATE_MACHINE.md
PASS  exists: docs/state-machines/PAYMENT_STATE_MACHINE.md
PASS  exists: docs/state-machines/REFUND_STATE_MACHINE.md
PASS  exists: docs/state-machines/PRODUCTION_STATE_MACHINE.md
PASS  exists: docs/state-machines/QUALITY_CONTROL_STATE_MACHINE.md
PASS  exists: docs/state-machines/TRACKING_ACCESS_LIFECYCLE.md
PASS  exists: docs/state-machines/PICKUP_DELIVERY_STATE_MACHINE.md
PASS  exists: docs/state-machines/COURIER_SETTLEMENT_STATE_MACHINE.md
PASS  exists: docs/state-machines/UNCLAIMED_LAUNDRY_STATE_MACHINE.md
PASS  exists: docs/state-machines/SUBSCRIPTION_STATE_MACHINE.md
PASS  order machine declares all 15 canonical statuses
PASS  pickup/delivery machine declares all 11 canonical statuses
PASS  quality control machine declares all 4 canonical statuses
PASS  ORDER_STATE_MACHINE.md contains a Mermaid diagram
PASS  ORDER_STATE_MACHINE.md contains a written transition table (93 rows)
PASS  ORDER_STATE_MACHINE.md states forbidden transitions explicitly
PASS  ORDER_STATE_MACHINE.md names transition actors
PASS  ORDER_STATE_MACHINE.md states transition preconditions
PASS  PAYMENT_STATE_MACHINE.md contains a Mermaid diagram
PASS  PAYMENT_STATE_MACHINE.md contains a written transition table (62 rows)
PASS  PAYMENT_STATE_MACHINE.md states forbidden transitions explicitly
PASS  PAYMENT_STATE_MACHINE.md names transition actors
PASS  PAYMENT_STATE_MACHINE.md states transition preconditions
PASS  REFUND_STATE_MACHINE.md contains a Mermaid diagram
PASS  REFUND_STATE_MACHINE.md contains a written transition table (50 rows)
PASS  REFUND_STATE_MACHINE.md states forbidden transitions explicitly
PASS  REFUND_STATE_MACHINE.md names transition actors
PASS  REFUND_STATE_MACHINE.md states transition preconditions
PASS  PRODUCTION_STATE_MACHINE.md contains a Mermaid diagram
PASS  PRODUCTION_STATE_MACHINE.md contains a written transition table (51 rows)
PASS  PRODUCTION_STATE_MACHINE.md states forbidden transitions explicitly
PASS  PRODUCTION_STATE_MACHINE.md names transition actors
PASS  PRODUCTION_STATE_MACHINE.md states transition preconditions
PASS  QUALITY_CONTROL_STATE_MACHINE.md contains a Mermaid diagram
PASS  QUALITY_CONTROL_STATE_MACHINE.md contains a written transition table (43 rows)
PASS  QUALITY_CONTROL_STATE_MACHINE.md states forbidden transitions explicitly
PASS  QUALITY_CONTROL_STATE_MACHINE.md names transition actors
PASS  QUALITY_CONTROL_STATE_MACHINE.md states transition preconditions
PASS  TRACKING_ACCESS_LIFECYCLE.md contains a Mermaid diagram
PASS  TRACKING_ACCESS_LIFECYCLE.md contains a written transition table (54 rows)
PASS  TRACKING_ACCESS_LIFECYCLE.md states forbidden transitions explicitly
PASS  TRACKING_ACCESS_LIFECYCLE.md names transition actors
PASS  TRACKING_ACCESS_LIFECYCLE.md states transition preconditions
PASS  PICKUP_DELIVERY_STATE_MACHINE.md contains a Mermaid diagram
PASS  PICKUP_DELIVERY_STATE_MACHINE.md contains a written transition table (65 rows)
PASS  PICKUP_DELIVERY_STATE_MACHINE.md states forbidden transitions explicitly
PASS  PICKUP_DELIVERY_STATE_MACHINE.md names transition actors
PASS  PICKUP_DELIVERY_STATE_MACHINE.md states transition preconditions
PASS  COURIER_SETTLEMENT_STATE_MACHINE.md contains a Mermaid diagram
PASS  COURIER_SETTLEMENT_STATE_MACHINE.md contains a written transition table (54 rows)
PASS  COURIER_SETTLEMENT_STATE_MACHINE.md states forbidden transitions explicitly
PASS  COURIER_SETTLEMENT_STATE_MACHINE.md names transition actors
PASS  COURIER_SETTLEMENT_STATE_MACHINE.md states transition preconditions
PASS  UNCLAIMED_LAUNDRY_STATE_MACHINE.md contains a Mermaid diagram
PASS  UNCLAIMED_LAUNDRY_STATE_MACHINE.md contains a written transition table (71 rows)
PASS  UNCLAIMED_LAUNDRY_STATE_MACHINE.md states forbidden transitions explicitly
PASS  UNCLAIMED_LAUNDRY_STATE_MACHINE.md names transition actors
PASS  UNCLAIMED_LAUNDRY_STATE_MACHINE.md states transition preconditions
PASS  SUBSCRIPTION_STATE_MACHINE.md contains a Mermaid diagram
PASS  SUBSCRIPTION_STATE_MACHINE.md contains a written transition table (59 rows)
PASS  SUBSCRIPTION_STATE_MACHINE.md states forbidden transitions explicitly
PASS  SUBSCRIPTION_STATE_MACHINE.md names transition actors
PASS  SUBSCRIPTION_STATE_MACHINE.md states transition preconditions
PASS  aging is anchored to the FIRST READY_FOR_PICKUP
PASS  aging clock is documented as never restarting
------------------------------------------------------------------------
SUMMARY [state-machines]: 65/65 checks passed, 0 failed
RESULT: PASS (state-machines)

========================================================================
VALIDATOR: tenant-boundaries
========================================================================
PASS  docs/domain/TENANT_BOUNDARIES.md exists
PASS  docs/domain/DATA_OWNERSHIP.md exists
PASS  tenant hierarchy level documented: User Account
PASS  tenant hierarchy level documented: Membership
PASS  tenant hierarchy level documented: Tenant
PASS  tenant hierarchy level documented: Laundry Brand
PASS  tenant hierarchy level documented: Outlet
PASS  tenant model states: a client-supplied tenant ID is never authorisation proof
PASS  tenant model states: membership and permission are verified server-side
PASS  tenant model states: every business aggregate carries tenant ownership
PASS  tenant model states: records are never merged on matching name, email, or phone
PASS  tenant model states: a customer profile is tenant-scoped, not global
PASS  tenant model states: the same phone number in two tenants is two separate profiles
PASS  tenant model states: cross-tenant exposure is an automatic NO-GO
PASS  tenant model states: the owner portfolio does not weaken isolation
PASS  tenant model states: caches, queues, and object keys are tenant-scoped
PASS  every aggregate appears in the tenant-boundary model
------------------------------------------------------------------------
SUMMARY [tenant-boundaries]: 17/17 checks passed, 0 failed
RESULT: PASS (tenant-boundaries)

========================================================================
VALIDATOR: money-rules
========================================================================
PASS  Step 1 corpus exists to validate
PASS  corpus states: money is integer Rupiah
PASS  corpus states: floating point is forbidden in financial paths
PASS  corpus states: payments are idempotent
PASS  corpus states: idempotency is keyed on a client reference
PASS  corpus states: historical price snapshot is immutable
PASS  corpus states: a price-list change never alters a past order
PASS  corpus states: financial records are never hard-deleted
PASS  corpus states: corrections are reversal or adjustment entries
PASS  corpus states: an order is never marked paid on a client claim
PASS  corpus states: gateway callbacks are verified server-side
PASS  corpus states: refund requires permission and a reason
PASS  corpus states: shift closing compares expected against actual cash
PASS  corpus states: courier cash is reconciled
PASS  docs/domain/PAYMENT_DOMAIN.md exists
PASS  payment domain states: money is integer Rupiah
PASS  payment domain states: payments are idempotent
PASS  payment domain states: corrections are reversal-based
PASS  no a float() cast on money in the corpus
PASS  no a float-typed money field in the corpus
PASS  no a fractional decimal money column in the corpus
------------------------------------------------------------------------
SUMMARY [money-rules]: 21/21 checks passed, 0 failed
RESULT: PASS (money-rules)

========================================================================
VALIDATOR: tracking-rules
========================================================================
PASS  docs/domain/TRACKING_DOMAIN.md exists
PASS  docs/state-machines/TRACKING_ACCESS_LIFECYCLE.md exists
PASS  tracking model states: the token is high-entropy
PASS  tracking model states: the token is stored hashed
PASS  tracking model states: the token is NOT the order number
PASS  tracking model states: the token is not derivable from the order number
PASS  tracking model states: the token is revocable
PASS  tracking model states: the token expires
PASS  tracking model states: the portal is noindex
PASS  tracking model states: rate limiting applies
PASS  tracking model states: enumeration protection applies
PASS  tracking model states: the customer name is masked
PASS  tracking model states: the full address is never shown
PASS  tracking model states: sensitive actions require OTP
PASS  tracking model states: the tracking projection is separate from the internal order
PASS  tracking model states: internal notes are not exposed
PASS  tracking model never describes storing the plaintext token
PASS  tracking model never describes using the order number as the token
PASS  tracking access lifecycle covers: issuance
PASS  tracking access lifecycle covers: revocation
PASS  tracking access lifecycle covers: expiry
PASS  tracking access lifecycle covers: reissue
------------------------------------------------------------------------
SUMMARY [tracking-rules]: 22/22 checks passed, 0 failed
RESULT: PASS (tracking-rules)

========================================================================
VALIDATOR: delivery-rules
========================================================================
PASS  docs/domain/PICKUP_DELIVERY_DOMAIN.md exists
PASS  docs/state-machines/PICKUP_DELIVERY_STATE_MACHINE.md exists
PASS  docs/state-machines/COURIER_SETTLEMENT_STATE_MACHINE.md exists
PASS  all 11 canonical delivery statuses documented
PASS  delivery model states: proof of pickup and delivery is mandatory
PASS  delivery model states: proof mechanisms include OTP, photo, signature, recipient name
PASS  delivery model states: proof artifacts are private
PASS  delivery model states: time windows are used rather than exact times
PASS  delivery model states: service zones are defined
PASS  delivery model states: courier assignment is explicit
PASS  delivery model states: cash collection is a financial transaction
PASS  delivery model states: courier cash is reconciled
PASS  delivery model states: a failed delivery is a first-class outcome
PASS  delivery model states: reschedule is supported
PASS  delivery model states: the external courier uses an expiring guest link
PASS  delivery model states: the guest link is revocable
PASS  delivery model states: the guest link is scoped to one job
PASS  delivery model states: the guest token is high-entropy and hashed
PASS  delivery model states: offline capture is supported for couriers
PASS  route ordering is described as a suggestion
PASS  corpus never claims 'optimal route'
PASS  corpus never claims a route optimization engine
PASS  corpus never claims a guaranteed arrival time
PASS  corpus never claims 'shortest possible route'
PASS  courier settlement covers: expected versus actual cash
PASS  courier settlement covers: variance is recorded
PASS  courier settlement covers: handover is tracked
------------------------------------------------------------------------
SUMMARY [delivery-rules]: 27/27 checks passed, 0 failed
RESULT: PASS (delivery-rules)

========================================================================
VALIDATOR: unclaimed-laundry-rules
========================================================================
PASS  docs/domain/UNCLAIMED_LAUNDRY_DOMAIN.md exists
PASS  docs/state-machines/UNCLAIMED_LAUNDRY_STATE_MACHINE.md exists
PASS  reminder ladder stage present: H+1
PASS  reminder ladder stage present: H+3
PASS  reminder ladder stage present: H+7
PASS  reminder ladder stage present: H+14
PASS  dashboard field documented: order count
PASS  dashboard field documented: customer count
PASS  dashboard field documented: held invoices
PASS  dashboard field documented: unpaid balance
PASS  dashboard field documented: order age
PASS  dashboard field documented: outlet
PASS  dashboard field documented: last reminder
PASS  dashboard field documented: follow-up officer
PASS  dashboard field documented: reason not collected
PASS  aging buckets documented (8/8 boundaries present)
PASS  unclaimed model states: aging is anchored to the FIRST READY_FOR_PICKUP
PASS  unclaimed model states: the aging clock never restarts
PASS  unclaimed model states: the first-ready timestamp is immutable
PASS  unclaimed model states: each ladder stage fires once
PASS  unclaimed model states: quiet hours are respected
PASS  unclaimed model states: opt-out is honoured
PASS  unclaimed model states: the H+7 follow-up task is assignable
PASS  unclaimed model states: the H+14 escalation reaches a manager or owner
PASS  the prohibition on automatic disposal is stated explicitly
PASS  no automatic disposal behaviour is proposed anywhere in the corpus
------------------------------------------------------------------------
SUMMARY [unclaimed-laundry-rules]: 26/26 checks passed, 0 failed
RESULT: PASS (unclaimed-laundry-rules)

========================================================================
VALIDATOR: threat-model
========================================================================
PASS  docs/security/INITIAL_THREAT_MODEL.md exists
PASS  threat model declares STRIDE as its method
PASS  STRIDE category covered: spoofing
PASS  STRIDE category covered: tampering
PASS  STRIDE category covered: repudiation
PASS  STRIDE category covered: information disclosure
PASS  STRIDE category covered: denial of service
PASS  STRIDE category covered: elevation of privilege
PASS  asset in scope: customer data
PASS  asset in scope: order data
PASS  asset in scope: payment record
PASS  asset in scope: tenant configuration
PASS  asset in scope: membership
PASS  asset in scope: tracking token
PASS  asset in scope: audit
PASS  asset in scope: notification consent
PASS  asset in scope: offline queue
PASS  asset in scope: subscription entitlement
PASS  asset in scope: uploaded image
PASS  trust boundary documented: tracking portal
PASS  trust boundary documented: customer android
PASS  trust boundary documented: ops android
PASS  trust boundary documented: console web
PASS  trust boundary documented: backend api
PASS  trust boundary documented: tenant boundary
PASS  trust boundary documented: redis
PASS  trust boundary documented: postgresql
PASS  trust boundary documented: object storage
PASS  trust boundary documented: whatsapp
PASS  trust boundary documented: payment provider
PASS  trust boundary documented: guest link
PASS  trust boundary documented: support
PASS  trust boundary documented: offline device storage
PASS  threat model enumerates at least 20 threats (found 50)
PASS  threat records carry field: actor
PASS  threat records carry field: asset
PASS  threat records carry field: precondition
PASS  threat records carry field: scenario
PASS  threat records carry field: impact
PASS  threat records carry field: likelihood
PASS  threat records carry field: severity
PASS  threat records carry field: prevention
PASS  threat records carry field: detection
PASS  threat records carry field: response
PASS  threat records carry field: residual risk
PASS  threat records carry field: implementation step
PASS  threat model uses canonical severities (found: CRITICAL, HIGH, MEDIUM, LOW, INFORMATIONAL)
PASS  every threat record declares an explicit Severity
      CRITICAL/HIGH threats found: 34
PASS  every CRITICAL and HIGH threat carries an explicit mitigation
------------------------------------------------------------------------
SUMMARY [threat-model]: 49/49 checks passed, 0 failed
RESULT: PASS (threat-model)

========================================================================
VALIDATOR: data-classification
========================================================================
PASS  docs/security/DATA_CLASSIFICATION.md exists
PASS  data class defined: PUBLIC
PASS  data class defined: INTERNAL
PASS  data class defined: CONFIDENTIAL
PASS  data class defined: RESTRICTED
PASS  data class defined: SECRET
PASS  marketing pricing is classified PUBLIC (via section heading)
PASS  customer phone is classified CONFIDENTIAL (via inline)
PASS  customer address is classified RESTRICTED (via inline)
PASS  tracking token is classified SECRET (via section heading)
PASS  OTP is classified SECRET (via inline)
PASS  payment provider credential is classified SECRET (via inline)
PASS  private key is classified SECRET (via section heading)
PASS  laundry photograph is classified RESTRICTED (via section heading)
PASS  public-repository constraint stated: only PUBLIC and sanitised INTERNAL material is committed
PASS  public-repository constraint stated: higher classes are never instantiated with real values
PASS  public-repository constraint stated: every example datum is fictional
PASS  document acknowledges the repository is PUBLIC
------------------------------------------------------------------------
SUMMARY [data-classification]: 18/18 checks passed, 0 failed
RESULT: PASS (data-classification)

========================================================================
VALIDATOR: acceptance-criteria
========================================================================
PASS  docs/quality/ACCEPTANCE_CRITERIA.md exists
PASS  docs/security/SECURITY_ACCEPTANCE_CRITERIA.md exists
PASS  docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md exists
PASS  docs/quality/STEP_01_DEFINITION_OF_DONE.md exists
PASS  criteria use Given/When/Then (55 'Given' clauses)
PASS  criteria use When and Then clauses
PASS  criteria cite requirement IDs (498 distinct IDs cited)
PASS  scenario covered: owner with multiple tenants
PASS  scenario covered: customer number reused across tenants
PASS  scenario covered: cross-tenant access denied
PASS  scenario covered: immutable price snapshot
PASS  scenario covered: partial payment
PASS  scenario covered: payment replay
PASS  scenario covered: duplicate offline order
PASS  scenario covered: order lifecycle
PASS  scenario covered: quality control rework
PASS  scenario covered: tracking token expiry
PASS  scenario covered: tracking token revocation
PASS  scenario covered: external courier guest access
PASS  scenario covered: proof of delivery
PASS  scenario covered: failed delivery
PASS  scenario covered: H+1/H+3/H+7 reminders
PASS  scenario covered: opt-out honoured
PASS  scenario covered: overdue laundry escalation
PASS  scenario covered: provider notification failure
PASS  scenario covered: subscription entitlement
PASS  scenario covered: portfolio dashboard authorization
PASS  criteria cover negative paths, not only happy paths
PASS  NFR target reproduced: 500 ms
PASS  NFR target reproduced: 2.5 second
PASS  NFR target reproduced: 3.5 second
PASS  NFR target reproduced: 99.5
PASS  NFR target reproduced: 99.9
PASS  NFR target reproduced: 15 minute
PASS  NFR target reproduced: 4 hour
PASS  NFRs state: metric
PASS  NFRs state: measurement method
PASS  NFRs state: environment
PASS  NFRs state: threshold
PASS  NFRs state: responsible step
PASS  NFRs state: failure consequence
PASS  NFRs state that targets are not yet measured
PASS  never claims all criteria are met
PASS  never claims criteria passed
PASS  never claims tests passed
PASS  Step 1 Definition of Done states: no application tests exist
PASS  Step 1 Definition of Done states: application CI is NOT APPLICABLE
PASS  Step 1 Definition of Done states: GO is owner-conferred
PASS  Step 1 Definition of Done states: exact-SHA evidence is required
------------------------------------------------------------------------
SUMMARY [acceptance-criteria]: 49/49 checks passed, 0 failed
RESULT: PASS (acceptance-criteria)

========================================================================
VALIDATOR: step-01-traceability
========================================================================
      requirements defined: 498
PASS  at least one requirement is defined
PASS  exists: docs/quality/ACCEPTANCE_CRITERIA.md
PASS  exists: docs/security/SECURITY_ACCEPTANCE_CRITERIA.md
      requirement IDs cited by acceptance criteria: 498
PASS  exists: docs/product/REQUIREMENT_TRACEABILITY.md
      requirement IDs present in the traceability matrix: 160
PASS  every acceptance criterion cites a requirement that exists
      traced requirements: 498/498 (100%); orphans: 0
PASS  orphaned requirements are at most 5% (found 0%)
      CRITICAL/HIGH threats: 34
PASS  every CRITICAL and HIGH threat is referenced by an acceptance criterion
      DEL-: 35/35 traced to a criterion
      FIN-: 40/40 traced to a criterion
      FR-: 120/120 traced to a criterion
      NFR-: 50/50 traced to a criterion
      NOT-: 30/30 traced to a criterion
      OFF-: 25/25 traced to a criterion
      RPT-: 20/20 traced to a criterion
      SEC-: 68/68 traced to a criterion
      SUB-: 20/20 traced to a criterion
      TEN-: 30/30 traced to a criterion
      TRK-: 30/30 traced to a criterion
      UCL-: 30/30 traced to a criterion
------------------------------------------------------------------------
SUMMARY [step-01-traceability]: 7/7 checks passed, 0 failed
RESULT: PASS (step-01-traceability)

========================================================================
VALIDATOR: Step 2 required files
========================================================================
PASS  docs/design/DESIGN_SYSTEM.md (252 lines)
PASS  docs/design/DESIGN_PRINCIPLES.md (296 lines)
PASS  docs/design/BRAND_FOUNDATION.md (206 lines)
PASS  docs/design/COLOR_AND_CONTRAST.md (282 lines)
PASS  docs/design/TYPOGRAPHY.md (341 lines)
PASS  docs/design/SPACING_SIZING_DENSITY.md (289 lines)
PASS  docs/design/SHAPE_BORDER_ELEVATION.md (218 lines)
PASS  docs/design/ICONOGRAPHY.md (267 lines)
PASS  docs/design/MOTION_AND_REDUCED_MOTION.md (210 lines)
PASS  docs/design/RESPONSIVE_FOUNDATION.md (284 lines)
PASS  docs/design/PLATFORM_ADAPTATION.md (348 lines)
PASS  docs/design/ACCESSIBILITY.md (416 lines)
PASS  docs/design/CONTENT_DESIGN.md (423 lines)
PASS  docs/design/UX_COPY_GLOSSARY.md (332 lines)
PASS  docs/design/DATA_VISUALIZATION.md (278 lines)
PASS  docs/design/COMPONENT_CATALOG.md (2229 lines)
PASS  docs/design/COMPONENT_STATE_MATRIX.md (149 lines)
PASS  docs/design/FORM_AND_VALIDATION_PATTERNS.md (479 lines)
PASS  docs/design/DESIGN_DEBT_REGISTER.md (388 lines)
PASS  docs/design/DESIGN_DECISION_LOG.md (472 lines)
PASS  docs/design/DESIGN_TRACEABILITY.md (94 lines)
PASS  docs/ux/SCREEN_INVENTORY.md (2387 lines)
PASS  docs/ux/CRITICAL_JOURNEYS.md (679 lines)
PASS  docs/ux/UX_STATE_MODEL.md (381 lines)
PASS  docs/ux/OFFLINE_AND_SYNC_UX.md (293 lines)
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md (645 lines)
PASS  docs/ux/TENANT_AND_OUTLET_CONTEXT_UX.md (190 lines)
PASS  docs/ux/CUSTOMER_ANDROID_UX.md (203 lines)
PASS  docs/ux/OPS_ANDROID_UX.md (228 lines)
PASS  docs/ux/COURIER_UX.md (232 lines)
PASS  docs/ux/CONSOLE_WEB_UX.md (252 lines)
PASS  docs/ux/TRACKING_PORTAL_UX.md (229 lines)
PASS  docs/ux/UNCLAIMED_LAUNDRY_UX.md (228 lines)
PASS  docs/ux/USABILITY_TEST_PLAN.md (247 lines)
PASS  docs/ux/UX_ACCEPTANCE_CRITERIA.md (582 lines)
PASS  docs/ux/UX_OPEN_QUESTIONS.md (313 lines)
PASS  docs/ux/information-architecture/CUSTOMER_ANDROID_IA.md (240 lines)
PASS  docs/ux/information-architecture/OPS_ANDROID_IA.md (263 lines)
PASS  docs/ux/information-architecture/CONSOLE_WEB_IA.md (268 lines)
PASS  docs/ux/information-architecture/TRACKING_PORTAL_IA.md (231 lines)
PASS  docs/ux/information-architecture/ROLE_NAVIGATION_MATRIX.md (213 lines)
PASS  docs/ux/information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md (261 lines)
PASS  docs/ux/information-architecture/GLOBAL_SEARCH_MODEL.md (190 lines)
PASS  docs/security/DESIGN_AND_UX_THREAT_REVIEW.md (1207 lines)
PASS  docs/quality/STEP_02_DEFINITION_OF_DONE.md (340 lines)
PASS  docs/quality/STEP_02_TRACEABILITY.md (551 lines)
PASS  .claude/rules/25-design-system-foundation.md (62 lines)
PASS  .claude/rules/26-design-token-governance.md (76 lines)
PASS  .claude/rules/27-accessibility-foundation.md (79 lines)
PASS  .claude/rules/28-platform-adaptive-navigation.md (79 lines)
PASS  .claude/rules/29-ux-state-model.md (76 lines)
PASS  .claude/rules/30-content-design-and-localization.md (80 lines)
PASS  .claude/rules/31-responsive-and-device-foundation.md (78 lines)
PASS  .claude/rules/32-security-and-privacy-ux.md (149 lines)
PASS  .claude/rules/33-design-traceability.md (102 lines)
PASS  .claude/rules/34-component-and-screen-governance.md (102 lines)
PASS  .claude/rules/35-current-step-02-status.md (181 lines)
PASS  no mandated Step 2 artifact is missing (0 missing)
PASS  no mandated Step 2 artifact is a stub (0 thin)
PASS  every code fence is closed (53 files scanned)
PASS  no Step 2 document makes a claim beyond what Step 2 produced
PASS  ACCESSIBILITY.md carries the exact claim 'DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS'
PASS  ACCESSIBILITY.md carries the exact caveat 'NOT YET RUNTIME-TESTED'
PASS  apps/ contains only README or .gitkeep (0 other files)
PASS  backend/ contains only README or .gitkeep (0 other files)
PASS  packages/ contains only README or .gitkeep (0 other files)
PASS  infrastructure/ contains only README or .gitkeep (0 other files)
------------------------------------------------------------------------
SUMMARY [Step 2 required files]: 67/67 checks passed, 0 failed
RESULT: PASS (Step 2 required files)

========================================================================
VALIDATOR: design tokens
========================================================================
PASS  docs/design/tokens/README.md exists
PASS  docs/design/tokens/token-schema.json exists
PASS  docs/design/tokens/primitives.json exists
PASS  docs/design/tokens/semantic-light.json exists
PASS  docs/design/tokens/typography.json exists
PASS  docs/design/tokens/spacing.json exists
PASS  docs/design/tokens/sizing.json exists
PASS  docs/design/tokens/radius.json exists
PASS  docs/design/tokens/border.json exists
PASS  docs/design/tokens/elevation.json exists
PASS  docs/design/tokens/motion.json exists
PASS  docs/design/tokens/opacity.json exists
PASS  docs/design/tokens/breakpoints.json exists
PASS  docs/design/tokens/density.json exists
PASS  docs/design/tokens/iconography.json exists
PASS  docs/design/tokens/component-aliases.json exists
PASS  no dark-theme token file exists (dark mode is PLANNED / NOT IMPLEMENTED)
PASS  token-schema.json is valid JSON
PASS  at least 14 token files present (found 14)
PASS  docs/design/tokens/border.json is valid JSON
PASS  docs/design/tokens/border.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/border.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/border.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/border.json $meta.description is present and substantive
PASS  docs/design/tokens/border.json has a non-empty tokens object
PASS  docs/design/tokens/breakpoints.json is valid JSON
PASS  docs/design/tokens/breakpoints.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/breakpoints.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/breakpoints.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/breakpoints.json $meta.description is present and substantive
PASS  docs/design/tokens/breakpoints.json has a non-empty tokens object
PASS  docs/design/tokens/component-aliases.json is valid JSON
PASS  docs/design/tokens/component-aliases.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/component-aliases.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/component-aliases.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/component-aliases.json $meta.description is present and substantive
PASS  docs/design/tokens/component-aliases.json has a non-empty tokens object
PASS  docs/design/tokens/density.json is valid JSON
PASS  docs/design/tokens/density.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/density.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/density.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/density.json $meta.description is present and substantive
PASS  docs/design/tokens/density.json has a non-empty tokens object
PASS  docs/design/tokens/elevation.json is valid JSON
PASS  docs/design/tokens/elevation.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/elevation.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/elevation.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/elevation.json $meta.description is present and substantive
PASS  docs/design/tokens/elevation.json has a non-empty tokens object
PASS  docs/design/tokens/iconography.json is valid JSON
PASS  docs/design/tokens/iconography.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/iconography.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/iconography.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/iconography.json $meta.description is present and substantive
PASS  docs/design/tokens/iconography.json has a non-empty tokens object
PASS  docs/design/tokens/motion.json is valid JSON
PASS  docs/design/tokens/motion.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/motion.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/motion.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/motion.json $meta.description is present and substantive
PASS  docs/design/tokens/motion.json has a non-empty tokens object
PASS  docs/design/tokens/opacity.json is valid JSON
PASS  docs/design/tokens/opacity.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/opacity.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/opacity.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/opacity.json $meta.description is present and substantive
PASS  docs/design/tokens/opacity.json has a non-empty tokens object
PASS  docs/design/tokens/primitives.json is valid JSON
PASS  docs/design/tokens/primitives.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/primitives.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/primitives.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/primitives.json $meta.description is present and substantive
PASS  docs/design/tokens/primitives.json has a non-empty tokens object
PASS  docs/design/tokens/radius.json is valid JSON
PASS  docs/design/tokens/radius.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/radius.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/radius.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/radius.json $meta.description is present and substantive
PASS  docs/design/tokens/radius.json has a non-empty tokens object
PASS  docs/design/tokens/semantic-light.json is valid JSON
PASS  docs/design/tokens/semantic-light.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/semantic-light.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/semantic-light.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/semantic-light.json $meta.description is present and substantive
PASS  docs/design/tokens/semantic-light.json has a non-empty tokens object
PASS  docs/design/tokens/sizing.json is valid JSON
PASS  docs/design/tokens/sizing.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/sizing.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/sizing.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/sizing.json $meta.description is present and substantive
PASS  docs/design/tokens/sizing.json has a non-empty tokens object
PASS  docs/design/tokens/spacing.json is valid JSON
PASS  docs/design/tokens/spacing.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/spacing.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/spacing.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/spacing.json $meta.description is present and substantive
PASS  docs/design/tokens/spacing.json has a non-empty tokens object
PASS  docs/design/tokens/typography.json is valid JSON
PASS  docs/design/tokens/typography.json $meta.layer is one of ['alias', 'primitive', 'semantic']
PASS  docs/design/tokens/typography.json $meta.status is NOT IMPLEMENTED
PASS  docs/design/tokens/typography.json $meta.theme is 'light' or 'none' (no dark theme)
PASS  docs/design/tokens/typography.json $meta.description is present and substantive
PASS  docs/design/tokens/typography.json has a non-empty tokens object
PASS  no duplicate token names across all token files
      total tokens loaded: 294
PASS  every token name follows the naming convention
PASS  every token declares a valid type
PASS  every declared unit is valid
PASS  every token carries a substantive description
PASS  every token declares a valid non-empty scope
PASS  every primitive colour token carries its RGB form
PASS  semantic colour 'color.semantic.primary' is defined
PASS  semantic colour 'color.semantic.secondary' is defined
PASS  semantic colour 'color.semantic.success' is defined
PASS  semantic colour 'color.semantic.warning' is defined
PASS  semantic colour 'color.semantic.danger' is defined
PASS  semantic colour 'color.semantic.information' is defined
PASS  semantic colour 'color.semantic.neutral' is defined
PASS  semantic colour 'color.semantic.focus' is defined
PASS  semantic colour 'color.semantic.selected' is defined
PASS  semantic colour 'color.semantic.disabled' is defined
PASS  semantic colour 'color.semantic.offline' is defined
PASS  semantic colour 'color.semantic.syncing' is defined
PASS  semantic colour 'color.semantic.conflict' is defined
PASS  every primitive and semantic colour token declares intendedUsage, allowedBackground, prohibitedUsage and contrastTarget (95 tokens carry the contract)
PASS  size.touch.min is 48 (minimum touch target, non-negotiable)
PASS  space.grid.base is 4 (the spacing grid is 4px-based)
------------------------------------------------------------------------
SUMMARY [design tokens]: 126/126 checks passed, 0 failed
RESULT: PASS (design tokens)

========================================================================
VALIDATOR: design token references
========================================================================
      resolving 294 tokens
PASS  every token reference resolves (0 unresolved)
PASS  no circular token references (0 circular)
PASS  no component alias hard-codes a literal hex colour
PASS  every colour alias references the semantic layer, not a primitive
PASS  every semantic colour references a primitive colour token
PASS  every primitive token holds a literal value, not a reference
PASS  every semantic colour token has a consumer (an alias or a specification)
------------------------------------------------------------------------
SUMMARY [design token references]: 7/7 checks passed, 0 failed
RESULT: PASS (design token references)

========================================================================
VALIDATOR: colour contrast
========================================================================
      recomputing contrast for 129 colour tokens
PASS  every colour token resolves to a literal hex value
PASS  every recorded contrast figure matches its computed value (283 figures checked, 0 mismatched)
PASS  every colour token meets its declared contrast target (49 enforced, 0 missed)
PASS  the focus ring meets 3:1 against the page surface (measured 8.31:1)
PASS  no gold token claims text use without clearing 4.5:1
PASS  no design document cites a colour the tokens do not define (36 documents scanned, 0 foreign hexes)
PASS  every token name cited in the Step 2 corpus resolves (0 unresolved)
PASS  the decorative gold accents are explicitly marked decorative-exempt
      decorative gold tokens: color.gold.300, color.gold.400, color.gold.500
------------------------------------------------------------------------
SUMMARY [colour contrast]: 8/8 checks passed, 0 failed
RESULT: PASS (colour contrast)

========================================================================
VALIDATOR: typography
========================================================================
PASS  docs/design/TYPOGRAPHY.md exists
PASS  no font binary is committed (the strategy is system-first)
PASS  font.family.sans is a system-first stack beginning with system-ui
PASS  font.family.mono is defined (receipt previews need a fixed grid)
PASS  the type scale defines at least 10 sizes (found 17)
PASS  every font size has a paired line height
PASS  the type scale defines the 'display' role
PASS  the type scale defines the 'headline' role
PASS  the type scale defines the 'title' role
PASS  the type scale defines the 'body' role
PASS  the type scale defines the 'label' role
PASS  the type scale defines the 'caption' role
PASS  at least 3 font weights are defined (found 4)
PASS  letter spacing tokens are defined
PASS  font.feature.tabularNumbers is defined
PASS  font.feature.tabularNumbers enables the OpenType tnum feature
PASS  the money field alias binds tabular figures
PASS  the numeric table column alias binds tabular figures
PASS  docs/design/TYPOGRAPHY.md states the system-first font strategy
PASS  docs/design/TYPOGRAPHY.md states the tabular-figure requirement
PASS  docs/design/TYPOGRAPHY.md states the 200% text-scaling commitment
PASS  docs/design/TYPOGRAPHY.md states that no font binary is committed
PASS  every type style documents line height, weight, letter spacing, wrapping and truncation
PASS  a maximum body measure is defined for wide layouts
------------------------------------------------------------------------
SUMMARY [typography]: 24/24 checks passed, 0 failed
RESULT: PASS (typography)

========================================================================
VALIDATOR: responsive and device foundation
========================================================================
PASS  docs/design/RESPONSIVE_FOUNDATION.md exists
PASS  docs/design/SPACING_SIZING_DENSITY.md exists
PASS  docs/design/PLATFORM_ADAPTATION.md exists
PASS  breakpoint.compact.min is 0 (found 0)
PASS  breakpoint.compact.max is 599 (found 599)
PASS  breakpoint.expanded.min is 1024 (found 1024)
PASS  breakpoint.expanded.max is 1439 (found 1439)
PASS  breakpoint.medium.min is 600 (found 600)
PASS  breakpoint.medium.max is 1023 (found 1023)
PASS  breakpoint.wide.min is 1440 (found 1440)
PASS  breakpoint.minSupportedWidth is 320 (found 320) — the Public Tracking Portal must be usable on an old handset
PASS  breakpoint.consoleReferenceWidth is 1366 (found 1366)
PASS  docs/design/RESPONSIVE_FOUNDATION.md states the 320px Tracking Portal guarantee
PASS  docs/design/RESPONSIVE_FOUNDATION.md states the 1366x768 Console Web guarantee
PASS  docs/design/RESPONSIVE_FOUNDATION.md states the no-horizontal-scrolling rule for primary Console Web workflows
PASS  space.grid.base is 4 (found 4)
PASS  every spacing token is a multiple of 4px
PASS  size.touch.min is 48 (found 48)
PASS  a minimum gap between adjacent touch targets is defined
PASS  at least two control heights satisfy the 48dp touch floor (found 2: size.control.lg, size.control.xl)
PASS  density.compact.rowHeight is defined
PASS  density.standard.rowHeight is defined
PASS  density.comfortable.rowHeight is defined
PASS  compact density is explicitly restricted to pointer-only surfaces
PASS  compact density is explicitly prohibited on the Android surfaces
PASS  standard density row height (48dp) satisfies the 48dp touch floor
PASS  compact density row height (40dp) is below the touch floor, which is exactly why it is pointer-only
PASS  the Android density buckets are documented
PASS  Android density bucket 'mdpi' is documented
PASS  Android density bucket 'hdpi' is documented
PASS  Android density bucket 'xhdpi' is documented
PASS  Android density bucket 'xxhdpi' is documented
PASS  Android density bucket 'xxxhdpi' is documented
PASS  docs/design/PLATFORM_ADAPTATION.md addresses both mobile and desktop adaptation
------------------------------------------------------------------------
SUMMARY [responsive and device foundation]: 34/34 checks passed, 0 failed
RESULT: PASS (responsive and device foundation)

========================================================================
VALIDATOR: component catalog
========================================================================
PASS  docs/design/COMPONENT_CATALOG.md exists
PASS  at least 60 components carry a CMP-### ID (found 70)
      component IDs defined: 70
PASS  every mandated component is documented (0 missing)
PASS  the catalog documents the full component contract
PASS  no component specification hard-codes a literal hex colour (components name tokens)
PASS  every token the catalog cites exists (91 cited, 0 unknown)
PASS  the catalog cites requirement IDs (27 distinct)
PASS  the catalog states the focus indicator is never removed
PASS  a destructive action is never the default action
------------------------------------------------------------------------
SUMMARY [component catalog]: 9/9 checks passed, 0 failed
RESULT: PASS (component catalog)

========================================================================
VALIDATOR: component state matrix
========================================================================
PASS  docs/design/COMPONENT_STATE_MATRIX.md exists
PASS  every mandated component state is present (0 missing)
PASS  the matrix references components by CMP-### ID (70 referenced)
PASS  every catalogued component appears in the state matrix (0 uncovered)
PASS  the matrix introduces no component the catalog does not define (0 unknown)
PASS  the matrix contains component rows
PASS  no matrix cell is blank (70 rows checked, 0 rows with a blank)
PASS  the matrix resolves the focus state
PASS  no component marks its focus indicator as removed or none
PASS  the matrix uses an explicit APPLICABLE / NOT APPLICABLE vocabulary
------------------------------------------------------------------------
SUMMARY [component state matrix]: 10/10 checks passed, 0 failed
RESULT: PASS (component state matrix)

========================================================================
VALIDATOR: screen inventory
========================================================================
PASS  docs/ux/SCREEN_INVENTORY.md exists
PASS  at least 80 screens are inventoried (found 89)
PASS  Console Web has at least 23 screens (found 23)
PASS  Customer Android has at least 17 screens (found 18)
PASS  Ops Android has at least 36 screens (found 37)
PASS  Public Tracking Portal has at least 11 screens (found 11)
PASS  no screen ID is defined twice (0 duplicated)
PASS  the inventory documents every mandated screen field (0 missing)
PASS  every anchor screen is inventoried (0 missing)
PASS  the inventory cites requirement IDs (286 distinct)
PASS  every screen cites at least one requirement (89 screen blocks checked, 0 orphaned)
PASS  every screen block carries its own error, offline, permission, accessibility, masking, action, empty and loading rows (0 incomplete)
PASS  the inventory documents tenant behaviour
PASS  the inventory documents outlet context
PASS  the inventory documents permission behaviour
PASS  the inventory states that client-side visibility is not authorization
PASS  the Public Tracking Portal screens document data masking (12 of 12 blocks mention masking)
PASS  screens name the future roadmap Step that implements them
PASS  the inventory states that the screens are NOT IMPLEMENTED
------------------------------------------------------------------------
SUMMARY [screen inventory]: 19/19 checks passed, 0 failed
RESULT: PASS (screen inventory)

========================================================================
VALIDATOR: critical journeys
========================================================================
PASS  docs/ux/CRITICAL_JOURNEYS.md exists
PASS  at least 32 journeys carry a JRN-### ID (found 32)
PASS  journey covered: COD reconciliation
PASS  journey covered: H+1
PASS  journey covered: H+3
PASS  journey covered: H+7
PASS  journey covered: QC rework
PASS  journey covered: assign courier
PASS  journey covered: cashier offline
PASS  journey covered: condition and photo
PASS  journey covered: device revoked
PASS  journey covered: duplicate prevented
PASS  journey covered: external courier guest
PASS  journey covered: failed attempt
PASS  journey covered: kiloan order
PASS  journey covered: membership revoked
PASS  journey covered: mixed order
PASS  journey covered: notification failure
PASS  journey covered: partial payment
PASS  journey covered: pickup request
PASS  journey covered: pickup to delivery
PASS  journey covered: portfolio
PASS  journey covered: production queue
PASS  journey covered: ready for pickup
PASS  journey covered: receivable
PASS  journey covered: record proof
PASS  journey covered: session expired
PASS  journey covered: sync conflict
PASS  journey covered: tenant switch
PASS  journey covered: token expired
PASS  journey covered: token revoked
PASS  journey covered: tracking link
PASS  journey covered: unclaimed follow-up
PASS  journey covered: unpaid balance
PASS  every mandated journey is documented (0 missing)
PASS  at least 32 journey blocks are specified (found 32)
PASS  every journey documents trigger, actor, precondition, happy path, alternative, error path, offline path, security boundary, recovery and completion criteria (0 incomplete)
PASS  every journey links to at least one requirement (0 orphaned)
PASS  every journey links to at least one screen (0 unlinked)
PASS  every screen a journey cites exists in the inventory (0 unknown)
PASS  docs/ux/journeys/ exists
PASS  at least 8 journey flow documents exist (found 8)
PASS  at least 8 journey documents carry a Mermaid diagram (found 8)
PASS  no journey claims route optimisation, a delivery guarantee, or unlimited WhatsApp
PASS  the ageing anchor is described as the first READY_FOR_PICKUP
------------------------------------------------------------------------
SUMMARY [critical journeys]: 45/45 checks passed, 0 failed
RESULT: PASS (critical journeys)

========================================================================
VALIDATOR: information architecture and navigation
========================================================================
PASS  docs/ux/information-architecture/CONSOLE_WEB_IA.md exists (268 lines)
PASS  Console Web IA documents every mandated topic (0 missing)
PASS  Console Web IA carries a Mermaid navigation diagram
PASS  docs/ux/information-architecture/CUSTOMER_ANDROID_IA.md exists (240 lines)
PASS  Customer Android IA documents every mandated topic (0 missing)
PASS  Customer Android IA carries a Mermaid navigation diagram
PASS  docs/ux/information-architecture/OPS_ANDROID_IA.md exists (263 lines)
PASS  Ops Android IA documents every mandated topic (0 missing)
PASS  Ops Android IA carries a Mermaid navigation diagram
PASS  docs/ux/information-architecture/TRACKING_PORTAL_IA.md exists (231 lines)
PASS  Public Tracking Portal IA documents every mandated topic (0 missing)
PASS  Public Tracking Portal IA carries a Mermaid navigation diagram
PASS  docs/ux/information-architecture/ROLE_NAVIGATION_MATRIX.md exists
PASS  docs/ux/information-architecture/TENANT_OUTLET_CONTEXT_MODEL.md exists
PASS  docs/ux/information-architecture/GLOBAL_SEARCH_MODEL.md exists
PASS  all 14 roles appear in the navigation matrix (0 missing)
PASS  the IA states that client-side visibility is not authorization
PASS  the IA names Step 3 as where server-side authorization is delivered
PASS  the external courier appears in the navigation model
PASS  the external courier is explicitly confined to minimum navigation
PASS  a tenant switch is explicitly never silent
PASS  a tenant switch warns about unsynced critical operations
PASS  the model addresses stale tenant cache on switch
PASS  Console Web defines 'Portfolio Mode'
PASS  Console Web defines 'Tenant Mode'
PASS  Console Web defines 'Outlet Mode'
PASS  the context model defines the 'tenant inaccessible' state
PASS  the context model defines the 'membership revoked' state
PASS  the context model defines the 'outlet inactive' state
PASS  the context model defines the 'subscription limited' state
PASS  the global search model is tenant-scoped
PASS  global search explicitly cannot cross the tenant boundary
PASS  Ops Android uses bottom navigation where appropriate
PASS  Console Web uses a persistent side navigation or navigation rail
PASS  Console Web documents keyboard navigation
PASS  the Public Tracking Portal keeps navigation minimal
PASS  the Public Tracking Portal never requires an app install
------------------------------------------------------------------------
SUMMARY [information architecture and navigation]: 37/37 checks passed, 0 failed
RESULT: PASS (information architecture and navigation)

========================================================================
VALIDATOR: UX state model
========================================================================
PASS  docs/ux/UX_STATE_MODEL.md exists
PASS  all 20 mandated UX states are defined (0 missing)
PASS  at least 20 states carry a UXS-### ID (found 20)
PASS  at least 20 state blocks are specified (found 20)
PASS  every state documents trigger, message, visual pattern, allowed and prohibited actions, recovery, accessibility, audit and analytics (0 incomplete)
PASS  every UX state has a recovery path (0 dead ends)
PASS  'Pending Sync' and 'Syncing' are separately defined
PASS  'Syncing' and 'Synced' are separately defined
PASS  'Failed Sync' and 'Conflict' are separately defined
PASS  the model forbids a silent failure
PASS  Permission Denied does not reveal whether the record exists
PASS  Session Expired preserves unsaved work rather than discarding it
------------------------------------------------------------------------
SUMMARY [UX state model]: 12/12 checks passed, 0 failed
RESULT: PASS (UX state model)

========================================================================
VALIDATOR: content design and UX copy glossary
========================================================================
PASS  docs/design/UX_COPY_GLOSSARY.md exists
PASS  docs/design/CONTENT_DESIGN.md exists
PASS  every canonical status appears in the glossary (0 missing)
PASS  every canonical status maps to a user-facing label (0 unmapped)
PASS  'OUT_FOR_DELIVERY' maps to 'Sedang Diantar'
PASS  'PAYMENT_PENDING' maps to 'Belum Lunas'
PASS  'READY_FOR_PICKUP' maps to 'Siap Diambil'
PASS  'SYNC_CONFLICT' maps to 'Perlu Diperiksa'
PASS  the glossary distinguishes canonical identifiers from user-facing labels
PASS  the glossary states that a canonical identifier is never shown to a user
PASS  the 24-hour time convention is documented with an example
PASS  the Rupiah thousands separator convention is documented with an example
PASS  the decimal comma for weight convention is documented with an example
PASS  the timezone convention is documented
PASS  the rule that timestamps are stored in UTC is documented
PASS  the integer-Rupiah rule is restated verbatim in the content layer
PASS  floating point is named only in order to forbid it in a money path
PASS  dark patterns are named only in order to forbid them
PASS  the content design system explicitly forbids dark patterns
PASS  error copy is required to explain recovery
PASS  the generic-error anti-pattern is called out explicitly
------------------------------------------------------------------------
SUMMARY [content design and UX copy glossary]: 21/21 checks passed, 0 failed
RESULT: PASS (content design and UX copy glossary)

========================================================================
VALIDATOR: wireframes
========================================================================
PASS  docs/ux/wireframes/ exists
PASS  at least 30 wireframes exist (found 32)
PASS  docs/ux/wireframes/README.md exists
PASS  docs/ux/wireframes/README.md states the wireframes are NOT IMPLEMENTED
PASS  at least 6 'console-web' wireframes (found 6)
PASS  at least 10 'customer-android' wireframes (found 10)
PASS  at least 10 'ops-android' wireframes (found 12)
PASS  at least 4 'tracking-portal' wireframes (found 4)
PASS  every wireframe is valid XML (0 invalid)
PASS  every wireframe declares a viewBox (0 missing)
PASS  every wireframe carries the LOW-FIDELITY - NOT IMPLEMENTED label (0 missing)
PASS  every wireframe carries a screen ID (0 missing)
PASS  no wireframe contains a script, an event handler, a remote reference, an entity declaration or an embedded binary
PASS  no wireframe contains a real-looking phone number
PASS  every wireframe screen ID appears in SCREEN_INVENTORY.md (no orphan wireframes)
      wireframes cover 32 inventoried screens
------------------------------------------------------------------------
SUMMARY [wireframes]: 15/15 checks passed, 0 failed
RESULT: PASS (wireframes)

========================================================================
VALIDATOR: accessibility foundation
========================================================================
PASS  docs/design/ACCESSIBILITY.md exists
PASS  the exact target wording 'DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS' is present
PASS  the exact caveat 'NOT YET RUNTIME-TESTED' is present
PASS  the target names WCAG 2.2 specifically
PASS  the target names conformance level AA
PASS  no document claims accessibility conformance was verified or tested
PASS  docs/design/ACCESSIBILITY.md covers 'OTP accessibility'
PASS  docs/design/ACCESSIBILITY.md covers 'bottom sheet behaviour'
PASS  docs/design/ACCESSIBILITY.md covers 'chart alternatives'
PASS  docs/design/ACCESSIBILITY.md covers 'colour independence'
PASS  docs/design/ACCESSIBILITY.md covers 'contrast'
PASS  docs/design/ACCESSIBILITY.md covers 'error association'
PASS  docs/design/ACCESSIBILITY.md covers 'escape behaviour'
PASS  docs/design/ACCESSIBILITY.md covers 'focus'
PASS  docs/design/ACCESSIBILITY.md covers 'form labels'
PASS  docs/design/ACCESSIBILITY.md covers 'headings'
PASS  docs/design/ACCESSIBILITY.md covers 'keyboard navigation'
PASS  docs/design/ACCESSIBILITY.md covers 'landscape'
PASS  docs/design/ACCESSIBILITY.md covers 'modal focus'
PASS  docs/design/ACCESSIBILITY.md covers 'reading order'
PASS  docs/design/ACCESSIBILITY.md covers 'reduced motion'
PASS  docs/design/ACCESSIBILITY.md covers 'screen reader'
PASS  docs/design/ACCESSIBILITY.md covers 'status announcements'
PASS  docs/design/ACCESSIBILITY.md covers 'table navigation'
PASS  docs/design/ACCESSIBILITY.md covers 'text scaling'
PASS  docs/design/ACCESSIBILITY.md covers 'timeout warning'
PASS  docs/design/ACCESSIBILITY.md covers 'touch targets'
PASS  every mandated accessibility topic is covered (0 missing)
PASS  the 48x48 touch-target floor is stated
PASS  the 4.5:1 normal-text contrast target is stated
PASS  the 3:1 large-text and boundary target is stated
PASS  the document states that the focus indicator is never removed
PASS  the document states that status is never conveyed by colour alone
PASS  docs/ux/CUSTOMER_ANDROID_UX.md carries accessibility notes
PASS  docs/ux/OPS_ANDROID_UX.md carries accessibility notes
PASS  docs/ux/COURIER_UX.md carries accessibility notes
PASS  docs/ux/CONSOLE_WEB_UX.md carries accessibility notes
PASS  docs/ux/TRACKING_PORTAL_UX.md carries accessibility notes
PASS  COMPONENT_CATALOG.md defines a screen-reader contract
PASS  COMPONENT_CATALOG.md defines a keyboard contract
------------------------------------------------------------------------
SUMMARY [accessibility foundation]: 40/40 checks passed, 0 failed
RESULT: PASS (accessibility foundation)

========================================================================
VALIDATOR: privacy and security UX
========================================================================
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md exists
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'OTP'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'address masking'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'audit reason'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'clipboard warning'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'device revocation'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'export warning'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'external courier guest access'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'marketing consent'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'opt-out'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'payment confirmation'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'permission denied'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'phone masking'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'refund confirmation'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'retention notice'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'screenshot considerations'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'session expiry'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'step-up authentication'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'support impersonation banner'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'tenant switching'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'tracking token handling'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'transactional consent'
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md documents 'void confirmation'
PASS  every mandated privacy UX pattern is documented (0 missing)
PASS  docs/ux/TRACKING_PORTAL_UX.md exists
PASS  the tracking portal prohibition on 'full address' is stated
PASS  the tracking portal prohibition on 'internal note' is stated
PASS  the tracking portal prohibition on 'margin' is stated
PASS  the tracking portal prohibition on 'cost price' is stated
PASS  the tracking portal noindex requirement is stated
PASS  docs/ux/SECURITY_AND_PRIVACY_UX.md shows a worked masking example
PASS  an order is never marked paid from client or local state
PASS  server acknowledgement is the condition for treating an operation as final
PASS  docs/ux/OFFLINE_AND_SYNC_UX.md exists
PASS  all 9 sync states are distinguished (0 missing)
PASS  docs/ux/OFFLINE_AND_SYNC_UX.md explicitly forbids a silent sync failure
PASS  docs/ux/OFFLINE_AND_SYNC_UX.md names the client_reference idempotency key
PASS  docs/ux/COURIER_UX.md exists
PASS  docs/ux/COURIER_UX.md covers the external courier guest link
PASS  the external courier is explicitly denied the wider customer data set
PASS  route ordering is described as a suggestion, not an optimisation
PASS  no route optimisation or delivery guarantee is claimed
PASS  docs/ux/UNCLAIMED_LAUNDRY_UX.md exists
PASS  docs/ux/UNCLAIMED_LAUNDRY_UX.md documents the H+1 stage
PASS  docs/ux/UNCLAIMED_LAUNDRY_UX.md documents the H+3 stage
PASS  docs/ux/UNCLAIMED_LAUNDRY_UX.md documents the H+7 stage
PASS  docs/ux/UNCLAIMED_LAUNDRY_UX.md documents the H+14 stage
PASS  ageing is anchored to the FIRST READY_FOR_PICKUP timestamp
PASS  the ageing clock is stated never to restart
PASS  automatic disposal, sale, auction, donation or ownership transfer is explicitly prohibited
PASS  the storage fee is described as optional and not assumed active
PASS  no unmasked real-format Indonesian mobile number appears in the Step 2 corpus
------------------------------------------------------------------------
SUMMARY [privacy and security UX]: 52/52 checks passed, 0 failed
RESULT: PASS (privacy and security UX)

========================================================================
VALIDATOR: design and UX threat review
========================================================================
PASS  docs/security/DESIGN_AND_UX_THREAT_REVIEW.md exists
PASS  at least 30 findings carry a DUX-### ID (found 36)
PASS  severity 'CRITICAL' is used
PASS  severity 'HIGH' is used
PASS  severity 'MEDIUM' is used
PASS  severity 'LOW' is used
PASS  severity 'INFORMATIONAL' is used
PASS  the review specifies at least 30 findings (found 36)
PASS  no CRITICAL finding is open (0 open)
PASS  no HIGH finding is open (0 open)
PASS  every finding carries a status (0 unresolved)
PASS  every finding carries a severity (0 unrated)
PASS  every CRITICAL and HIGH finding carries a mitigation (0 without)
PASS  every mandated review area is covered (0 missing)
PASS  the review never describes itself as independent peer review
PASS  the review states that governance is single-maintainer
PASS  the review states that independent human approval is ABSENT
PASS  the review uses the mandated internal re-verification wording
PASS  severity is argued from impact and likelihood
PASS  residual risk is recorded
------------------------------------------------------------------------
SUMMARY [design and UX threat review]: 20/20 checks passed, 0 failed
RESULT: PASS (design and UX threat review)

========================================================================
VALIDATOR: UX requirement classification
========================================================================
PASS  docs/quality/STEP_02_TRACEABILITY.md exists
PASS  the registry still holds 498 requirement IDs (found 498)
PASS  the DEL series still holds 35 requirements (found 35)
PASS  the FIN series still holds 40 requirements (found 40)
PASS  the FR series still holds 120 requirements (found 120)
PASS  the NFR series still holds 50 requirements (found 50)
PASS  the NOT series still holds 30 requirements (found 30)
PASS  the OFF series still holds 25 requirements (found 25)
PASS  the RPT series still holds 20 requirements (found 20)
PASS  the SEC series still holds 68 requirements (found 68)
PASS  the SUB series still holds 20 requirements (found 20)
PASS  the TEN series still holds 30 requirements (found 30)
PASS  the TRK series still holds 30 requirements (found 30)
PASS  the UCL series still holds 30 requirements (found 30)
PASS  every classification uses the approved vocabulary ['UI-DIRECT', 'UI-INDIRECT', 'NON-UI', 'DEFERRED-UX'] (0 malformed)
PASS  every requirement in the registry is classified (498 classified, 0 unclassified)
PASS  the matrix invents no requirement ID (0 invented)
PASS  every security requirement remains visible in the mapping (68/68 present, 0 dropped)
PASS  every tenancy requirement remains visible in the mapping (30/30)
PASS  every financial requirement remains visible in the mapping (40/40)
PASS  every offline requirement remains visible in the mapping (25/25)
PASS  every tracking requirement remains visible in the mapping (30/30)
PASS  no UI-critical requirement is orphaned from a screen (440 UI-bearing, 0 orphaned)
PASS  no UI-critical requirement is orphaned from a journey (0 orphaned)
PASS  every screen the matrix cites exists (0 unknown)
PASS  every journey the matrix cites exists (0 unknown)
PASS  every DEFERRED-UX requirement records a rationale and an owner step
PASS  the matrix states that every requirement is NOT IMPLEMENTED
PASS  the matrix states that a mapping is not evidence of satisfaction
------------------------------------------------------------------------
SUMMARY [UX requirement classification]: 29/29 checks passed, 0 failed
RESULT: PASS (UX requirement classification)

========================================================================
VALIDATOR: design traceability
========================================================================
PASS  docs/design/DESIGN_TRACEABILITY.md exists
PASS  docs/ux/SCREEN_INVENTORY.md exists
PASS  docs/ux/CRITICAL_JOURNEYS.md exists
PASS  docs/design/COMPONENT_CATALOG.md exists
PASS  docs/ux/UX_STATE_MODEL.md exists
PASS  docs/security/DESIGN_AND_UX_THREAT_REVIEW.md exists
PASS  docs/quality/STEP_02_TRACEABILITY.md exists
      screens=89 journeys=32 components=70 states=20 findings=36
PASS  every screen cited by a journey exists (0 unknown)
PASS  every hard-gate screen appears in at least one journey (19 hard-gate screens, 0 unwalked)
      journey coverage: 19/19 requirement-bearing screens are walked by at least one journey
PASS  every component appears in the state matrix (0 uncovered)
PASS  the component catalog names design tokens (91 distinct)
PASS  every token a component names exists (0 unknown)
PASS  every semantic token has a consumer (0 orphaned)
PASS  every UX state has a recovery path (0 without)
PASS  the threat review contains finding blocks
PASS  every threat finding carries a UX mitigation (0 without)
PASS  every UX mitigation traces to a requirement (0 untraced)
PASS  Step 2 invents no requirement ID (0 invented)
PASS  the traceability document covers 'requirement'
PASS  the traceability document covers 'journey'
PASS  the traceability document covers 'screen'
PASS  the traceability document covers 'component'
PASS  the traceability document covers 'token'
PASS  the traceability document covers 'threat'
PASS  the traceability document covers 'state'
PASS  the traceability document states its maintenance rule
PASS  the traceability document restates NOT IMPLEMENTED
------------------------------------------------------------------------
SUMMARY [design traceability]: 27/27 checks passed, 0 failed
RESULT: PASS (design traceability)

========================================================================
VALIDATOR: Step 2 application rules
========================================================================
PASS  .claude/rules/25-design-system-foundation.md exists (62 lines)
PASS  .claude/rules/25-design-system-foundation.md has a 'Purpose' section
PASS  .claude/rules/25-design-system-foundation.md has a 'Violation handling' section
PASS  .claude/rules/26-design-token-governance.md exists (76 lines)
PASS  .claude/rules/26-design-token-governance.md has a 'Purpose' section
PASS  .claude/rules/26-design-token-governance.md has a 'Violation handling' section
PASS  .claude/rules/27-accessibility-foundation.md exists (79 lines)
PASS  .claude/rules/27-accessibility-foundation.md has a 'Purpose' section
PASS  .claude/rules/27-accessibility-foundation.md has a 'Violation handling' section
PASS  .claude/rules/28-platform-adaptive-navigation.md exists (79 lines)
PASS  .claude/rules/28-platform-adaptive-navigation.md has a 'Purpose' section
PASS  .claude/rules/28-platform-adaptive-navigation.md has a 'Violation handling' section
PASS  .claude/rules/29-ux-state-model.md exists (76 lines)
PASS  .claude/rules/29-ux-state-model.md has a 'Purpose' section
PASS  .claude/rules/29-ux-state-model.md has a 'Violation handling' section
PASS  .claude/rules/30-content-design-and-localization.md exists (80 lines)
PASS  .claude/rules/30-content-design-and-localization.md has a 'Purpose' section
PASS  .claude/rules/30-content-design-and-localization.md has a 'Violation handling' section
PASS  .claude/rules/31-responsive-and-device-foundation.md exists (78 lines)
PASS  .claude/rules/31-responsive-and-device-foundation.md has a 'Purpose' section
PASS  .claude/rules/31-responsive-and-device-foundation.md has a 'Violation handling' section
PASS  .claude/rules/32-security-and-privacy-ux.md exists (149 lines)
PASS  .claude/rules/32-security-and-privacy-ux.md has a 'Purpose' section
PASS  .claude/rules/32-security-and-privacy-ux.md has a 'Violation handling' section
PASS  .claude/rules/33-design-traceability.md exists (102 lines)
PASS  .claude/rules/33-design-traceability.md has a 'Purpose' section
PASS  .claude/rules/33-design-traceability.md has a 'Violation handling' section
PASS  .claude/rules/34-component-and-screen-governance.md exists (102 lines)
PASS  .claude/rules/34-component-and-screen-governance.md has a 'Purpose' section
PASS  .claude/rules/34-component-and-screen-governance.md has a 'Violation handling' section
PASS  .claude/rules/35-current-step-02-status.md exists (181 lines)
PASS  .claude/rules/35-current-step-02-status.md has a 'Purpose' section
PASS  .claude/rules/35-current-step-02-status.md has a 'Violation handling' section
PASS  constraint 1 is locked
PASS  constraint 2 is locked
PASS  constraint 3 is locked
PASS  constraint 4 is locked
PASS  constraint 5 is locked
PASS  constraint 6 is locked
PASS  constraint 7 is locked
PASS  constraint 8 is locked
PASS  constraint 9 is locked
PASS  constraint 10 is locked
PASS  constraint 11 is locked
PASS  constraint 12 is locked
PASS  constraint 13 is locked
PASS  constraint 14 is locked
PASS  constraint 15 is locked
PASS  constraint 16 is locked
PASS  constraint 17 is locked
PASS  constraint 18 is locked
PASS  constraint 19 is locked
PASS  constraint 20 is locked
PASS  constraint 21 is locked
PASS  constraint 22 is locked
PASS  constraint 23 is locked
PASS  constraint 24 is locked
PASS  constraint 25 is locked
PASS  constraint 26 is locked
PASS  constraint 27 is locked
PASS  constraint 28 is locked
PASS  constraint 29 is locked
PASS  constraint 30 is locked
PASS  constraint 31 is locked
PASS  constraint 32 is locked
PASS  constraint 33 is locked
PASS  constraint 34 is locked
PASS  constraint 35 is locked
PASS  all 35 Step 2 constraints are locked (0 unlocked: [])
PASS  CLAUDE.md exists
PASS  CLAUDE.md references every Step 2 rule (0 unreferenced)
PASS  rule 35 records Step 2 as IN PROGRESS
PASS  rule 35 records the later Steps as PLANNED
PASS  rule 35 records 'Backend runtime' as ABSENT
PASS  rule 35 records 'Flutter workspace' as ABSENT
PASS  rule 35 records application CI as NOT APPLICABLE
PASS  rule 35 records UAT as NOT STARTED
PASS  rule 35 states that documentation is not implementation
PASS  rule 35 does not self-declare GO for Step 2
PASS  rule 35 states that GO is owner-conferred
PASS  no Step 2 rule invents a status word outside the approved vocabulary
------------------------------------------------------------------------
SUMMARY [Step 2 application rules]: 81/81 checks passed, 0 failed
RESULT: PASS (Step 2 application rules)

========================================================================
VALIDATOR: mermaid-blocks
========================================================================
      checking 226 markdown files
PASS  every markdown file has balanced code fences
PASS  no Mermaid block is empty
PASS  every Mermaid block declares a recognised diagram type
      63 Mermaid diagrams across 36 files
PASS  the corpus contains at least one Mermaid diagram
      NOTE: structural validation only. No diagram was rendered, and no claim of successful visual rendering is made.
------------------------------------------------------------------------
SUMMARY [mermaid-blocks]: 4/4 checks passed, 0 failed
RESULT: PASS (mermaid-blocks)

========================================================================
VALIDATOR: public-repository-safety
========================================================================
      scanning 353 tracked files
PASS  no real-looking Indonesian mobile number
PASS  no private key block
PASS  no AWS access key id
PASS  no Google API key
PASS  no Slack token
PASS  no GitHub token
PASS  no Stripe secret key
PASS  no JSON Web Token
PASS  no assigned secret literal
PASS  no database connection string with credentials
PASS  no .env file is committed
PASS  no database dump or backup is committed
PASS  the repository is never described as private
PASS  PUBLIC visibility is recorded as an accepted deviation (DEC-0016)
------------------------------------------------------------------------
SUMMARY [public-repository-safety]: 14/14 checks passed, 0 failed
RESULT: PASS (public-repository-safety)

========================================================================
VALIDATOR: released tag immutability
========================================================================
PASS  aish-laundry-step-00-master-source-governance-v1.0.0-go is annotated and unmoved (8494bc8543b9301351da6055337832597f1f2d9f)
PASS  aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go is annotated and unmoved (4eadbc73f8bacdc9cd2acfcc62280ac932116089)

########################################################################
# STEP 2 GATE SUMMARY
########################################################################
GATE                       RESULT EXIT
-------------------------- ------ ----
required-files             PASS   0
master-source              PASS   0
decisions                  PASS   0
roadmap                    PASS   0
status                     PASS   0
pricing                    PASS   0
rules-traceability         PASS   0
no-runtime                 PASS   0
markdown-links             PASS   0
secrets                    PASS   0
destructive-guard          PASS   0
product-requirements       PASS   0
requirement-ids            PASS   0
personas                   PASS   0
use-cases                  PASS   0
domain-glossary            PASS   0
bounded-contexts           PASS   0
aggregates                 PASS   0
domain-invariants          PASS   0
domain-events              PASS   0
state-machines             PASS   0
tenant-boundaries          PASS   0
money-rules                PASS   0
tracking-rules             PASS   0
delivery-rules             PASS   0
unclaimed-laundry          PASS   0
threat-model               PASS   0
data-classification        PASS   0
acceptance-criteria        PASS   0
step-01-traceability       PASS   0
design-required-files      PASS   0
design-tokens              PASS   0
token-references           PASS   0
colour-contrast            PASS   0
typography                 PASS   0
breakpoints                PASS   0
component-catalog          PASS   0
component-states           PASS   0
screen-inventory           PASS   0
journeys                   PASS   0
navigation                 PASS   0
ux-states                  PASS   0
content-glossary           PASS   0
wireframes                 PASS   0
accessibility              PASS   0
privacy-ux                 PASS   0
design-threat-review       PASS   0
ux-classification          PASS   0
design-traceability        PASS   0
step-02-rules              PASS   0
mermaid-blocks             PASS   0
public-repo-safety         PASS   0
tag-immutability           PASS   0
------------------------------------------------------------------------
GATES PASSED: 53 / 53

Scope note: Step 2 is documentation only. These are governance validators.
There are no application unit, widget, integration, or end-to-end tests,
because no application exists. Application CI is NOT APPLICABLE.
A passing gate proves a document satisfies a rule. It never proves a
feature works, a screen renders, or an accessibility criterion was met.
STEP 2 VERIFICATION: PASS
```
