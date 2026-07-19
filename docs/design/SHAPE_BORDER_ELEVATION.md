# Shape, Border, and Elevation — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY
**Theme:** Light theme is canonical for MVP. Dark theme is PLANNED / NOT IMPLEMENTED — no dark-theme
shadow value is specified here.

---

## 1. Shape philosophy

The brand is **clean, professional, light, not futuristic, not luxurious**
(`BRAND_FOUNDATION.md` §2). Shape carries that:

- **Moderate radii.** Not sharp corners (severe, dated) and not pill-shaped everything (playful,
  consumer-app). A restrained 8 dp default reads as competent business software.
- **Borders over shadows.** Separation is achieved with a hairline border first, elevation second.
  Borders cost nothing to render and survive being viewed in sunlight on a cheap display; soft shadows
  disappear under both conditions.
- **Flat by default.** Elevation is spent, not scattered. Most surfaces in the product are flat.

---

## 2. Radius scale

| Token | Value | Use |
|---|---|---|
| `radius.none` | 0 dp | Full-bleed regions; table cells; app bar; receipt preview edges |
| `radius.xs` | 4 dp | Small chips, tags, inline badges, progress bar ends |
| `radius.sm` | 6 dp | Text fields, dropdowns, small buttons, checkboxes (outer) |
| `radius.md` | 8 dp | **Default.** Buttons, cards, banners, tooltips, menus |
| `radius.lg` | 12 dp | Dialogs, large cards, KPI cards, panels |
| `radius.xl` | 16 dp | Bottom sheets (top corners only), modal sheets |
| `radius.full` | 9999 dp | Status badges, avatars, floating action button, switch track, radio |

### Rules

1. **A nested element's radius is smaller than its parent's**, ideally by one step. A card at
   `radius.lg` (12) containing an image at `radius.md` (8) reads correctly. Equal radii on nested
   elements look like a rendering error.
2. **`radius.full` is reserved.** Only status badges, avatars, the FAB, switch tracks, and radio
   controls. A pill-shaped primary button is prohibited — it reads as consumer-app and conflicts with
   the professional attribute.
3. **Bottom sheets round only their top corners.** `radius.xl` top-left and top-right, `radius.none`
   bottom, because the bottom edge meets the screen edge.
4. **Full-bleed regions use `radius.none`.** A card that touches both screen edges on a compact
   breakpoint does not round — it becomes a section.
5. **Radius does not change with density.** Density changes spacing and heights, not shape.
6. **Radius does not scale with text scaling.** An 8 dp radius stays 8 dp at 200%.

### Prohibited

- Radius values off the scale (5, 10, 14, 20 dp).
- Asymmetric radii, except the documented bottom-sheet case.
- `radius.full` on a rectangular button.
- Radius above `radius.xl` on any container.

---

## 3. Border widths

| Token | Value | Use |
|---|---|---|
| `border.width.hairline` | 1 dp | Default. Separators, card outlines, table rules, control outlines at rest |
| `border.width.thin` | 1.5 dp | Control outline on hover (pointer devices only) |
| `border.width.thick` | 2 dp | Control outline on focus; error state outline; selected state outline |
| `border.width.heavy` | 3 dp | Focus ring outer stroke; emphasis on a courier surface in sunlight |

### Border colour rules

