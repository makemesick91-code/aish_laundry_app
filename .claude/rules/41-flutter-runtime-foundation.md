# Rule 41 — Flutter Runtime Foundation

## Purpose

Rule 05 and Rule 25 fixed the Flutter client foundation and the design system as documentation. Step 3
is the first step where a real `pubspec.yaml`, a real `packages/design_system` implementation, and a
real Flutter shell may exist. This rule fixes how that first runtime code must relate to the Step 2
specification it is built from, and how honestly its state may be described.

## Hard rules

1. **Runtime design tokens implemented in `packages/design_system` must not drift from the Step 2 token
   inventory and governance.** A token's name, role, and semantic mapping in code match what Rule 26
   specified; introducing a new token, renaming one, or remapping one at implementation time without
   updating the governed inventory is a defect, not an acceptable implementation detail.
2. **Accessibility claims about a runtime shell are bounded strictly to what was actually executed.**
   The permitted wording for a Step 3 shell that has had its accessibility foundation exercised is
   **RUNTIME ACCESSIBILITY FOUNDATION TESTED FOR SHELLS — FULL ASSISTIVE-TECHNOLOGY AND WCAG AUDIT NOT
   YET COMPLETED.** No stronger claim — no "WCAG 2.2 AA compliant," no "accessibility audited" — may be
   made until a full assistive-technology and WCAG audit has actually run and its output is captured at
   an exact commit SHA (Rule 01, Rule 27).

## Supporting expectations

- The three Flutter surfaces remain as fixed in Rule 05: Aish Laundry Customer Android, Aish Laundry Ops
  Android, and Aish Laundry Console/Admin Web. The public tracking portal remains the fourth,
  deliberately non-Flutter-mandated surface and is never made to require an app install (Rule 05, Rule
  28, DEC-0006, DEC-0014).
- **The light theme remains the only implemented theme.** Dark mode remains `PLANNED` at runtime exactly
  as it was at specification time; no dark-mode mapping is introduced, wired, or exposed behind a flag
  during Step 3 (Rule 25, Rule 26).
- A component built in `packages/design_system` carries the same state contract, accessibility contract,
  token usage, and content contract its Step 2 specification declared (Rule 34); a runtime component
  that silently drops a documented state is incomplete, not simplified.
- The Dart workspace root manifest is `pubspec.yaml` plus `analysis_options.yaml` only; no other
  top-level manifest is introduced without following Rule 37.

## Step 3 note

**No Flutter workspace exists yet.** `apps/customer_android/`, `apps/ops_android/`, `apps/admin_web/`,
and `packages/design_system/` presently contain only a `README.md`. The Flutter workspace remains
`ABSENT` until an actual `pubspec.yaml` and application or package source populate an approved root, and
no runtime screen, theme, or component may be described as built before that is true and demonstrated
(Rule 01, Rule 34).

## Violation handling

- **A runtime token that does not match its Step 2 semantic mapping** — reject; correct the
  implementation to match the governed token, or update the token inventory through its own governed
  change first (Rule 26).
- **An accessibility claim stronger than the permitted Step 3 wording** — correct it immediately and
  visibly; state that the earlier claim was wrong (Rule 01, Rule 27).
- **Dark mode implemented, wired, or exposed in any runtime build** — remove it; dark mode is `PLANNED`,
  not delivered (Rule 25).
- **A change requiring an app install to use public tracking** — reject; it contradicts DEC-0006 and
  DEC-0014 regardless of which surface introduced it.
- **A runtime component missing a state or accessibility behaviour its Step 2 specification declared** —
  the component is incomplete; the Step is not done (Rule 34).
- **A Flutter workspace, screen, or theme claimed to exist when only a placeholder directory exists** —
  correct the claim immediately (Rule 01, Rule 15).
