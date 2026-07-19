# Iconography — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY. No icon asset is committed.

---

## 1. Icon policy

**No icon binary, SVG asset, or icon font is committed to this repository in Step 2.**

No `.svg`, no `.png`, no icon font file, no sprite sheet. This document specifies **which icon concept
means what**. It does not ship the glyphs.

### Source strategy

The product uses a **single open-licensed outline icon set**, selected in the Step that builds
`packages/design_system`. Selection criteria, recorded now so the choice is not made arbitrarily later:

1. Permissive licence (Apache 2.0, MIT, or equivalent) compatible with a PUBLIC repository.
2. Consistent 24 dp grid and consistent optical weight across the set.
3. Coverage of every concept in §5 without requiring custom drawing.
4. Available as vector paths, so tinting uses `currentColor` rather than baked colour.
5. Renders acceptably at 16 dp on a low-density display.

The set is **not selected in Step 2**. Recorded as `DEBT-003` in
[`DESIGN_DEBT_REGISTER.md`](DESIGN_DEBT_REGISTER.md). Selecting it is a Step 2-adjacent decision that
requires evaluating actual rendering, which requires a runtime — and Step 2 has none.

### Rules for the set once selected

- **One set, no mixing.** Two icon families in one product read as inconsistency, and their optical
  weights never match.
- **Outline style, not filled**, as the default. Filled variants are permitted only for a selected
  bottom-navigation destination and for a filled status badge's inline glyph.
- **No custom icon** without a recorded design decision. A concept without a glyph in the set is a
  signal that the concept needs rethinking, not drawing.
- **No brand or vendor logos as icons** — no WhatsApp mark, no payment-provider mark, no map-provider
  mark — without confirming the provider's trademark terms. That confirmation is not Step 2's to give
  (`DESIGN_DECISION_LOG.md` §4).
- **No emoji as a UI icon**, anywhere. Emoji render differently on every platform, carry unintended
  tone, and are unreliable for screen readers.
- **No flags, no currency symbols as glyphs, no human figures.**

---

## 2. Sizes and stroke

| Token | Size | Stroke | Use |
|---|---|---|---|
| `size.icon.xs` | 16 dp | 1.5 dp | Inline in `body.sm`; table cell affordance; chip glyph |
| `size.icon.sm` | 20 dp | 1.5 dp | Inline in `body.md`; status badge glyph |
| `size.icon.md` | 24 dp | 2 dp | **Default.** App bar, list leading, button glyph, form affordance |
| `size.icon.lg` | 32 dp | 2 dp | Navigation rail; prominent status; courier surface actions |
| `size.icon.xl` | 48 dp | 2 dp | Empty state, error state, offline state |

### Rules

1. **Stroke weight is optical, not literal.** A 16 dp icon drawn with a 2 dp stroke reads as a blob;
   1.5 dp is the floor. Below 16 dp no icon is used at all.
2. **Icons align to the 4 pt grid** and are optically centred within their container. Where a glyph's
   bounding box is off-centre, a sub-4 optical offset is permitted and documented per component
   (`SPACING_SIZING_DENSITY.md` §1).
3. **Icon colour is inherited**, expressed as `currentColor`. An icon in a danger banner is
   `color.semantic.danger` because its text is; the icon does not carry its own baked colour.
4. **A meaningful icon meets 3:1** against its background (`COLOR_AND_CONTRAST.md` §1). A decorative
   icon has no contrast requirement — but if it is decorative, it must also be hidden from screen
   readers.
5. **Icon size is not touch target.** Every interactive icon resolves a 48 × 48 dp target
   (`SPACING_SIZING_DENSITY.md` §4).

---

## 3. The icon-never-alone rule

**An icon is never the sole carrier of meaning.**

This follows directly from Master Source §18.2 rule 2 and extends it: colour is not enough, and icon
plus colour is still not enough. **Text is the carrier.**

### Binding rules

1. **Every status presentation carries a text label.** An icon-only status is prohibited in every
   surface.
2. **Every icon-only button carries an accessible name** and, on pointer devices, a tooltip. The
   accessible name is a real Indonesian label ("Hapus lampiran"), not the glyph's name ("trash").
3. **Icon-only buttons are permitted only for universally understood, low-consequence actions** —
   close, back, search, more, edit. They are **prohibited** for destructive actions, financial actions,
   and any action whose consequence is not obvious from context. "Batalkan Pesanan" is never a bare
   icon.
4. **Decorative icons are hidden from assistive technology.** If a check icon sits beside the word
   "Lunas", the icon is decorative and must not be announced — otherwise the user hears
   "checkmark Lunas".
