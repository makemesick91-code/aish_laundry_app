# Spacing, Sizing, and Density — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY

---

## 1. The 4 pt grid

All spacing and sizing derive from a **4 pt base unit**. Every margin, padding, gap, and dimension is a
multiple of 4.

Why 4 and not 8: the product needs 44 and 52 dp control heights, 12 dp gaps inside dense table rows, and
20 dp inset variants. An 8 pt grid forces those to round to values that are either wasteful or cramped.
4 pt gives the needed resolution while still being a grid.

**The grid is not a suggestion.** A value of 15, 18, or 25 is a defect, not a refinement. If a layout
appears to need 15, it needs 16 and a different composition.

Two exceptions, both explicit:

1. **Hairline borders** may be 1 dp or 0.5 dp (`SHAPE_BORDER_ELEVATION.md`) — a border is a line, not a
   space.
2. **Optical alignment of an icon inside a control** may use a sub-4 offset, documented per component,
   where the glyph's own bounding box is off-centre.

---

## 2. Spacing scale

| Token | Value | Typical use |
|---|---|---|
| `space.0` | 0 dp | Explicit zero — collapsing a default gap |
| `space.1` | 4 dp | Icon-to-label inside a chip or badge; hairline insets |
| `space.2` | 8 dp | Gap between tightly related elements; compact table cell padding |
| `space.3` | 12 dp | Icon-to-label in a button; gap between form field and its helper text |
| `space.4` | 16 dp | **Default.** Screen horizontal margin on compact; card padding; gap between list items |
| `space.5` | 20 dp | Card padding at comfortable density |
| `space.6` | 24 dp | Gap between form fields; section inner padding; screen margin on medium |
| `space.8` | 32 dp | Gap between sections within a screen; screen margin on expanded |
| `space.10` | 40 dp | Gap between major content blocks |
| `space.12` | 48 dp | Gap before a terminal action region; large section separation |
| `space.16` | 64 dp | Vertical rhythm in an empty state; dashboard block separation |
| `space.20` | 80 dp | Top offset for a centred empty state |
| `space.24` | 96 dp | Maximum standard spacing; hero regions on wide breakpoints |

`space.4` (16 dp) is the default. A designer reaching for a value should reach for 16 first and justify
anything else.

### Relationship spacing rule

Spacing communicates relationship. Elements that belong together are closer than elements that do not,
and the difference must be at least one full step on the scale.

| Relationship | Gap |
|---|---|
| Inside a single element (icon to its label) | `space.1` – `space.2` |
| Between tightly coupled elements (label to its field) | `space.2` |
| Between peer elements (two form fields) | `space.6` |
| Between groups (two form sections) | `space.8` |
| Between major regions (content to action bar) | `space.10` – `space.12` |

A layout where a label is 16 dp from its own field and 16 dp from the next field is ambiguous and is a
defect, regardless of how even it looks.

---

## 3. Sizing scale

### 3.1 Control heights

| Token | Value | Use |
|---|---|---|
| `size.control.xs` | 32 dp | Dense table inline control on Console Web pointer-only regions — **never on Android** |
| `size.control.sm` | 40 dp | Compact-density Console controls, secondary chips |
| `size.control.md` | 48 dp | **Default.** All Android controls; standard-density Console controls |
| `size.control.lg` | 56 dp | Primary actions on Android; all courier-surface controls |
| `size.control.xl` | 64 dp | Courier proof-capture actions; single dominant action on a confirmation screen |

### 3.2 Icon sizes

| Token | Value | Use |
|---|---|---|
| `size.icon.xs` | 16 dp | Inline in `body.sm`, table cell affordances |
| `size.icon.sm` | 20 dp | Inline in `body.md`, chip icons, status badge icons |
| `size.icon.md` | 24 dp | **Default.** App bar actions, list leading icons, button icons |
| `size.icon.lg` | 32 dp | Navigation rail, prominent status |
| `size.icon.xl` | 48 dp | Empty-state and error-state illustration glyphs |

An icon's **size is not its touch target.** A 24 dp icon inside an icon button still occupies a
48 × 48 dp target (§4).

### 3.3 Avatar sizes

| Token | Value | Use |
|---|---|---|
| `size.avatar.sm` | 32 dp | Table row, dense list |
| `size.avatar.md` | 40 dp | Standard list item |
| `size.avatar.lg` | 56 dp | Detail header |

