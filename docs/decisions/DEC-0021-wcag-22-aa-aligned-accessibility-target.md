# DEC-0021 — WCAG 2.2 AA-Aligned Accessibility Target, Explicitly Not Runtime-Tested

**ID:** DEC-0021
**Title:** WCAG 2.2 AA-Aligned Accessibility Target, Explicitly Not Runtime-Tested
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

The Master Source requires that accessibility and device font scaling are supported, that contrast
meets accessible ratios, and that status is never conveyed by colour alone (§18.2). Step 2 must turn
that into a checkable target.

It must do so without overstating. There is no runtime, no screen and no assistive-technology
session in existence. Any statement that accessibility has been *verified* would be a false claim
under Rule 01, and the temptation to make one grows as the documentation gets thorough.

## Options considered

1. **State no target; handle accessibility per screen as it is built.** Rejected: it guarantees
   accessibility is retrofitted, which in practice means never.
2. **Claim WCAG 2.2 AA conformance.** Rejected outright as a false claim. Conformance is a property
   of a running interface, and there is none.
3. **State a WCAG 2.2 AA-aligned design target with a mandatory caveat, enforced mechanically.**
4. **Target WCAG 2.1 AA.** Rejected: 2.2 adds criteria — focus appearance, target size, dragging
   movements, consistent help — that bear directly on a courier operating one-handed outdoors.

## Decision

**Option 3 is adopted.**

The mandated wording, used verbatim and without softening, is:

> **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

The target is made concrete and machine-checkable:

- Normal text ≥ **4.5:1**; large text ≥ **3:1**; interactive boundaries and the focus ring ≥ **3:1**.
  Every ratio is recomputed from the hex value by `validate-color-contrast.py`.
- Minimum touch target **48 × 48** logical pixels, with a minimum gap between adjacent targets.
  Compact density is pointer-only precisely because its row height sits below that floor.
- **The focus indicator is never removed**, never set to none, never reduced below 3:1.
- **Status is never conveyed by colour alone.** Every status carries three redundant signals: a
  semantic colour, a semantic icon, and a Bahasa Indonesia text label.
- Reduced motion removes non-essential animation without removing the state signal along with it.
- Every component carries a keyboard contract and a screen-reader contract.
- Every critical screen carries accessibility notes.

**Accessibility is a hard gate, not a backlog item.** A component without an accessibility contract
fails the Step.

## Consequences

Accessibility becomes a property the design system can be checked against today, while the honest
limitation — nothing has been exercised with a real assistive technology — stays visible.

### Positive consequences

- Contrast is computed, not asserted, so an inaccessible colour cannot ship unnoticed.
- The obligations are attached to components and screens now, before there is any code to retrofit.
- The claim is honest, which means a later real audit has a stated baseline to measure against.

### Negative consequences / trade-offs

- A design-time target is genuinely weaker than a tested result, and this decision does not pretend
  otherwise. Screen-reader behaviour, focus order in practice, and real text-scaling reflow remain
  **unverified**.
- Some WCAG 2.2 criteria cannot be evaluated at all without a runtime, so the design-time gate is
  necessarily partial.
- Holding the 48dp floor costs screen density on small handsets.

## Verification

`python3 scripts/validate-accessibility.py` enforces the exact claim wording, the exact caveat, the
full topic coverage, the numeric floors, the focus-never-removed rule and the colour-independence
rule — and separately fails on any *stronger* claim ("WCAG certified", "accessibility tested",
"fully accessible") found anywhere in the Step 2 corpus.

`python3 scripts/validate-color-contrast.py` recomputes every ratio.
`python3 scripts/validate-breakpoints.py` enforces the 48dp floor.

Adversarial mutations 5, 7 and 29 prove the focus guarantee, the touch-target floor and the
component accessibility contract are each genuinely enforced.

**Runtime accessibility testing is `NOT STARTED` and is scheduled for Step 13.**

## Requirement references

NFR-020 and the accessibility-bearing NFR series; every UI-DIRECT requirement in `docs/quality/STEP_02_TRACEABILITY.md` carries an accessibility criterion.

## Threat references

DUX-016 (accessibility exclusion), DUX-017 (colour-only status), DUX-018 (focus loss), DUX-019 (screen-reader ambiguity), DUX-020 (OTP usability).

## Rule references

Rule 27 (accessibility foundation), Rule 29 (UX state model), Rule 34 (component and screen governance).

## Supersession policy

Superseded when runtime accessibility testing is actually performed, at which point a new record
states what was tested, with which assistive technologies, on which devices, at which exact SHA, and
what failed. Only such a record may replace the "NOT YET RUNTIME-TESTED" caveat — and it may only
replace it with what the evidence supports.

## Related Master Source sections

§18.2 (canonical UX rules, items 2 and 6), §18.1 (visual language), §19 (performance), §24 (roadmap, Step 13).