5. **An icon never replaces a number.** Three star glyphs is not a rating of 3; "3 dari 5" is.
6. **An icon never encodes a chart series** on its own (`DATA_VISUALIZATION.md`).

---

## 4. Semantic icon mapping — order statuses

Icons are named by **concept**, not by any vendor's glyph name. The selected set supplies the glyph.
Labels come from [`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md); colours from
[`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md) §8.

Silhouettes are chosen to be **distinguishable in greyscale and at 16 dp**, which is the real test.

| Status | Icon concept | Silhouette note | Colour token |
|---|---|---|---|
| `DRAFT` | document-outline | Rectangle with folded corner | `color.semantic.neutral` |
| `RECEIVED` | inbox-arrow-down | Downward arrow into a tray | `color.semantic.information` |
| `AWAITING_PROCESS` | hourglass | Distinct waisted shape | `color.semantic.neutral` |
| `SORTING` | layers-split | Two diverging stacks | `color.semantic.information` |
| `WASHING` | washing-machine | Circle inside a square — unmistakable | `color.semantic.information` |
| `DRYING` | wind / air-flow | Horizontal flowing lines | `color.semantic.information` |
| `FINISHING` | iron | Flat-iron profile | `color.semantic.information` |
| `QUALITY_CONTROL` | magnifier-check | Magnifier with an internal tick | `color.semantic.information` |
| `REWORK` | arrow-loop-back | Circular arrow, counter-clockwise | `color.semantic.warning` |
| `READY_FOR_PICKUP` | check-circle | Solid circular outline with a tick — the product's most important glyph | `color.semantic.success` |
| `SCHEDULED_FOR_DELIVERY` | calendar-clock | Calendar with a clock overlay | `color.semantic.information` |
| `OUT_FOR_DELIVERY` | truck | Vehicle profile | `color.semantic.information` |
| `COMPLETED` | check-double | Two ticks — distinct from single-tick `READY_FOR_PICKUP` | `color.semantic.success` |
| `CANCELLED` | circle-slash | Circle with a diagonal bar | `color.semantic.neutral` |
| `ISSUE` | alert-triangle | Triangle — the only triangular status glyph | `color.semantic.danger` |

**Distinguishability note:** `READY_FOR_PICKUP` (single tick in a circle) and `COMPLETED` (double tick)
are the pair most likely to be confused. They are separated by silhouette, by label ("Siap Diambil" vs
"Selesai"), and by position in the timeline. They deliberately share a colour because they are both
successful outcomes.

---

## 5. Semantic icon mapping — pickup, delivery, QC, and system states

### 5.1 Pickup and delivery

| Status | Icon concept | Colour token |
|---|---|---|
| `REQUESTED` | hand-raised | `color.semantic.neutral` |
| `CONFIRMED` | check-badge | `color.semantic.information` |
| `SCHEDULED` | calendar | `color.semantic.information` |
| `ASSIGNED` | user-arrow | `color.semantic.information` |
| `EN_ROUTE` | navigation-arrow | `color.semantic.information` |
| `ARRIVED` | map-pin | `color.semantic.information` |
| `PICKED_UP` | box-arrow-up | `color.semantic.success` |
| `DELIVERED` | box-check | `color.semantic.success` |
| `FAILED` | box-x | `color.semantic.danger` |
| `RESCHEDULED` | calendar-arrow | `color.semantic.warning` |
| `CANCELLED` | circle-slash | `color.semantic.neutral` |

The `navigation-arrow` glyph for `EN_ROUTE` is a **direction indicator, not a route claim.** It is
never paired with copy implying an optimal route or a guaranteed arrival time (Rule 09, rule 1).

### 5.2 Quality control

| Status | Icon concept | Colour token |
|---|---|---|
| `PENDING` | hourglass | `color.semantic.neutral` |
| `PASSED` | shield-check | `color.semantic.success` |
| `FAILED_REWORK_REQUIRED` | arrow-loop-back | `color.semantic.warning` |
| `WAIVED_WITH_AUTHORIZATION` | key-check | `color.semantic.information` |

`WAIVED_WITH_AUTHORIZATION` always renders alongside the authorising actor and the recorded reason.
The key glyph signals that a permission was exercised — it never stands alone (Rule 19).

### 5.3 Payment states

| State | Icon concept | Colour token |
|---|---|---|
| `PAYMENT_PENDING` | wallet-alert | `color.semantic.warning` |
| `PAYMENT_PARTIAL` | wallet-half | `color.semantic.warning` |
| `PAYMENT_SETTLED` | wallet-check | `color.semantic.success` |
| `PAYMENT_REVERSED` | arrow-u-turn-left | `color.semantic.neutral` |