### 3.4 Layout dimensions

| Token | Value | Use |
|---|---|---|
| `size.appbar.height` | 56 dp | Android app bar |
| `size.appbar.height.web` | 64 dp | Console Web top bar |
| `size.bottomnav.height` | 64 dp | Android bottom navigation (3–5 destinations) |
| `size.navrail.width` | 80 dp | Tablet navigation rail |
| `size.sidenav.width` | 264 dp | Console Web side navigation, expanded |
| `size.sidenav.width.collapsed` | 72 dp | Console Web side navigation, collapsed |
| `size.maxWidth.content` | 1200 dp | Maximum content column width on wide breakpoints |
| `size.maxWidth.reading` | 640 dp | Maximum width of a running-text column |
| `size.dialog.width` | 560 dp | Standard dialog on medium and above |
| `size.dialog.width.sm` | 400 dp | Confirmation dialog |
| `size.bottomsheet.max` | 640 dp | Bottom sheet maximum width when centred on a large screen |
| `size.row.compact` | 40 dp | Data table row, compact density |
| `density.standard.rowHeight` | 48 dp | Data table row, standard density |
| `density.comfortable.rowHeight` | 56 dp | Data table row, comfortable density |

---

## 4. Touch targets — 48 × 48 dp minimum

**Every interactive element occupies a minimum touch target of 48 × 48 dp.** This is a floor, not a
target, and it holds in every density, at every text scale, on every surface.

### Rules

1. **The target may exceed the visual element.** A 24 dp icon may render inside a 48 dp transparent
   target. The visual can be small; the target cannot.
2. **Minimum spacing between adjacent targets is `space.2` (8 dp).** Adjacent 48 dp targets with no gap
   produce mis-taps at a busy counter.
3. **Density never reduces the target.** Compact density reduces padding and row height for *display*,
   but an interactive control inside a compact row still resolves a 48 dp target, overflowing the row
   bounds if necessary.
4. **Text scaling never reduces the target.** At 200%, targets grow or hold; they never shrink.
5. **Courier surfaces use 56 dp minimum, and 64 dp for proof capture.** Rule 09, rule 8: couriers
   operate one-handed, outdoors, in a hurry, sometimes in the rain.
6. **Destructive actions use a 56 dp minimum** and are spatially separated by at least `space.8`
   (32 dp) from the nearest routine action (`DESIGN_PRINCIPLES.md` P5).
7. **Pointer-only exception:** `size.control.xs` (32 dp) is permitted on Console Web **only** in a
   region that is unreachable by touch on the supported configuration, and **only** where a keyboard
   route to the same action exists. It is never permitted on Android, never on the tracking portal, and
   never for a destructive or financial action.

### Prohibited

- Any interactive target below 48 × 48 dp on Android or the tracking portal.
- An inline text link inside a paragraph as the *only* route to an important action — links inside
  prose cannot guarantee a 48 dp target. Provide a button.
- Overlapping targets.
- A target whose visible affordance is smaller than 16 dp — the user cannot find what they cannot see,
  even if the target is generous.

---

## 5. Density

Three densities. Density is assigned **by context, never by breakpoint** (`DESIGN_PRINCIPLES.md` P9).

### 5.1 Compact

**For:** high-volume data review by a seated user on a pointer device.

| Property | Value |
|---|---|
| Table row height | `size.row.compact` (40 dp) |
| Cell padding | `space.2` (8 dp) vertical, `space.3` (12 dp) horizontal |
| Control height | `size.control.sm` (40 dp) |
| Card padding | `space.3` (12 dp) |
| Gap between fields | `space.4` (16 dp) |
| Body text | `font.size.body.sm` |
| Interactive touch target | still 48 dp, resolved by overflow |

**Permitted on:** Console Web data tables, receivables lists, reporting grids, audit timelines,
platform administration lists.

**Prohibited on:** any Android surface; any courier surface; any confirmation, payment, refund, or
destructive-action surface; the public tracking portal; any view above 130% text scaling.

### 5.2 Standard

**For:** everyday operation. The default for the whole product.

| Property | Value |
|---|---|
| Table row height | `density.standard.rowHeight` (48 dp) |
| Cell padding | `space.3` (12 dp) vertical, `space.4` (16 dp) horizontal |
| Control height | `size.control.md` (48 dp) |
| Card padding | `space.4` (16 dp) |
| Gap between fields | `space.6` (24 dp) |
| Body text | `font.size.body.md` |

