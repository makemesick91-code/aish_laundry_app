# Step 2 — Definition of Done

**Step:** 2 — Design System and UX Foundation
**Status:** `IN PROGRESS`
**Master Source version:** 1.3.0 · baseline 19 July 2026

`GO` is conferred by the **repository owner** and is **never self-declared by an agent**. While the Step 2
pull request is open, the maximum permissible status is `IN PROGRESS`, `TESTED`, or `WATCH`.

---

## 1. What Step 2 delivers

**Documentation only.** Specifically:

- The visual language and its palette roles — white, soft blue, dark blue, and a restrained gold accent —
  reproduced from the Master Source rather than restated from memory.
- Typography, spacing, elevation, and iconography specifications, tuned for low-end Android hardware and
  bright shop lighting.
- The design token inventory and its governance: primitive tokens, semantic tokens, and the rule that
  components reference semantic tokens only.
- The accessibility foundation: contrast, touch target, font scaling, focus order, and assistive-technology
  semantics.
- Platform-adaptive navigation models for Customer Android, Ops Android, Console Web, and the public
  tracking portal.
- The UX state model, including offline, pending-sync, stale, error, and denied states.
- Content design and Bahasa Indonesia terminology discipline, bound to the domain glossary.
- Responsive and device constraints.
- Security and privacy UX patterns — [`../ux/SECURITY_AND_PRIVACY_UX.md`](../ux/SECURITY_AND_PRIVACY_UX.md).
- The design and UX threat review — [`../security/DESIGN_AND_UX_THREAT_REVIEW.md`](../security/DESIGN_AND_UX_THREAT_REVIEW.md).
- The component inventory and screen specifications.
- Design traceability.
- This Definition of Done, the governance rules 25–35, the Step 2 validators, and an exact-SHA evidence
  pack.

---

## 2. What Step 2 does NOT deliver — stated plainly

Step 2 creates **no runtime**. The following remain true at the close of Step 2:

| Item | Status |
| --- | --- |
| All product features | `NOT IMPLEMENTED` |
| Backend runtime | `ABSENT` |
| Flutter workspace | `ABSENT` |
| Database | `ABSENT` |
| Deployment | `ABSENT` |
| Application CI | `NOT APPLICABLE` |
| UAT | `NOT STARTED` |
| Dark mode | `PLANNED` |

Forbidden in this Step: `flutter create` · `dart create` · `laravel new` · `composer create-project` ·
`npm create` · `pubspec.yaml` · `composer.json` · `package.json` · `artisan` · a theme file · a widget · a
component library · a storybook · a design-token build artefact · a CSS or Dart constant file · database
schema · migrations · REST API runtime · authentication · tenant middleware · Android or Flutter Web
project · payment, WhatsApp, tracking, pickup-delivery, or queue runtime · Docker application stack · any
deployment.

Runtime folders (`apps/`, `backend/`, `packages/`, `infrastructure/`) contain only a `README` or a
`.gitkeep`.

**The claims that must never be made:**

- **Documentation is not implementation.**
- **A wireframe is not a screen.**
- **A design token is not a theme.**
- **An accessibility requirement is not a passed audit.** The permitted wording is
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**
- A UX mitigation is not an enforced control, and a traceability row is not a test result.

---

## 3. Gates

A gate is either met or it is not. There is no partial credit and no "substantially met".

### Gate 1 — Master Source alignment

- [ ] Every design decision traces to `docs/MASTER_SOURCE.md`; none contradicts it.
- [ ] The palette, tone, surface list, and roadmap numbering are reproduced from the Master Source, not
      restated from memory.
- [ ] Pricing text appearing anywhere in Step 2 artefacts matches the Master Source **character for
      character**.
- [ ] No product decision was invented. Genuine gaps are recorded as open questions and escalated to the
      owner, never closed with a placeholder that reads as a decision.
- [ ] Any Master Source change in this Step carries a version bump, a tool-regenerated checksum, and a
      decision record — or the Master Source was not changed at all.

