# Design Tokens — Aish Laundry App

**Step:** Step 2 — Design System and UX Foundation
**Status:** `NOT IMPLEMENTED`
**Theme:** light (canonical MVP theme). Dark mode is `PLANNED` and `NOT IMPLEMENTED`.

These files are **specifications, not a running theme.** No Flutter workspace, no backend, and no build
step consumes them. The Flutter workspace is `ABSENT` and the backend runtime is `ABSENT`. A token file
describes an obligation on every later Step; it is never evidence that a surface has been styled.

---

## The three layers

| Layer | File(s) | May reference | May be referenced by |
|---|---|---|---|
| **Primitive** | `primitives.json`, `typography.json`, `spacing.json`, `sizing.json`, `radius.json`, `border.json`, `elevation.json`, `motion.json`, `opacity.json`, `breakpoints.json`, `density.json`, `iconography.json` | nothing — literal values only | semantic tokens, and non-colour aliases |
| **Semantic** | `semantic-light.json` | primitive colour tokens | component aliases, component specifications |
| **Alias** | `component-aliases.json` | semantic colour tokens; primitive dimension/motion/elevation/typography tokens | component specifications |

**A component specification names an alias or a semantic token. It never names a primitive colour and it
never names a literal hex value.** A hard-coded colour in a component specification is a governance
defect under [Rule 26](../../../.claude/rules/26-design-token-governance.md), not a style preference.

There is no separate semantic tier for dimension, motion, elevation or typography, because those
primitives already carry their role in the name (`size.touch.min`, `motion.reduced.duration`). Colour is
the exception: a hex value carries no meaning, so it always passes through the semantic layer.

---

## Files

| File | Contents |
|---|---|
| `token-schema.json` | JSON Schema every token file validates against |
| `primitives.json` | 54 primitive colour tokens across 8 ramps, each with RGB and computed contrast |
| `semantic-light.json` | 32 semantic colour tokens for the canonical light theme |
| `typography.json` | System-first font stacks, type scale, weights, tabular-figure feature |
| `spacing.json` | 4px grid spacing scale |
| `sizing.json` | Icon, control, avatar and layout sizes, including `size.touch.min` |
| `radius.json` | Corner radius scale |
| `border.json` | Border widths and focus-ring geometry |
| `elevation.json` | Light-theme elevation levels |
| `motion.json` | Durations, easing, and the reduced-motion contract |
| `opacity.json` | Opacity scale |
| `breakpoints.json` | Semantic breakpoints and the two hard width guarantees |
| `density.json` | Compact / standard / comfortable densities and Android dp foundation |
| `iconography.json` | Icon policy and the semantic icon mapping |
| `component-aliases.json` | Component-scoped aliases |

---

## Naming convention

```
<category>.<family>.<step>            color.blue.700
<category>.<role>                     space.4 · radius.md · elevation.2
color.semantic.<meaning>[.<variant>]  color.semantic.danger.surface
component.<component>.<part>[.<state>] component.button.primary.backgroundHover
```

Names are lowercase, dot-separated, and stable. **A token name is permanent.** Renaming a token silently
changes the meaning of every specification that cited it, so a rename requires a decision record and a
migration note in [`../DESIGN_DECISION_LOG.md`](../DESIGN_DECISION_LOG.md).

---

## Contrast is computed, never asserted

Every colour token carries a `measuredContrast` block computed from its own hex value using the WCAG 2.2
relative-luminance formula. These are not estimates and were not copied from a design tool.

| `contrastTarget` | Requirement |
|---|---|
| `normal-text-4.5` | ≥ 4.5:1 against the surface it is permitted on |
| `large-text-3.0` | ≥ 3:1 |
| `interactive-boundary-3.0` | ≥ 3:1 — inputs, checkboxes, switches, focus ring |
| `decorative-exempt` | carries no meaning; exempt, and never the sole indicator of anything |
| `inactive-exempt` | inactive components only (WCAG 2.2 SC 1.4.3 exemption) |
| `background-only` | never a foreground colour |

`scripts/validate-color-contrast.py` recomputes every ratio from the hex values and fails if a token
misses its declared target. The recorded number and the computed number must agree — a hand-edited
contrast figure is falsified evidence under
[Rule 01](../../../.claude/rules/01-status-and-evidence.md).

**Status is never conveyed by colour alone.** Every status carries three redundant signals: a semantic
colour, a semantic icon from `iconography.json`, and a Bahasa Indonesia text label from
[`../UX_COPY_GLOSSARY.md`](../UX_COPY_GLOSSARY.md).

---

## The gold constraint

Gold is a **restrained accent**, and the token set enforces that structurally:

- `color.gold.300` / `color.gold.400` are `decorative-exempt`. They may fill a small badge or draw a thin
  rule. They are **never** body text, **never** the sole indicator of a warning, **never** dominant, and
  **never** a meaning-bearing boundary.
- `color.gold.600` (measured 5.21:1 on white) is the **only** gold permitted to carry text or a
  meaning-bearing boundary.

Warning semantics use `color.semantic.warning` (amber), never gold. Gold and warning are deliberately
different hues so that "premium accent" and "something needs attention" can never be confused.

---

## What these tokens do not do

- They do not implement a theme. Nothing renders them.
- They do not define a dark theme. Dark mode is `PLANNED` / `NOT IMPLEMENTED`; there is no
  `semantic-dark.json` and none may be claimed.
- They do not ship a font. No font binary is committed; the strategy is system-first.
- They do not ship an icon set. No icon binary is committed; icons are referenced by name.
- They are not a passed accessibility audit. The colour system is
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## Related

- [`../DESIGN_SYSTEM.md`](../DESIGN_SYSTEM.md) — how tokens, components and screens relate
- [`../COLOR_AND_CONTRAST.md`](../COLOR_AND_CONTRAST.md) — the colour system in prose
- [`../ACCESSIBILITY.md`](../ACCESSIBILITY.md) — the accessibility foundation
- [Rule 26 — Design Token Governance](../../../.claude/rules/26-design-token-governance.md)
