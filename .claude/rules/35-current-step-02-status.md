# Rule 35 — Current Step 2 Status

## Purpose

To hold one honest statement of what Step 2 is, what it produces, and — most importantly — what it does
**not** produce, so that a design system on paper is never mistaken for a design system in a product.

Canonical status: [`../../docs/STATUS.md`](../../docs/STATUS.md). Master Source version **1.3.0**.

## Status snapshot

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | **GO** (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 1 — Product Requirement and Domain Model | **GO** (owner-conferred 19 July 2026, with a recorded deviation) |
| Step 2 — Design System and UX Foundation | **GO** (owner-conferred 20 July 2026, with four recorded deviations) |
| Steps 3–14 | **PLANNED** |
| All product features | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Database | **ABSENT** |
| Deployment | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

Step 2 carries **`GO`**, conferred by the repository owner on 20 July 2026 against exact-SHA evidence and
tagged `aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go` at
`47c07d360e8802fd78f61d41435cae3f28313137`. **`GO` is conferred by the repository owner and is never
self-declared by an agent** (Rule 01); while a Step's pull request is open, the maximum permissible status
is `IN PROGRESS`, `TESTED`, or `WATCH`.

**Four deviations were accepted at Step 2 `GO`**, and none of them is silent: PUBLIC repository visibility
(DEC-0016), single-maintainer governance, no independent human review (DEC-0017), and design-only
accessibility that is **not yet runtime-tested** (DEC-0021). **The compensating controls are not equivalent
to independent peer review or to runtime accessibility testing.** Step 2 `GO` means every gate passed with
those four deviations deliberately taken and documented — it does not mean the deviated requirements were
met.

## What Step 2 produces

**Documentation only.** Specifically: the visual language and its palette roles; typography, spacing,
elevation, and iconography specifications; the design token inventory and its governance; the accessibility
foundation; platform-adaptive navigation models; the UX state model; content design and Bahasa Indonesia
terminology discipline; responsive and device constraints; security and privacy UX patterns; the design and
UX threat review; the component inventory and screen specifications; design traceability; the Step 2
Definition of Done; the governance rules 25–35; Step 2 validators; and an exact-SHA evidence pack.

## What Step 2 does NOT produce

Step 2 creates **no runtime**. It is forbidden in this Step to run or create:

`flutter create` · `dart create` · `laravel new` · `composer create-project` · `npm create` ·
`pubspec.yaml` · `composer.json` · `package.json` · `artisan` · a theme file · a widget · a component
library · a storybook · a design-token build artefact · a CSS or Dart constant file · database schema ·
migrations · REST API runtime · authentication · tenant middleware · Android project · Flutter Web project ·
payment integration · WhatsApp integration · tracking portal runtime · pickup-delivery runtime · queue
worker · Redis runtime · Docker application stack · any deployment.

**Flutter and backend remain `ABSENT` in Step 2.** Runtime folders (`apps/`, `backend/`, `packages/`,
`infrastructure/`) continue to contain only a `README` or a `.gitkeep`. An empty folder is never an
implemented feature.

## The claim that must never be made

**Documentation is not implementation.** A design token, a wireframe, a component specification, a
navigation model, an accessibility requirement, or a UX pattern describes an obligation — never an
achievement.

Specifically, and without softening:

- **A wireframe is not a screen.**
- **A design token is not a theme.**
- **An accessibility requirement is not a passed audit.** The permitted wording is
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**
- A component specification is not a built component.
- A UX mitigation recorded in the design threat review is not an enforced control.
- A traceability row is not a test result.

It is a false claim under Rule 01 to say or imply that Step 2 delivered a working screen, a theme, a
component library, a build, a deployment, an application CI pipeline, an accessibility audit, a performance
measurement, or any UAT result.

The only executable verification in Step 2 is the governance validator set
(`bash scripts/verify-step-02.sh`), and its output is bound to an **exact commit SHA** (DEC-0013).

## Governance, evidence, and public-repository facts

1. **Exact-SHA evidence is mandatory.** Every verification claim records the full 40-character commit SHA,
   the exact command, the captured output, the timestamp in Asia/Jakarta, and the environment. Evidence
   produced at one SHA never carries over to another (Rule 01, DEC-0013).
2. **The `GO` tag is immutable.** Tags are annotated, are never moved, deleted, or re-pointed, and a
   released tag is never reused. A wrong tag is corrected by publishing a new one and documenting why
   (Rule 11).
3. **Public repository safety is mandatory.** This repository is **PUBLIC**, an accepted deviation from a
   canonical desired **PRIVATE** (AMENDMENT-0001, DEC-0016). It is never described as private, and PUBLIC is
   never presented as the desired end state. Every example datum in every Step 2 artefact is fictional and
   recognisably so. Deletion is not remediation (Rule 23).
4. **Compensating controls are not independent review.** Governance operates in single-maintainer mode and
   independent human approval is **`ABSENT`** — a standing accepted deviation recorded in DEC-0017. The
   ruleset, exact-SHA CI, deterministic validators, adversarial validator testing, and recorded internal
   re-verification are **load-bearing, not supplementary**, and they are **not equivalent** to a second
   human reader. **Internal re-verification is never described as independent peer review, and a self-review
   is never described as an approval** (Rule 23).
5. **Adversarial validator testing is mandatory** before a Step 2 validator is relied upon as a gate
   (Rule 33).
6. **Graphify relationship review is mandatory** before Step 2 closes, and diagram tooling renders only
   already-approved documentation — it never introduces a product fact (Rule 33).

## Step boundary

- **Step 3 must not begin before Step 2 has `GO`.** Runtime, authentication, tenancy implementation, RBAC,
  database schema, and the Flutter workspace belong to **Step 3** and are not pulled forward. Design-system
  *implementation* is Step 3 work; Step 2 stops at the specification.
- Work belonging to Step 4 and beyond — master data, POS, production, tracking, pickup and delivery,
  unclaimed laundry, reporting, subscription — is not pulled forward either.
- Step numbers are locked and are never reused, renumbered, swapped, merged, or split without an accepted
  decision record (Master Source §24).

## Rule coverage index — the thirty-five Step 2 locks

Every lock below is held by at least one rule. This index exists so that a reader can check coverage without
reading all eleven files.

| # | Lock | Held by |
|---|---|---|
| 1 | Master Source is highest decision authority | Rule 25 |
| 2 | PRD is authoritative requirement source | Rule 25 |
| 3 | Requirement IDs mandatory | Rule 33 |
| 4 | Design tokens never hardcoded without semantic mapping | Rule 26 |
| 5 | Light theme is MVP | Rule 25, Rule 26 |
| 6 | Dark mode deferred | Rule 25, Rule 26 |
| 7 | Accessibility is a hard gate | Rule 27 |
| 8 | Status never depends on colour alone | Rule 27, Rule 30 |
| 9 | Touch target minimum 48×48 | Rule 27, Rule 31 |
| 10 | Tenant and outlet context must be visible | Rule 28, Rule 32 |
| 11 | Offline and sync states must be honest | Rule 29 |
| 12 | Payment success never claimed from client state | Rule 29, Rule 32 |
| 13 | Customer data must be masked | Rule 32 |
| 14 | Public tracking projection is separate | Rule 32 |
| 15 | External courier sees minimum data only | Rule 32, Rule 28 |
| 16 | Destructive action requires confirmation | Rule 32 |
| 17 | Financial action requires a reason where relevant | Rule 32 |
| 18 | Components must have a state contract | Rule 34, Rule 29 |
| 19 | Components must have an accessibility contract | Rule 34, Rule 27 |
| 20 | Critical screens must have error and recovery states | Rule 34, Rule 29 |
| 21 | All requirements must carry a UX classification | Rule 33 |
| 22 | All screens must carry requirement references | Rule 33, Rule 34 |
| 23 | New screens must define tenant and permission behaviour | Rule 28, Rule 34 |
| 24 | UX copy follows the Bahasa Indonesia glossary | Rule 30 |
| 25 | Final logo must never be fabricated | Rule 25 |
| 26 | Wireframes must never be claimed implemented | Rule 34, Rule 35 |
| 27 | Flutter and backend remain `ABSENT` in Step 2 | Rule 35 |
| 28 | Step 3 must not begin before Step 2 `GO` | Rule 35 |
| 29 | Exact-SHA evidence mandatory | Rule 35, Rule 33 |
| 30 | `GO` tag immutable | Rule 35 |
| 31 | Public repository safety mandatory | Rule 35, Rule 30 |
| 32 | Compensating controls are not independent review | Rule 35 |
| 33 | Adversarial validator testing mandatory | Rule 33, Rule 35 |
| 34 | Graphify relationship review mandatory | Rule 33, Rule 35 |
| 35 | Design changes must update traceability | Rule 33 |

## Maintenance

1. This snapshot is updated only when reality changes, alongside [`../../docs/STATUS.md`](../../docs/STATUS.md).
2. Statuses move forward on **exact-SHA evidence** only (Rule 01).
3. Use the approved status vocabulary only: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`, `NOT IMPLEMENTED`,
   `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`, `GO`. No synonyms, no softening adjectives.
4. If another document contradicts this snapshot, the other document is wrong — unless the Master Source
   itself has moved, in which case this file is updated to match it.

## Violation handling

- **Any claim that a Step 2 artefact is an implemented screen, theme, component, or tested surface** —
  correct it immediately and visibly, and state that the earlier claim was wrong (Rule 01).
- **A wireframe reported as a screen, a token set reported as a theme, or an accessibility requirement
  reported as a passed audit** — correct it; the permitted accessibility wording is
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**
- **Any runtime artefact created during Step 2** — remove it and report the scope breach.
- **Step 3 work performed before Step 2 `GO`** — stop, revert the forward-leaked work, and report.
- **`GO` written for Step 2 by an agent while its pull request is open** — revert the wording; `GO` is the
  owner's to confer.
- **A status advanced without exact-SHA evidence** — revert the advancement.
- **A released tag moved, deleted, or re-pointed** — record the incident, restore the original target if
  possible, and publish a corrective tag (Rule 11).
- **This repository described as private, or PUBLIC presented as the desired end state** — correct it
  immediately, citing AMENDMENT-0001 and DEC-0016 (Rule 23).
- **Internal re-verification reported as independent peer review or as an approval** — correct the wording;
  this is a false claim under Rule 01, not a stylistic preference (Rule 23).
- **A validator relied upon as a gate without adversarial testing** — the assurance is overstated; test it
  against broken input before reporting it (Rule 33).