### Gate 2 — Status honesty

- [ ] Step 2 is stated as `IN PROGRESS`; Steps 3–14 remain `PLANNED`; Steps 0 and 1 remain `GO` with their
      recorded deviations.
- [ ] Only the approved status vocabulary is used: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`,
      `NOT IMPLEMENTED`, `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`, `GO`. No synonyms, no softening
      adjectives.
- [ ] **`GO` is not written for Step 2 anywhere** — not in a document, a rule file, a commit message, a
      pull request description, or an evidence pack.
- [ ] No hedged claim ("should pass", "effectively done", "essentially working") appears anywhere.

### Gate 3 — Exact-SHA evidence

- [ ] `bash scripts/verify-step-02.sh` executed, with output captured **unedited**.
- [ ] Evidence records the full **40-character** commit SHA, the exact command, the captured output, the
      timestamp in Asia/Jakarta, and the environment. A short SHA is insufficient.
- [ ] Evidence produced at an earlier SHA is **not** reused. If the tree changed, verification was re-run.
- [ ] Evidence artefacts live under `evidence/`, are sanitised, and state that sanitisation occurred.
- [ ] No evidence file contains a secret, token, credential, OTP, or personal datum.

### Gate 4 — Documented tenant isolation in the interface

- [ ] Tenant and outlet context visibility is specified for every authenticated screen.
- [ ] Every screen specification states its tenant behaviour and its permission behaviour.
- [ ] The tenant switcher, its confirmation, and its clearing of the visible working set are specified.
- [ ] Denial and absence are specified as indistinguishable across a tenant boundary.
- [ ] The external-courier guest surface is specified with no navigation, no search, no history, and no path
      to any other record.
- [ ] No design artefact proposes relaxing tenant scoping for convenience, reporting, performance, or a
      demo.

### Gate 5 — Documented security and privacy UX

- [ ] [`../security/DESIGN_AND_UX_THREAT_REVIEW.md`](../security/DESIGN_AND_UX_THREAT_REVIEW.md) exists,
      with findings carrying ID, area, affected surface, severity, argued impact and likelihood, UX
      mitigation, linked threat ID where one applies, linked requirement IDs, and status.
- [ ] **Zero `CRITICAL` findings open. Zero `HIGH` findings open.**
- [ ] Every `CRITICAL` and `HIGH` finding traces to a concrete UX mitigation and to at least one requirement
      ID.
- [ ] Every `MEDIUM` finding is either mitigated by design or accepted with a recorded rationale; every
      `LOW` and `INFORMATIONAL` finding is documented.
- [ ] [`../ux/SECURITY_AND_PRIVACY_UX.md`](../ux/SECURITY_AND_PRIVACY_UX.md) exists and specifies masking,
      tracking-token handling, clipboard, session expiry, device revocation, step-up authentication, OTP,
      permission denied, impersonation banner, audit reason capture, guest courier access, payment, refund
      and void confirmation, tenant switching, export, retention, and consent.
- [ ] The **public tracking portal prohibition list** is stated explicitly and completely.
- [ ] The rule that **push notifications must not carry excessive sensitive data** is stated explicitly.

### Gate 6 — Documented financial integrity in the interface

- [ ] Payment success is specified as **server-confirmed only**; the pending, confirmed, and failed states
      are visually and textually distinct.
- [ ] Retry is specified as reusing the original `client_reference`, and the interface says so.
- [ ] Refund and void are specified as first-class, discoverable, reason-mandatory, and reversal-based —
      never deletion.
- [ ] No delete-payment control appears in any ordinary role's specified interface.
- [ ] Displayed amounts are specified as integer Rupiah read from the authoritative record, formatted for
      display only.
- [ ] Payment conflicts are specified as surfaced for human resolution, never auto-resolved.

### Gate 7 — Accessibility foundation

- [ ] Minimum touch target 48×48 dp is specified across every surface, including the courier surface.
- [ ] Contrast is specified at the semantic token pair, meeting WCAG 2.2 AA for text and non-text UI
      components.
- [ ] Status is specified as never conveyed by colour alone — text label plus icon or shape, always.
- [ ] Font scaling, focus order, focus management, and assistive-technology semantics are specified.
- [ ] Every component carries an accessibility contract.
- [ ] Every accessibility statement uses the exact wording
      **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**, and **no accessibility audit,
      conformance result, or tested screen is claimed**.

### Gate 8 — No false claims

- [ ] No document, rule file, commit message, or pull request description asserts a screen, theme,
      component library, build, deployment, application CI pipeline, accessibility audit, performance
      measurement, test, or UAT result.
- [ ] No empty folder, `README`, or `.gitkeep` is presented as an implemented feature.
- [ ] No wireframe is presented as a screen; no token set as a theme; no criterion as a passed test.
- [ ] No false capability claim appears in copy — no route optimization, no delivery guarantee, no
      "unlimited WhatsApp".
- [ ] No final logo is fabricated or presented as approved.
- [ ] Anything unverified is labelled unverified, in plain words.

### Gate 9 — Traceability and documentation updated

- [ ] Every screen and component carries at least one requirement ID.
- [ ] Every requirement carries a UX classification.
- [ ] No orphan in either direction — no user-facing requirement without a screen, no screen without a
      requirement.
- [ ] Design traceability is updated **in the same pull request** as the design change.
- [ ] **Graphify relationship review performed**, with output captured at the exact commit SHA. Diagram
      tooling introduced no new product fact, screen, or flow.
- [ ] All internal markdown links resolve to files that exist.
- [ ] Every fenced code block is properly closed.
- [ ] `docs/STATUS.md`, `docs/ROADMAP.md`, and the changelog are updated.

### Gate 10 — CI green at the exact SHA

- [ ] Governance CI is green **at the exact SHA under review**, with actions pinned to full commit SHAs and
      workflow permissions least-privilege.
- [ ] **Adversarial validator testing performed**: every Step 2 validator was run against deliberately
      broken input — a missing requirement reference, an orphan screen, a colour-only status, an unclosed
      code fence, a broken internal link — and was shown to **fail** on each. A validator only ever run
      against correct input is reported as untested, not as a passing gate.
- [ ] Secret scanning passes. Every example datum is fictional and recognisably so.
- [ ] **No runtime artefact of any kind was introduced.**
- [ ] Application CI remains `NOT APPLICABLE` and is not claimed otherwise.

### Gate 11 — Owner acceptance

- [ ] The pull request is open against `main`, states what changed, which Step it belongs to, what was
      verified, **at which exact SHA**, and what remains unverified.
- [ ] The pull request does **not** claim `GO`.
- [ ] **`GO` is conferred by the repository owner and is never self-declared by an agent.** An agent that
      writes `GO` for Step 2 has committed a status-inflation violation under Rule 01.
- [ ] Any `GO` tag published on acceptance is **annotated and immutable**, and is never moved, deleted, or
      re-pointed.

---

## 4. Public repository constraints on this Step

This repository is **PUBLIC**, an accepted deviation from a canonical desired **PRIVATE**
(AMENDMENT-0001, [`../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md`](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
It is **never described as private**, and **PUBLIC is never presented as the desired end state**.

Design work is the highest-risk authoring activity for this constraint, because wireframes, copy decks, and
component examples all want realistic data. Step 2 authoring therefore requires:

- **Every example datum is fictional** and recognisably so — invented names, structurally valid but
  unallocated phone patterns, invented outlets, invented order references. Never copied from a real
  customer, device, log, message, or screenshot.
- **Only `PUBLIC` and sanitised `INTERNAL` material is committed.** `CONFIDENTIAL`, `RESTRICTED`, and
  `SECRET` may be described and modelled but **never instantiated with real values**.
- **No screenshot of a real system, real message thread, or real device is committed.**
- **Evidence packs are sanitised before commit** and state that sanitisation occurred.
- **Token, component, and screen names carry no unreleased commercial intent** — no unannounced plan,
  feature, or customer name.
- **Deletion is not remediation.** Anything committed must be assumed mirrored, cached, and indexed from the
  moment it is pushed. A committed secret is **rotated first** and removed second.

---

## 5. Governance mode — stated honestly

**Governance operates in single-maintainer mode. Independent human approval is `ABSENT`.**

Master Source §25.1 item 12 requires a Step-closing change to be reviewed and approved by someone other than
the author. That requirement **cannot currently be satisfied**. It is a **standing accepted deviation**
recorded in [`../decisions/DEC-0017-single-maintainer-approval-standing-deviation.md`](../decisions/DEC-0017-single-maintainer-approval-standing-deviation.md);
it is not re-argued per Step and is not re-reported as a fresh failure.

The compensating controls are the active branch-protection ruleset, exact-SHA CI, the deterministic
validators, adversarial validator testing, and recorded internal re-verification. They are **load-bearing,
not supplementary**, and they are **not equivalent to independent review**. A design defect that both the
maintainer and the validators miss is not caught. That residual risk is **accepted, not eliminated**.

**Internal re-verification is never described as independent peer review, and a self-review is never
described as an approval.** Where a report would normally say "reviewed", it says "internally re-verified
under single-maintainer governance".

---

## 6. Violation handling

- **Step 2 declared done without exact-SHA evidence** — the declaration is void. Re-run verification or
  withdraw the claim.
- **Any claim of a screen, theme, component library, build, deployment, application CI, accessibility audit,
  performance measurement, test, or UAT in Step 2** — remove immediately. These are `NOT IMPLEMENTED`,
  `ABSENT`, `NOT APPLICABLE`, and `NOT STARTED`, and saying otherwise is a false claim.
- **A wireframe reported as a screen, a token set as a theme, or an accessibility requirement as a passed
  audit** — correct it immediately and visibly, and state that the earlier claim was wrong.
- **A `CRITICAL` or `HIGH` design finding left without a UX mitigation** — validator failure; the Step is not
  done.
- **A runtime artefact introduced during Step 2** — remove it and report the scope breach.
- **Step 3 work performed before Step 2 `GO`** — stop, revert the forward-leaked work, and report.
- **Fabricated output or a hand-edited checksum** — automatic **`NO-GO`**. Stop work, disclose to the owner,
  and treat every other claim from the same session as suspect until re-verified.
- **`GO` self-declared by an agent** — revert the wording before the pull request proceeds.
- **A secret or real personal datum committed** — rotate first, disclose, then remove. Removal without
  rotation is not a fix.
- **A tag moved, deleted, or re-pointed** — record the incident, restore the original target if possible,
  and publish a corrective tag.
- **Internal re-verification reported as independent review or approval** — correct the wording; this is a
  false claim under Rule 01.
- Repeated status inflation is grounds for the owner to reject the branch entirely.

---

## 7. Step 2 completion checklist

| # | Item | Gate |
| --- | --- | --- |
| 1 | Visual language, palette roles, typography, spacing, elevation, and iconography documented | 1 |
| 2 | Design token inventory and two-layer governance documented; no raw value without a semantic mapping | 1 |
| 3 | Light theme documented as the MVP theme; dark mode documented as `PLANNED` and not claimed available | 1, 2 |
| 4 | Accessibility foundation documented — contrast, 48×48 dp targets, font scaling, focus, semantics | 7 |
| 5 | Platform-adaptive navigation documented for all four surfaces | 1 |
| 6 | UX state model documented, including offline, pending-sync, stale, error, and denied states | 6 |
| 7 | Content design and Bahasa Indonesia terminology discipline documented and bound to the glossary | 1 |
| 8 | Responsive and device constraints documented against the stated operating environment | 1 |
| 9 | Security and privacy UX patterns documented, including the portal prohibition list and the push-payload rule | 5 |
| 10 | Design and UX threat review complete: 0 `CRITICAL` open, 0 `HIGH` open, `MEDIUM` fixed or accepted, `LOW` and `INFORMATIONAL` documented | 5 |
| 11 | Component inventory documented; every component carries a state contract and an accessibility contract | 7, 9 |
| 12 | Screen specifications documented; every screen carries requirement references, tenant behaviour, permission behaviour, and error and recovery states | 4, 9 |
| 13 | Financial interaction patterns documented — server-confirmed payment, reason-mandatory refund and void, reversal not deletion | 6 |
| 14 | Every requirement carries a UX classification; no orphan in either direction | 9 |
| 15 | Graphify relationship review performed and captured at the exact SHA | 9 |
| 16 | Governance rules 25–35 exist and are consistent with the Master Source | 1 |
| 17 | Status vocabulary correct everywhere; no forbidden claim anywhere; `GO` not written for Step 2 | 2, 8 |
| 18 | All internal markdown links resolve; every fenced code block closed | 9 |
| 19 | `docs/STATUS.md`, `docs/ROADMAP.md`, and the changelog updated | 9 |
| 20 | Governance validators pass at the exact SHA; output captured unedited | 3, 10 |
| 21 | Adversarial validator testing performed and recorded | 10 |
| 22 | Secret scanning passes; every example fictional; evidence sanitised | 4 (§4 above), 10 |
| 23 | No runtime artefact of any kind introduced; runtime folders still hold only `README` or `.gitkeep` | 10 |
| 24 | Pull request open against `main`, stating what was verified, at which exact SHA, and what remains unverified — and **not** claiming `GO` | 11 |

---

## 8. Open questions for the repository owner

These are genuine gaps. **None is closed by inventing a product decision**, and none is a Step 2 blocker
unless the owner says so.

1. **The final logo and brand mark.** Not decided. Step 2 uses a labelled placeholder and fabricates
   nothing. The owner confers the mark.
2. **Dark mode timing.** Deferred and `PLANNED`. The Step at which it is delivered is not fixed.
3. **Retention periods for proof photographs, signatures, tracking links, and exports.** Where a period is
   undecided, the interface shows it as undefined rather than inventing a plausible number.
4. **Storage-fee messaging boundaries for tenant-customised templates.** The platform constrains its own
   templates; it cannot fully constrain a tenant's. Recorded as accepted residual risk (`DUX-025`).
5. **Offline queue recovery on device revocation.** The full recovery design depends on server-side queue
   introspection and belongs to Step 3. Step 2 fixes only the honesty of the interface (`DUX-022`).
6. **Whether tenant-uploaded SVG brand assets are permitted at all**, or whether tenant uploads are
   restricted to raster formats. Step 2 records the constraint; the product decision is the owner's
   (`DUX-028`).

---

## 9. Related documents

- [`../security/DESIGN_AND_UX_THREAT_REVIEW.md`](../security/DESIGN_AND_UX_THREAT_REVIEW.md)
- [`../ux/SECURITY_AND_PRIVACY_UX.md`](../ux/SECURITY_AND_PRIVACY_UX.md)
- [`STEP_01_DEFINITION_OF_DONE.md`](STEP_01_DEFINITION_OF_DONE.md)
- [`ACCEPTANCE_CRITERIA.md`](ACCEPTANCE_CRITERIA.md) · [`NON_FUNCTIONAL_REQUIREMENTS.md`](NON_FUNCTIONAL_REQUIREMENTS.md)
- [`../security/INITIAL_THREAT_MODEL.md`](../security/INITIAL_THREAT_MODEL.md) · [`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md)
- [`../security/PRIVACY_REQUIREMENTS.md`](../security/PRIVACY_REQUIREMENTS.md) · [`../security/SECURITY_ACCEPTANCE_CRITERIA.md`](../security/SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../product/PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md) · [`../domain/DOMAIN_GLOSSARY.md`](../domain/DOMAIN_GLOSSARY.md)
- [`../DEFINITION_OF_DONE.md`](../DEFINITION_OF_DONE.md) · [`../STATUS.md`](../STATUS.md) · [`../ROADMAP.md`](../ROADMAP.md) · [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md)