Contrast values are measured in [`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md).

| Purpose | Token | Contrast on white | Permitted |
|---|---|---|---|
| Decorative separator | `color.semantic.border` | 1.29:1 | Only where the boundary carries no information |
| Interactive control at rest | `color.semantic.border.interactive` | **3.37:1** | Yes — meets the 3:1 non-text threshold |
| Emphasised boundary | `color.semantic.border.strong` | **6.80:1** | Table header rules, courier surfaces |
| Focus indicator | `color.semantic.focus` | **7.86:1** | Focus only — never a resting border |
| Error boundary | `color.semantic.danger` | **6.54:1** | Always accompanied by an icon and message text |
| Selected boundary | `color.semantic.primary` | **5.79:1** | Always accompanied by a non-colour selection cue |

**The boundary of an interactive control never uses `color.semantic.border`** (1.29:1). That is a
WCAG 2.2 SC 1.4.11 failure and is prohibited outright.

### Border transitions must not shift layout

A control whose border grows from 1 dp at rest to 2 dp on focus must not move its neighbours. The
border grows inward, or the control reserves the space. A layout that jumps on focus is a defect —
it is disorienting for everyone and disabling for a keyboard user tracking their position.

---

## 4. Focus indicator

The focus indicator is specified here because it is a shape-and-border concern, and its accessibility
obligations are in [`ACCESSIBILITY.md`](ACCESSIBILITY.md) §3.

**Specification:**

- **Outer ring:** `border.width.heavy` (3 dp) in `color.semantic.focus`.
- **Inner offset:** 2 dp of `color.semantic.surface` between the control edge and the ring, so the ring
  reads against both the control fill and the page.
- **Radius:** the control's own radius plus 2 dp, so the ring follows the shape.
- **Measured contrast:** 7.86:1 on `color.semantic.surface`, 7.45:1 on `color.semantic.surface.sunken`,
  7.22:1 on `color.semantic.primary.surface`. All exceed the 3:1 requirement.

**The focus indicator can never be removed.** Not for visual preference, not for a "cleaner" look, not
on a specific component, not on a specific surface, not temporarily. There is no design authority in
this system to remove it. A component specification that omits it is incomplete, and a state matrix
cell marking focus as NOT APPLICABLE for an interactive component is an error.

Focus may be **suppressed for pointer-initiated interaction only** (a focus-visible equivalent), so a
mouse click does not leave a ring. Keyboard focus always shows. Touch focus shows whenever the control
retains focus after the touch ends.

---

## 5. Elevation

Elevation levels for the **light theme**. Shadows are specified as offset-Y / blur / spread / colour,
using `color.neutral.900` at the stated alpha.

| Token | Shadow | Use |
|---|---|---|
| `elevation.0` | none | **Default.** Flat surfaces, page background, list rows, table cells, inline cards |
| `elevation.1` | `0 1px 2px 0 rgba(18, 26, 36, 0.06)`, `0 1px 3px 0 rgba(18, 26, 36, 0.04)` | Resting card that must separate from a tinted background; app bar on scroll |
| `elevation.2` | `0 2px 4px -1px rgba(18, 26, 36, 0.08)`, `0 4px 6px -1px rgba(18, 26, 36, 0.05)` | Raised card, dropdown menu, autocomplete list, tooltip |
| `elevation.3` | `0 4px 8px -2px rgba(18, 26, 36, 0.10)`, `0 8px 16px -4px rgba(18, 26, 36, 0.06)` | Bottom sheet, drawer, popover, floating action button |
| `elevation.4` | `0 8px 16px -4px rgba(18, 26, 36, 0.12)`, `0 16px 32px -8px rgba(18, 26, 36, 0.08)` | Dialog, modal — the only level that sits above a scrim |

Every level uses a **two-layer shadow**: a tight, near-opaque layer describing the contact edge, and a
wide, faint layer describing the ambient cast. A single-layer shadow reads as a drop shadow from
1998.

Maximum alpha anywhere is **0.12**. The brand is light; a heavy shadow on a white surface reads as
dirt.

### Elevation rules

1. **Elevation is a z-order statement, not decoration.** A surface is elevated because it floats above
   another surface, not because it should look important.
2. **One elevated surface per region.** Two `elevation.2` cards side by side on a flat page do not
   communicate hierarchy — they communicate noise. Use borders.
3. **Elevation never substitutes for contrast.** A shadow is not a boundary; a control still needs
   `color.semantic.border.interactive`.
4. **A scrim accompanies `elevation.4`.** Dialogs and modals sit above a scrim of
   `color.neutral.900` at 0.32 alpha. The scrim is what makes the modal modal; the shadow only sells
   the depth.
5. **Elevation does not change on hover** except for the floating action button and draggable
   elements. A card that lifts on hover is decoration.
6. **The tracking portal uses `elevation.0` and `elevation.1` only.** Shadow rendering costs frames on
   the baseline device and the portal's whole job is to load fast.

### When elevation is prohibited

| Context | Rule |
|---|---|
| Any list row, table row, or table cell | `elevation.0`. Rows are separated by borders |
| Any element inside an already-elevated surface | A dialog's contents do not get their own shadow |
| Any full-bleed section | Full-bleed regions are structure, not floating objects |
| Any inline form field | Fields use borders, never shadows |
| Any status badge or chip | Flat, always |
| Any element on the public tracking portal above `elevation.1` | Performance (§5 rule 6) |
| Any Ops Android production or courier surface above `elevation.1` | Shadows are invisible in sunlight; borders are not |
| Nested elevation (an `elevation.2` card inside an `elevation.2` card) | Ambiguous hierarchy |
| Elevation used to indicate state (selected, active, error) | State uses colour, border, icon, and text — never depth alone |
| Any elevated element that is not actually above other content in z-order | Depth must be true |

---

## 6. Scrim and overlay

| Token | Value | Use |
|---|---|---|
| `overlay.scrim` | `color.neutral.900` at 0.32 alpha | Behind dialogs, modals, bottom sheets, drawers |
| `overlay.hover` | `color.neutral.900` at 0.04 alpha | Hover state on a list row (pointer devices) |
| `overlay.pressed` | `color.neutral.900` at 0.08 alpha | Pressed state on a surface-coloured control |
| `overlay.disabled` | `color.neutral.0` at 0.60 alpha | Rarely used; prefer explicit disabled tokens |

The scrim is **never** decorative and never used to dim content that remains interactive. If content is
behind a scrim, it is not reachable — by pointer, by touch, or by keyboard (`ACCESSIBILITY.md` §11,
focus trap).

---

## 7. Component shape assignments

| Component | Radius | Border at rest | Elevation at rest |
|---|---|---|---|
| Button, filled | `radius.md` | none (fill provides 5.79:1) | `elevation.0` |
| Button, outlined | `radius.md` | `border.width.hairline` / `color.semantic.border.interactive` | `elevation.0` |
| Button, text | `radius.md` | none | `elevation.0` |
| Floating action button | `radius.full` | none | `elevation.3` |
| Text field | `radius.sm` | `border.width.hairline` / `color.semantic.border.interactive` | `elevation.0` |
| Dropdown (closed) | `radius.sm` | `border.width.hairline` / `color.semantic.border.interactive` | `elevation.0` |
| Dropdown menu (open) | `radius.md` | `border.width.hairline` / `border` | `elevation.2` |
| Checkbox | `radius.xs` | `border.width.thick` / `color.semantic.border.interactive` | `elevation.0` |
| Radio | `radius.full` | `border.width.thick` / `color.semantic.border.interactive` | `elevation.0` |
| Switch track | `radius.full` | none | `elevation.0` |
| Chip | `radius.xs` | `border.width.hairline` / `color.semantic.border.interactive` | `elevation.0` |
| Status badge | `radius.full` | none, or hairline on subtle variants | `elevation.0` |
| Card | `radius.lg` | `border.width.hairline` / `border` | `elevation.0` or `elevation.1` |
| KPI card | `radius.lg` | `border.width.hairline` / `border` | `elevation.0` |
| Banner | `radius.md` | `border.width.hairline` in its semantic colour | `elevation.0` |
| Snackbar / toast | `radius.md` | none | `elevation.3` |
| Tooltip | `radius.md` | none | `elevation.2` |
| Bottom sheet | `radius.xl` top only | none | `elevation.3` |
| Dialog | `radius.lg` | none | `elevation.4` + scrim |
| Drawer | `radius.none` | hairline on the meeting edge | `elevation.3` + scrim |
| Avatar | `radius.full` | none | `elevation.0` |
| Data table | `radius.none` | `color.semantic.border.strong` under header, hairline between rows | `elevation.0` |
| App bar | `radius.none` | hairline bottom, or `elevation.1` on scroll | `elevation.0` |
| Bottom navigation | `radius.none` | hairline top | `elevation.0` |
| Side navigation | `radius.none` | hairline on the content edge | `elevation.0` |
| Receipt preview | `radius.none` | `border.width.hairline` / `border` | `elevation.0` |
| Photo evidence thumbnail | `radius.sm` | `border.width.hairline` / `border` | `elevation.0` |
| Skeleton block | matches the element it stands in for | none | `elevation.0` |