**Permitted on:** everything. This is the default and requires no justification.

### 5.3 Comfortable

**For:** users under physical or time pressure, and for consequential decisions.

| Property | Value |
|---|---|
| Table row height | `density.comfortable.rowHeight` (56 dp) |
| Cell padding | `space.4` (16 dp) vertical, `space.5` (20 dp) horizontal |
| Control height | `size.control.lg` (56 dp) |
| Card padding | `space.5` (20 dp) |
| Gap between fields | `space.8` (32 dp) |
| Body text | `font.size.body.lg` |

**Required on:** all courier surfaces (pickup, delivery, proof capture, cash handover); payment
confirmation; refund and void; shift closing; every destructive confirmation dialog; the public
tracking portal's primary status region.

**Rationale for the tracking portal:** it is opened by an unknown person on an unknown device to answer
one question. Density there buys nothing and costs comprehension.

### 5.4 Density selection

| Context | Density |
|---|---|
| Ops Android — order intake | Standard |
| Ops Android — production job list | Standard |
| Ops Android — courier job, proof capture, cash | **Comfortable** |
| Ops Android — payment and refund | **Comfortable** |
| Ops Android — shift closing | **Comfortable** |
| Customer Android — all | Standard |
| Console Web — dashboards | Standard |
| Console Web — data tables, reports, audit | **Compact** |
| Console Web — finance actions, refunds, subscription changes | **Comfortable** |
| Console Web — configuration forms | Standard |
| Tracking Portal — status region | **Comfortable** |
| Tracking Portal — history timeline | Standard |
| Any surface above 130% text scaling | Standard minimum; compact prohibited |

---

## 6. Layout margins by breakpoint

Breakpoints are defined in [`RESPONSIVE_FOUNDATION.md`](RESPONSIVE_FOUNDATION.md).

| Breakpoint | Screen margin | Content max width | Grid columns | Gutter |
|---|---|---|---|---|
| Compact (< 600) | `space.4` (16 dp) | full width | 4 | `space.4` (16 dp) |
| Medium (600–1023) | `space.6` (24 dp) | full width | 8 | `space.6` (24 dp) |
| Expanded (1024–1439) | `space.8` (32 dp) | `size.maxWidth.content` (1200 dp) | 12 | `space.6` (24 dp) |
| Wide (≥ 1440) | `space.8` (32 dp), content centred | `size.maxWidth.content` (1200 dp) | 12 | `space.8` (32 dp) |

At the wide breakpoint the content column **is centred and capped**, not stretched. A 2560 px monitor
does not get 2560 px of table. Running-text columns cap at `size.maxWidth.reading` (640 dp) regardless of
available width.

---

## 7. Vertical rhythm

| Region | Spacing |
|---|---|
| App bar to first content | `space.4` |
| Section heading to its content | `space.3` |
| Content to next section heading | `space.8` |
| Between form fields | `space.6` |
| Between form sections | `space.8` |
| Last field to action bar | `space.10` |
| Between list items | `space.0` (separated by a border, not a gap) |
| Between cards in a list | `space.3` |
| Card content to card edge | `space.4` (standard density) |
| Above a bottom action bar | `space.4` internal padding, plus safe-area inset |

**Safe areas:** every Android layout respects the system gesture inset, notch, and navigation-bar
inset. A bottom action bar adds the safe-area inset to its own padding rather than sitting under the
gesture bar.

---

## 8. Prohibited spacing practices

| Prohibited | Reason |
|---|---|
| A spacing value not on the 4 pt grid | §1 |
| Negative margin to correct a spacing mistake | Hides the real problem and breaks at other text scales |
| Fixed pixel heights on text-bearing containers | Breaks at 200% text scaling (`TYPOGRAPHY.md` §5) |
| Equal spacing between unrelated and related elements | Destroys grouping (§2) |
| Compact density on any Android surface | §5.1 |
| Compact density on any financial confirmation | §5.1 |
| A touch target below 48 × 48 dp on Android or the portal | §4 |
| Reducing a touch target to fit a density or a text scale | §4 rules 3 and 4 |
| A destructive action within `space.8` of a routine action | §4 rule 6 |
| Stretching a content column beyond `size.maxWidth.content` | §6 |
| Spacing that depends on a specific string length | Font fallback variance (`TYPOGRAPHY.md` §1) |
