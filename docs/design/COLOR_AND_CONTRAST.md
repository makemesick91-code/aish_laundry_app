# Colour and Contrast

> **Step 2 — Design System and UX Foundation. Documentation only.**
> No runtime renders these colours. The design token system is
> `NOT IMPLEMENTED` and the Flutter workspace is `ABSENT`.
>
> **This file is generated** by `scripts/build-color-and-contrast.py`
> from [`tokens/`](tokens/). Every hex, RGB triple and contrast ratio
> below is read or computed from the token files, so this document
> cannot drift from the system it describes. Do not hand-edit it —
> edit the tokens and regenerate.

---

## 1. How contrast is established here

Every ratio in this document is **computed** from the token's own hex
value using the WCAG 2.2 relative-luminance formula. None is copied
from a design tool and none is estimated.

`scripts/validate-color-contrast.py` recomputes all of them on every
commit and fails if a recorded figure and a computed figure disagree.
A hand-edited contrast figure is falsified evidence under
[Rule 01](../../.claude/rules/01-status-and-evidence.md), not a typo.

The accessibility position is
**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**.
Contrast is checkable at design time; whether a real screen renders it
correctly is not, and is `NOT STARTED` until Step 13.

| Target | Requirement |
|---|---|
| `normal-text-4.5` | ≥ 4.5:1 against every surface it is permitted on |
| `large-text-3.0` | ≥ 3:1 |
| `interactive-boundary-3.0` | ≥ 3:1 — inputs, checkboxes, switches, the focus ring |
| `decorative-exempt` | carries no meaning and is never the sole indicator of anything |
| `inactive-exempt` | inactive components only (WCAG 2.2 SC 1.4.3 exemption) |
| `background-only` | never a foreground colour |

## 2. Status is never conveyed by colour alone

This is the rule the palette is built around, and it is not advisory.
Every status carries **three redundant signals**:

1. a semantic colour from §4,
2. a semantic icon from [`tokens/iconography.json`](tokens/iconography.json),
3. a Bahasa Indonesia text label from [`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md).

A shop floor is brightly lit, screens are cheap, and roughly one in
twelve men has a colour vision deficiency. A status that depends on hue
alone is a status that will be misread — and on this product misreading
`SYNCING` as `SYNCED` means a cashier believes a payment was accepted
when the server never saw it.

`syncing` (teal), `conflict` (violet), `warning` (amber) and `danger`
(red) were chosen to stay distinguishable from one another under the
common forms of colour vision deficiency — but the redundant icon and
label are what actually carry the meaning.

## 3. Primitive ramps

Primitives hold values and carry no meaning. **A component never names
a primitive colour.** Ratios are against the page surface (`#FFFFFF`) and the raised surface (`#F7F8FA`).

### 3.1 `blue`

The brand spine. `color.blue.700` is the canonical primary.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.blue.50` | `#F0F6FC` | `rgb(240, 246, 252)` | 1.09:1 | 1.02:1 | background only |
| `color.blue.100` | `#DBE9F6` | `rgb(219, 233, 246)` | 1.24:1 | 1.16:1 | background only |
| `color.blue.200` | `#B7D2EC` | `rgb(183, 210, 236)` | 1.56:1 | 1.47:1 | background only |
| `color.blue.300` | `#8AB6DF` | `rgb(138, 182, 223)` | 2.13:1 | 2.01:1 | background only |
| `color.blue.400` | `#5A95CC` | `rgb(90, 149, 204)` | 3.18:1 | 2.99:1 | background only |
| `color.blue.500` | `#2A6FA9` | `rgb(42, 111, 169)` | 5.32:1 | 5.01:1 | normal text — 4.5:1 |
| `color.blue.600` | `#1F5E96` | `rgb(31, 94, 150)` | 6.77:1 | 6.38:1 | normal text — 4.5:1 |
| `color.blue.700` | `#0A4F8F` | `rgb(10, 79, 143)` | 8.31:1 | 7.82:1 | normal text — 4.5:1 |
| `color.blue.800` | `#083D70` | `rgb(8, 61, 112)` | 10.98:1 | 10.34:1 | normal text — 4.5:1 |
| `color.blue.900` | `#0A2540` | `rgb(10, 37, 64)` | 15.54:1 | 14.62:1 | normal text — 4.5:1 |

### 3.2 `gold`

The restrained brand accent. Only `color.gold.600` may carry text.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.gold.50` | `#FBF6E9` | `rgb(251, 246, 233)` | 1.08:1 | 1.02:1 | background only |
| `color.gold.100` | `#F5E9C4` | `rgb(245, 233, 196)` | 1.21:1 | 1.14:1 | background only |
| `color.gold.200` | `#EBD595` | `rgb(235, 213, 149)` | 1.45:1 | 1.36:1 | background only |
| `color.gold.300` | `#DDBC5F` | `rgb(221, 188, 95)` | 1.84:1 | 1.73:1 | decorative — exempt |
| `color.gold.400` | `#C79A2B` | `rgb(199, 154, 43)` | 2.6:1 | 2.44:1 | decorative — exempt |
| `color.gold.500` | `#A87F17` | `rgb(168, 127, 23)` | 3.68:1 | 3.46:1 | decorative — exempt |
| `color.gold.600` | `#8A6710` | `rgb(138, 103, 16)` | 5.21:1 | 4.91:1 | normal text — 4.5:1 |
| `color.gold.700` | `#6B4F0B` | `rgb(107, 79, 11)` | 7.64:1 | 7.19:1 | normal text — 4.5:1 |

### 3.3 `neutral`

Surfaces, text, borders and dividers.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.neutral.0` | `#FFFFFF` | `rgb(255, 255, 255)` | 1.0:1 | 1.06:1 | background only |
| `color.neutral.50` | `#F7F8FA` | `rgb(247, 248, 250)` | 1.06:1 | 1.0:1 | background only |
| `color.neutral.100` | `#EFF1F4` | `rgb(239, 241, 244)` | 1.13:1 | 1.06:1 | background only |
| `color.neutral.200` | `#DFE3E8` | `rgb(223, 227, 232)` | 1.29:1 | 1.21:1 | background only |
| `color.neutral.300` | `#C4CAD3` | `rgb(196, 202, 211)` | 1.65:1 | 1.55:1 | background only |
| `color.neutral.400` | `#9AA3B0` | `rgb(154, 163, 176)` | 2.55:1 | 2.4:1 | inactive component — exempt (SC 1.4.3) |
| `color.neutral.500` | `#737D8C` | `rgb(115, 125, 140)` | 4.17:1 | 3.92:1 | interactive boundary — 3:1 |
| `color.neutral.600` | `#566070` | `rgb(86, 96, 112)` | 6.36:1 | 5.98:1 | normal text — 4.5:1 |
| `color.neutral.700` | `#3E4756` | `rgb(62, 71, 86)` | 9.37:1 | 8.82:1 | normal text — 4.5:1 |
| `color.neutral.800` | `#2A313D` | `rgb(42, 49, 61)` | 13.08:1 | 12.31:1 | normal text — 4.5:1 |
| `color.neutral.900` | `#171C24` | `rgb(23, 28, 36)` | 17.1:1 | 16.09:1 | normal text — 4.5:1 |

### 3.4 `green`

Success semantics only.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.green.50` | `#E8F5EC` | `rgb(232, 245, 236)` | 1.12:1 | 1.06:1 | background only |
| `color.green.100` | `#C2E5CE` | `rgb(194, 229, 206)` | 1.36:1 | 1.28:1 | background only |
| `color.green.500` | `#0F7A3D` | `rgb(15, 122, 61)` | 5.42:1 | 5.1:1 | normal text — 4.5:1 |
| `color.green.600` | `#0B5C2E` | `rgb(11, 92, 46)` | 8.13:1 | 7.65:1 | normal text — 4.5:1 |
| `color.green.700` | `#08431F` | `rgb(8, 67, 31)` | 11.46:1 | 10.78:1 | normal text — 4.5:1 |

### 3.5 `amber`

Warning semantics only — never gold.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.amber.50` | `#FDF3E4` | `rgb(253, 243, 228)` | 1.1:1 | 1.03:1 | background only |
| `color.amber.100` | `#F8E0B8` | `rgb(248, 224, 184)` | 1.28:1 | 1.21:1 | background only |
| `color.amber.500` | `#9A5B00` | `rgb(154, 91, 0)` | 5.43:1 | 5.11:1 | normal text — 4.5:1 |
| `color.amber.600` | `#7A4800` | `rgb(122, 72, 0)` | 7.62:1 | 7.17:1 | normal text — 4.5:1 |
| `color.amber.700` | `#5C3600` | `rgb(92, 54, 0)` | 10.59:1 | 9.97:1 | normal text — 4.5:1 |

### 3.6 `red`

Danger and destructive semantics only.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.red.50` | `#FCEBEA` | `rgb(252, 235, 234)` | 1.15:1 | 1.09:1 | background only |
| `color.red.100` | `#F7C9C5` | `rgb(247, 201, 197)` | 1.49:1 | 1.4:1 | background only |
| `color.red.500` | `#B3261E` | `rgb(179, 38, 30)` | 6.54:1 | 6.15:1 | normal text — 4.5:1 |
| `color.red.600` | `#8C1D17` | `rgb(140, 29, 23)` | 9.12:1 | 8.58:1 | normal text — 4.5:1 |
| `color.red.700` | `#6B1611` | `rgb(107, 22, 17)` | 12.01:1 | 11.31:1 | normal text — 4.5:1 |

### 3.7 `teal`

Synchronisation-in-progress semantics only.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.teal.50` | `#E6F3F5` | `rgb(230, 243, 245)` | 1.13:1 | 1.07:1 | background only |
| `color.teal.100` | `#BCDFE4` | `rgb(188, 223, 228)` | 1.42:1 | 1.33:1 | background only |
| `color.teal.500` | `#0F6E7B` | `rgb(15, 110, 123)` | 5.94:1 | 5.59:1 | normal text — 4.5:1 |
| `color.teal.600` | `#0B535D` | `rgb(11, 83, 93)` | 8.72:1 | 8.2:1 | normal text — 4.5:1 |
| `color.teal.700` | `#083E45` | `rgb(8, 62, 69)` | 11.76:1 | 11.07:1 | normal text — 4.5:1 |

### 3.8 `violet`

Conflict semantics only.

| Token | Hex | RGB | On page | On raised | Target |
|---|---|---|---:|---:|---|
| `color.violet.50` | `#F3EBF6` | `rgb(243, 235, 246)` | 1.17:1 | 1.1:1 | background only |
| `color.violet.100` | `#E0C9E8` | `rgb(224, 201, 232)` | 1.53:1 | 1.44:1 | background only |
| `color.violet.500` | `#7A3B8F` | `rgb(122, 59, 143)` | 7.37:1 | 6.93:1 | normal text — 4.5:1 |
| `color.violet.600` | `#5E2D6E` | `rgb(94, 45, 110)` | 10.11:1 | 9.52:1 | normal text — 4.5:1 |
| `color.violet.700` | `#46224F` | `rgb(70, 34, 79)` | 13.11:1 | 12.33:1 | normal text — 4.5:1 |

## 4. Semantic colours

A semantic token binds a meaning to a primitive. **This is the only
layer where the meaning of a colour may be changed**, and it is the
layer a component specification names.

| Token | Resolves to | Hex | On page | Target | Meaning |
|---|---|---|---:|---|---|
| `color.semantic.accent` | `color.gold.400` | `#C79A2B` | 2.6:1 | decorative — exempt | Restrained brand accent: a thin rule, a small badge fill, a wordmark flourish |
| `color.semantic.accent.strong` | `color.gold.600` | `#8A6710` | 5.21:1 | normal text — 4.5:1 | The only gold permitted to carry text or a meaning-bearing boundary |
| `color.semantic.border` | `color.neutral.500` | `#737D8C` | 4.17:1 | interactive boundary — 3:1 | The default border of an interactive control |
| `color.semantic.border.interactive` | `color.neutral.500` | `#737D8C` | 4.17:1 | interactive boundary — 3:1 | The resting boundary of every interactive control: inputs, checkboxes, radios, switches, bordered buttons |
| `color.semantic.border.strong` | `color.neutral.700` | `#3E4756` | 9.37:1 | interactive boundary — 3:1 | A heavier boundary for emphasis: a focused table cell, a selected card outline, a high-attention container |
| `color.semantic.border.subtle` | `color.neutral.300` | `#C4CAD3` | 1.65:1 | decorative — exempt | Decorative dividers and non-meaning-bearing separators |
| `color.semantic.conflict` | `color.violet.500` | `#7A3B8F` | 7.37:1 | normal text — 4.5:1 | Local and server state disagree and a human must decide |
| `color.semantic.conflict.subtle` | `color.violet.100` | `#E0C9E8` | 1.53:1 | background only | A quieter conflict fill for a row that needs review without shouting across the whole table |
| `color.semantic.conflict.surface` | `color.violet.50` | `#F3EBF6` | 1.17:1 | background only | Background of the conflict panel |
| `color.semantic.danger` | `color.red.500` | `#B3261E` | 6.54:1 | normal text — 4.5:1 | Destructive and failed states: void, refund, failed delivery, failed sync, validation error |
| `color.semantic.danger.surface` | `color.red.50` | `#FCEBEA` | 1.15:1 | background only | Background of error banners and danger status chips |
| `color.semantic.disabled` | `color.neutral.400` | `#9AA3B0` | 2.55:1 | inactive component — exempt (SC 1.4.3) | Inactive controls and their labels |
| `color.semantic.focus` | `color.blue.700` | `#0A4F8F` | 8.31:1 | interactive boundary — 3:1 | The focus ring |
| `color.semantic.information` | `color.blue.600` | `#1F5E96` | 6.77:1 | normal text — 4.5:1 | Neutral informational messages and help text that must be noticed |
| `color.semantic.information.surface` | `color.blue.100` | `#DBE9F6` | 1.24:1 | background only | Background of informational banners |
| `color.semantic.neutral` | `color.neutral.600` | `#566070` | 6.36:1 | normal text — 4.5:1 | Neutral status and secondary metadata that must remain readable |
| `color.semantic.neutral.subtle` | `color.neutral.100` | `#EFF1F4` | 1.13:1 | background only | A quiet neutral fill: a metadata chip, an inactive tab, a zebra table row |
| `color.semantic.offline` | `color.neutral.700` | `#3E4756` | 9.37:1 | normal text — 4.5:1 | The device has no usable connection |
| `color.semantic.primary` | `color.blue.700` | `#0A4F8F` | 8.31:1 | normal text — 4.5:1 | Primary actions, active navigation, primary buttons, focused field borders |
| `color.semantic.primary.hover` | `color.blue.800` | `#083D70` | 10.98:1 | normal text — 4.5:1 | Hover and pressed treatment for primary actions on pointer devices |
| `color.semantic.primary.pressed` | `color.blue.900` | `#0A2540` | 15.54:1 | normal text — 4.5:1 | The pressed state of a primary action, one step darker than the hover treatment |
| `color.semantic.primary.surface` | `color.blue.50` | `#F0F6FC` | 1.09:1 | background only | Tinted surface behind primary-flavoured content, selected rows, and active navigation items |
| `color.semantic.secondary` | `color.blue.500` | `#2A6FA9` | 5.32:1 | normal text — 4.5:1 | Secondary emphasis, links inside dense content, secondary buttons |
| `color.semantic.selected` | `color.blue.600` | `#1F5E96` | 6.77:1 | normal text — 4.5:1 | Selection state for rows, chips, list items and segmented controls |
| `color.semantic.selected.surface` | `color.blue.100` | `#DBE9F6` | 1.24:1 | background only | Background fill of a selected row or chip |
| `color.semantic.success` | `color.green.500` | `#0F7A3D` | 5.42:1 | normal text — 4.5:1 | Successful completion: payment recorded, sync acknowledged by the server, QC passed |
| `color.semantic.success.surface` | `color.green.50` | `#E8F5EC` | 1.12:1 | background only | Background of success banners and success status chips |
| `color.semantic.surface` | `color.neutral.0` | `#FFFFFF` | 1.0:1 | background only | The default surface |
| `color.semantic.surface.inverse` | `color.neutral.900` | `#171C24` | 17.1:1 | background only | An inverted surface: tooltips, snackbars, and the dark app bar variant |
| `color.semantic.surface.page` | `color.neutral.0` | `#FFFFFF` | 1.0:1 | background only | Canonical page background for the light theme |
| `color.semantic.surface.raised` | `color.neutral.50` | `#F7F8FA` | 1.06:1 | background only | Cards, sheets and raised containers |
| `color.semantic.surface.sunken` | `color.neutral.100` | `#EFF1F4` | 1.13:1 | background only | Sunken wells, table header rows, and inactive tab strips |
| `color.semantic.syncing` | `color.teal.500` | `#0F6E7B` | 5.94:1 | normal text — 4.5:1 | A queued operation is currently being sent to the server |
| `color.semantic.syncing.surface` | `color.teal.50` | `#E6F3F5` | 1.13:1 | background only | Background of the sync indicator chip |
| `color.semantic.text.disabled` | `color.neutral.400` | `#9AA3B0` | 2.55:1 | inactive component — exempt (SC 1.4.3) | The label of an inactive control |
| `color.semantic.text.inverse` | `color.neutral.0` | `#FFFFFF` | 1.0:1 | normal text — 4.5:1 | Text placed on color |
| `color.semantic.text.onPrimary` | `color.neutral.0` | `#FFFFFF` | 1.0:1 | normal text — 4.5:1 | Text and icons placed on color |
| `color.semantic.text.primary` | `color.neutral.900` | `#171C24` | 17.1:1 | normal text — 4.5:1 | Primary body and heading text |
| `color.semantic.text.secondary` | `color.neutral.700` | `#3E4756` | 9.37:1 | normal text — 4.5:1 | Secondary text, metadata, timestamps and helper text |
| `color.semantic.warning` | `color.amber.500` | `#9A5B00` | 5.43:1 | normal text — 4.5:1 | Attention needed but not yet failed: approaching a limit, order ageing, reminder due |
| `color.semantic.warning.surface` | `color.amber.50` | `#FDF3E4` | 1.1:1 | background only | Background of warning banners and warning status chips |

## 5. The focus ring

`color.semantic.focus` resolves to `#0A4F8F` and measures **8.31:1** against the page surface, clearing the 3:1 interactive-boundary target.

It is rendered as a 2px outline with a 2px offset (`border.width.focus`, `border.focus.offset`).

**The focus indicator is never removed, never set to `none`, and never
reduced below 3:1.** Not for aesthetics, not because a design reads
more cleanly without it, not on any surface. A keyboard user who
cannot see where they are cannot use the product at all.

## 6. Interactive boundaries

`color.semantic.border.interactive` resolves to `#737D8C` and measures **4.17:1**, clearing the 3:1 boundary target (WCAG 2.2 SC 1.4.11).

`color.semantic.border.subtle` is deliberately **decorative-exempt**:
it draws dividers that carry no meaning. It is never the boundary of
an interactive control, because a control whose edge a user cannot see
is a control they cannot find.

## 7. The gold constraint

Gold is a **restrained accent**, and the token set enforces that
structurally rather than asking designers to remember it.

- `color.gold.400` (`#C79A2B`) measures 2.6:1 and is **decorative-exempt**. It may fill a small badge or draw a thin rule. It is **never** body text, **never** the sole indicator of a warning, **never** dominant, and **never** a meaning-bearing boundary.
- `color.semantic.accent.strong` (`#8A6710`) measures 5.21:1 and is the **only** gold permitted to carry text or a meaning-bearing boundary.

**Warning semantics use amber, never gold.** The two are deliberately
different hues so that "premium accent" and "something needs your
attention" can never be confused.

## 8. Disabled and inactive

`color.semantic.disabled` resolves to `#9AA3B0` and measures 2.55:1 — below 4.5:1 **by design**. WCAG 2.2 SC 1.4.3 exempts inactive components.

The exemption is not a licence to be unhelpful: **a disabled control is
never the only signal that something is unavailable.** The reason is
always available to the user, because a greyed-out button with no
explanation is a dead end at a counter with a customer waiting.

## 9. Theme scope

The **light theme is the canonical MVP theme** (DEC-0019). Every ratio
in this document is a light-theme ratio.

**Dark mode is `PLANNED` and `NOT IMPLEMENTED`.** There is no
`semantic-dark.json`, none may be claimed, and a dark theme would need
its own full contrast pass — the ratios here would not carry over.

## 10. Related

- [`tokens/README.md`](tokens/README.md) — the token layer model
- [`ACCESSIBILITY.md`](ACCESSIBILITY.md) — the accessibility foundation
- [`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md) — the Bahasa Indonesia labels that accompany every status colour
- [Rule 26 — Design Token Governance](../../.claude/rules/26-design-token-governance.md)
- [Rule 27 — Accessibility Foundation](../../.claude/rules/27-accessibility-foundation.md)

