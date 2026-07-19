# Corpus Inventory — Step 1

**Exact commit SHA:** `663f432d68eeaec4a7cd7d5f7b0d477bd9fa2948`  
**Timestamp:** 2026-07-19 17:37:15 WIB

Every document below is **documentation only**. Nothing here is implemented.
All product features are `NOT IMPLEMENTED`; backend runtime and Flutter workspace are `ABSENT`;
application CI is `NOT APPLICABLE`; UAT is `NOT STARTED`.

---

## `docs/product/`

| Document | Lines | Present |
|---|---:|---|
| `PRODUCT_REQUIREMENTS.md` | 1146 | yes |
| `MVP_SCOPE.md` | 201 | yes |
| `PERSONAS.md` | 463 | yes |
| `JOBS_TO_BE_DONE.md` | 241 | yes |
| `USER_JOURNEYS.md` | 401 | yes |
| `OPERATIONAL_JOURNEYS.md` | 469 | yes |
| `USE_CASE_CATALOG.md` | 331 | yes |
| `SUCCESS_METRICS.md` | 196 | yes |
| `ASSUMPTIONS_AND_OPEN_QUESTIONS.md` | 155 | yes |
| `REQUIREMENT_TRACEABILITY.md` | 434 | yes |

## `docs/domain/`

| Document | Lines | Present |
|---|---:|---|
| `DOMAIN_GLOSSARY.md` | 261 | yes |
| `BOUNDED_CONTEXTS.md` | 567 | yes |
| `CONTEXT_MAP.md` | 250 | yes |
| `AGGREGATE_CATALOG.md` | 976 | yes |
| `ENTITY_AND_VALUE_OBJECT_CATALOG.md` | 258 | yes |
| `DOMAIN_INVARIANTS.md` | 319 | yes |
| `DOMAIN_EVENTS.md` | 373 | yes |
| `COMMANDS_AND_POLICIES.md` | 303 | yes |
| `TENANT_BOUNDARIES.md` | 271 | yes |
| `DATA_OWNERSHIP.md` | 168 | yes |
| `ORDER_DOMAIN.md` | 166 | yes |
| `PRODUCTION_AND_QC_DOMAIN.md` | 161 | yes |
| `PAYMENT_DOMAIN.md` | 232 | yes |
| `TRACKING_DOMAIN.md` | 171 | yes |
| `PICKUP_DELIVERY_DOMAIN.md` | 209 | yes |
| `UNCLAIMED_LAUNDRY_DOMAIN.md` | 197 | yes |
| `NOTIFICATION_DOMAIN.md` | 189 | yes |
| `OFFLINE_SYNC_DOMAIN.md` | 186 | yes |
| `SUBSCRIPTION_DOMAIN.md` | 149 | yes |

## `docs/state-machines/`

| Document | Lines | Present |
|---|---:|---|
| `ORDER_STATE_MACHINE.md` | 250 | yes |
| `PAYMENT_STATE_MACHINE.md` | 185 | yes |
| `REFUND_STATE_MACHINE.md` | 155 | yes |
| `PRODUCTION_STATE_MACHINE.md` | 202 | yes |
| `QUALITY_CONTROL_STATE_MACHINE.md` | 203 | yes |
| `TRACKING_ACCESS_LIFECYCLE.md` | 265 | yes |
| `PICKUP_DELIVERY_STATE_MACHINE.md` | 300 | yes |
| `COURIER_SETTLEMENT_STATE_MACHINE.md` | 249 | yes |
| `UNCLAIMED_LAUNDRY_STATE_MACHINE.md` | 306 | yes |
| `SUBSCRIPTION_STATE_MACHINE.md` | 215 | yes |

## `docs/security/`

| Document | Lines | Present |
|---|---:|---|
| `INITIAL_THREAT_MODEL.md` | 981 | yes |
| `ABUSE_CASES.md` | 256 | yes |
| `DATA_CLASSIFICATION.md` | 182 | yes |
| `TRUST_BOUNDARIES.md` | 239 | yes |
| `PRIVACY_REQUIREMENTS.md` | 185 | yes |
| `SECURITY_ACCEPTANCE_CRITERIA.md` | 374 | yes |

## `docs/quality/`

| Document | Lines | Present |
|---|---:|---|
| `NON_FUNCTIONAL_REQUIREMENTS.md` | 244 | yes |
| `ACCEPTANCE_CRITERIA.md` | 985 | yes |
| `STEP_01_DEFINITION_OF_DONE.md` | 235 | yes |

---

## Requirement identifiers

Each defined exactly once in its authoritative register, and never reused.

| Series | Defined | Authoritative register |
|---|---:|---|
| `FR-` | 120 | `docs/product/PRODUCT_REQUIREMENTS.md` |
| `RPT-` | 20 | `docs/product/PRODUCT_REQUIREMENTS.md` |
| `SUB-` | 20 | `docs/product/PRODUCT_REQUIREMENTS.md` |
| `SEC-` | 68 | `docs/security/SECURITY_ACCEPTANCE_CRITERIA.md` |
| `NFR-` | 50 | `docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md` |
| `TEN-` | 30 | `docs/domain/DOMAIN_INVARIANTS.md` |
| `FIN-` | 40 | `docs/domain/DOMAIN_INVARIANTS.md` |
| `OFF-` | 25 | `docs/domain/DOMAIN_INVARIANTS.md` |
| `TRK-` | 30 | `docs/domain/DOMAIN_INVARIANTS.md` |
| `DEL-` | 35 | `docs/domain/DOMAIN_INVARIANTS.md` |
| `UCL-` | 30 | `docs/domain/DOMAIN_INVARIANTS.md` |
| `NOT-` | 30 | `docs/domain/DOMAIN_INVARIANTS.md` |
| **Total** | **498** | |

## Threat model

| Severity | Count |
|---|---:|
| `CRITICAL` | 11 |
| `HIGH` | 23 |
| `MEDIUM` | 14 |
| `LOW` | 1 |
| `INFORMATIONAL` | 1 |
| **Total** | **50** |

Every `CRITICAL` and `HIGH` threat carries an explicit mitigation **and** is referenced by an
acceptance criterion. Both are enforced by validators, not asserted.

## Governance artefacts

| Artefact | Count |
|---|---:|
| Rule files (`.claude/rules/`) | 25 |
| Decision records (`docs/decisions/`) | 16 |
| Step 1 documents | 48 |
| Step 1 documentation lines | 15454 |

## What is NOT in this inventory

- No application source file of any kind.
- No dependency manifest: no `pubspec.yaml`, `composer.json`, or `package.json`.
- No database schema and no migration.
- No API endpoint, screen, or deployment artefact.
- No test file, because there is no application to test.

`scripts/validate-no-runtime.py` asserts this at every run and is bound to the same SHA.