### 5.4 Sync and connectivity states

| State | Icon concept | Motion | Colour token |
|---|---|---|---|
| `OFFLINE` | cloud-slash | static | `color.semantic.offline` |
| `SYNC_PENDING` | cloud-arrow-up | static | `color.semantic.syncing` |
| `SYNC_IN_PROGRESS` | arrows-circular | rotating, respects reduced motion | `color.semantic.syncing` |
| `SYNC_FAILED` | cloud-x | static | `color.semantic.danger` |
| `SYNC_CONFLICT` | alert-diamond | static | `color.semantic.conflict` |

`SYNC_CONFLICT` uses a **diamond**, deliberately distinct from `ISSUE`'s triangle and from any circle.
Conflict is the state where a human must choose; it must be findable at a glance in a list of thirty
queued operations.

### 5.5 Aging ladder

| Band | Icon concept | Colour token |
|---|---|---|
| Under H+1 | none | — |
| H+1 to H+2 | clock | `color.semantic.information` |
| H+3 to H+6 | clock-alert | `color.semantic.warning` |
| H+7 to H+13 | clock-alert + task glyph | `color.semantic.warning` |
| H+14 and beyond | arrow-up-escalate | `color.semantic.danger` |

The age is always rendered as text ("H+9") beside the glyph. No icon in this ladder — or anywhere —
implies disposal, sale, donation, or transfer of a customer's belongings; those are prohibited outright
(Rule 10).

### 5.6 Privacy and security

| Concept | Icon concept | Usage note |
|---|---|---|
| Masked data | eye-slash | Beside a masked phone or partial address |
| Reveal (authorised) | eye | Only where an authorised reveal path genuinely exists |
| Private file | lock | On proof photographs and signed-URL content |
| Expiring link | clock-lock | On tracking and guest-courier links |
| Revoked | link-slash | On a revoked tracking or guest link |
| Tenant boundary | building | In the tenant switcher |
| Impersonation active | user-shield | Persistent, non-dismissible, on every screen during support impersonation |

The impersonation indicator is a hard requirement: platform support has **no silent tenant access**
(Rule 03, rule 19). Its icon is always accompanied by text naming the tenant and the session, and it
cannot be dismissed.

---

## 6. Common action icons

| Action | Icon concept | Icon-only permitted |
|---|---|---|
| Back | arrow-left | Yes |
| Close | x | Yes |
| Search | magnifier | Yes |
| More options | dots-vertical | Yes |
| Add | plus | Yes |
| Edit | pencil | Yes |
| Filter | funnel | Yes |
| Sort | arrows-up-down | Yes |
| Refresh / retry | arrow-circular | Yes |
| Print | printer | Yes, with tooltip |
| Share | share | Yes, with tooltip |
| Attach | paperclip | Yes, with tooltip |
| Camera | camera | Yes, with tooltip |
| Signature | pen-line | **No** — proof action, needs a label |
| Delete attachment | trash | **No** — destructive |
| Cancel order | circle-slash | **No** — destructive |
| Refund | arrow-u-turn-left | **No** — financial |
| Void | ban | **No** — financial |
| Approve discount | badge-check | **No** — financial and permissioned |
| Revoke access | link-slash | **No** — security |
| Export | download | **No** — carries tenant data; needs a label |

---

## 7. Prohibited icon practices

| Prohibited | Reason |
|---|---|
| Committing an icon binary or icon font in Step 2 | §1; Step 2 is documentation only |
| Mixing two icon families | Inconsistent optical weight |
| Emoji as a UI icon | Cross-platform variance; screen-reader unreliability; tone |
| An icon as the sole carrier of a status | §3; Master Source §18.2 rule 2 |
| An icon-only destructive or financial action | §3 rule 3 |
| An icon-only button with no accessible name | §3 rule 2 |
| A decorative icon announced by a screen reader | §3 rule 4 |
| An icon below 16 dp | §2 rule 1 |
| A meaningful icon below 3:1 contrast | `COLOR_AND_CONTRAST.md` §1 |
| Baked colour inside an icon asset | §2 rule 3; prevents semantic tinting |
| An icon implying route optimization or a delivery guarantee | Rule 09, rule 1 |
| An icon implying automatic disposal or sale of laundry | Rule 10 |
| A vendor logo used without confirmed trademark terms | §1 |
| A custom-drawn icon with no recorded decision | §1 |
| Animating an icon for decoration | `MOTION_AND_REDUCED_MOTION.md` |
