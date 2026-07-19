# DEC-0019 — Light Theme is the Canonical MVP Theme; Dark Mode Deferred

**ID:** DEC-0019
**Title:** Light Theme is the Canonical MVP Theme; Dark Mode Deferred
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

The Master Source fixes the visual identity as white, soft blue, dark blue and a restrained gold
accent, with white as the dominant surface (§18.1). A second theme doubles the colour surface that
must be designed, contrast-checked, and kept consistent — and a half-finished dark theme is worse
than none, because a single unconverted surface becomes an unreadable screen at a counter.

Aish Laundry App runs primarily in brightly lit laundry shops on low-end Android devices. The
dominant operational context favours a light surface.

## Options considered

1. **Light theme only, permanently.** Rejected: dark mode is a genuine accessibility and battery
   affordance, and closing the door on it now would be an unforced product decision.
2. **Light and dark themes both delivered in Step 2.** Rejected: it doubles the contrast surface
   before a single screen exists, and the second theme would be unverifiable against real content.
3. **Light theme canonical for the MVP; dark mode explicitly deferred and recorded as such.**

## Decision

**Option 3 is adopted.**

The light theme is the canonical MVP theme and the only theme Step 2 specifies. Dark mode is
`PLANNED` and `NOT IMPLEMENTED`.

Concretely, and enforced mechanically:

- There is exactly one semantic theme file, `semantic-light.json`.
- No `semantic-dark.json` exists, and `validate-design-tokens.py` fails if any dark-theme token file
  appears.
- Every token file declares `"theme": "light"` or `"theme": "none"`.
- Elevation shadow values are explicitly tuned for the light theme only, and the token file says so.
- **No document may claim dark mode is available.** `validate-design-required-files.py` fails on that
  claim.

The two-layer architecture in DEC-0018 is what makes deferral cheap: a future dark theme is a second
semantic file binding the same meanings to different primitives, with no component specification
changing.

## Consequences

Step 2 designs and contrast-checks one theme thoroughly rather than two superficially. The
architecture leaves the door open without pretending the work is done.

### Positive consequences

- One theme to design, verify and keep consistent, which is the difference between contrast being
  genuinely checked and being nominally claimed.
- The deferral is visible and re-examinable rather than an unrecorded omission.
- Adding dark mode later costs a semantic file, not a redesign.

### Negative consequences / trade-offs

- Users who prefer or need a dark interface are not served by the MVP. This is a real accessibility
  cost, accepted and recorded rather than hidden.
- Low-light use — a courier at night, a shop after hours — is less comfortable than it could be.
- Elevation and shadow values will need genuine rework for a dark surface; they do not simply invert.

## Verification

`python3 scripts/validate-design-tokens.py` asserts no dark-theme token file exists and every file
declares a light or theme-neutral value.

`python3 scripts/validate-design-required-files.py` fails on any claim that dark mode is available.

Adversarial mutation 27 in `scripts/test-step-02-validators.sh` proves the claim is caught: it
appends "Dark mode is available and ships with the MVP" and requires the validator to turn red.

## Requirement references

NFR-020, NFR-041 … NFR-050. Accessibility requirements remain satisfied in the light theme; no requirement is deferred with the theme.

## Threat references

DUX-016 (accessibility exclusion) — the residual risk of serving only a light theme is recorded there and accepted.

## Rule references

Rule 25, Rule 26 (light theme is MVP; dark mode deferred), Rule 27 (accessibility foundation).

## Supersession policy

Superseded by a decision record that schedules dark mode to a named Step, at which point a
`semantic-dark.json` is authored, every semantic token is bound for the dark surface, elevation is
re-derived, and the full contrast gate is re-run for the new theme. Until such a record exists, dark
mode remains `NOT IMPLEMENTED` and must be described that way.

## Related Master Source sections

§18.1 (visual language), §18.4 (foundation delivery), §19 (performance, low-end Android baseline).
