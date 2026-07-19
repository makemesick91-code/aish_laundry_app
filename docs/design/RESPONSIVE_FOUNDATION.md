# Responsive Foundation — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY

---

## 1. Breakpoints

Four breakpoints, measured in logical pixels (dp on Android, CSS px on Web) of the **viewport width**.

| Token | Range | Typical device |
|---|---|---|
| `breakpoint.compact` | < 600 | Phone portrait; the public tracking portal's dominant case |
| `breakpoint.medium` | 600 – 1023 | Phone landscape, small tablet, tablet portrait |
| `breakpoint.expanded` | 1024 – 1439 | Tablet landscape, small laptop, **1366 × 768 baseline** |
| `breakpoint.wide` | ≥ 1440 | Desktop monitor |

### Rules

1. **Breakpoints are viewport-based, not device-based.** There is no "is it a tablet" check. A phone in
   landscape and a small tablet in portrait both land in `medium` and get the same layout.
2. **Layout responds to the container, not only the viewport**, wherever a component can appear in more
   than one container width. A card inside a 360 dp side panel on a 1440 px screen lays out as compact.
3. **Breakpoints do not select density.** Density is assigned by context
   (`SPACING_SIZING_DENSITY.md` §5, `DESIGN_PRINCIPLES.md` P9). A 1440 px screen showing a courier
   workflow still uses comfortable density.
4. **No layout is defined only at a breakpoint boundary.** Every layout is valid across its full range,
   including at 599 px and 600 px.
5. **Text scaling can change the effective breakpoint.** At 200% scaling a `medium` viewport may need to
   adopt the `compact` layout. Layouts must tolerate this.

Margins, content widths, grid columns, and gutters per breakpoint are in
[`SPACING_SIZING_DENSITY.md`](SPACING_SIZING_DENSITY.md) §6.

---

## 2. Layout behaviour per breakpoint

### 2.1 Compact (< 600)

- **Structure:** single column. No side-by-side content regions.
- **Navigation:** bottom navigation (3–5 destinations) on Android; a hamburger drawer on Web.
- **Screen margin:** `space.4` (16 dp).
- **Grid:** 4 columns.
- **Primary action:** bottom action bar, within thumb reach.
- **Tables:** replaced by the stacked-card pattern (§6).
- **Dialogs:** full-screen on Android for anything with more than two fields; a centred dialog only for
  simple confirmations.
- **Secondary content:** collapsed behind expanders or pushed to a detail view.
- **Detail navigation:** list and detail are **separate screens**. Tapping a list item navigates.

### 2.2 Medium (600 – 1023)

- **Structure:** single column with a wider content measure, or two columns where content genuinely
  divides.
- **Navigation:** navigation rail (`size.navrail.width`, 80 dp) on Android tablet; a collapsed side
  navigation on Web.
- **Screen margin:** `space.6` (24 dp).
- **Grid:** 8 columns.
- **Tables:** a reduced column set is permitted (§6), or the stacked-card pattern.
- **Dialogs:** centred, `size.dialog.width` (560 dp).
- **Detail navigation:** still list-then-detail. A list/detail split view is **not** introduced at
  medium; the panes would both be too narrow.

### 2.3 Expanded (1024 – 1439) — the Console baseline

- **Structure:** multi-column. List/detail split view is permitted and preferred for the Console.
- **Navigation:** persistent side navigation (`size.sidenav.width`, 264 dp), collapsible to 72 dp.
- **Screen margin:** `space.8` (32 dp); content capped at `size.maxWidth.content` (1200 dp).
- **Grid:** 12 columns.
- **Tables:** full data table with the complete column set for the primary workflows (§4).
- **Dialogs:** centred, `size.dialog.width`.
- **Detail navigation:** split view — the list stays visible while the detail changes.

### 2.4 Wide (≥ 1440)

- **Structure:** identical to expanded. The content column is **centred and capped**, not stretched.
- **Navigation:** persistent side navigation, expanded.
- **Screen margin:** `space.8` with the remainder as symmetric empty space.
- **Grid:** 12 columns, wider gutters (`space.8`).
- **Tables:** additional columns may be revealed, and the table may use the full
  `size.maxWidth.content` width.
- **What does not happen:** running text does not exceed `size.maxWidth.reading` (640 dp); a form does not
  become a 1400 px-wide row of fields; a two-column layout does not become five columns.

**A wide screen buys breathing room, not more density.** Filling a 2560 px monitor edge to edge is a
failure, not a feature.

---

## 3. The Tracking Portal at 320 px

**The public tracking portal must be fully usable at a 320 px viewport width.**

This is a hard requirement. The portal is opened by whoever the customer forwarded the link to, on
whatever device they have, and it is the product's most visible differentiator (Master Source §5.4).
It must never degrade into "install the app first" (DEC-0006, DEC-0014).

### Obligations at 320 px

1. **No horizontal scrolling.** Anywhere. Not on the status region, not on the timeline, not on the
   payment summary.
2. **Single column**, `space.4` (16 dp) margins, leaving a 288 px content width.
3. **The primary question is answered above the fold**: brand and outlet, order number, current status
   with label and icon, and the estimated completion. The customer's question is "sudah selesai belum?"
4. **Comfortable density** in the status region (`SPACING_SIZING_DENSITY.md` §5.3).
5. **Touch targets remain 48 × 48 dp.** Narrow viewport is not a reason to shrink targets.
6. **The status timeline is vertical**, never a horizontal stepper. A horizontal stepper with 15
   possible statuses cannot fit 288 px and will either scroll or truncate.
7. **Money values never wrap and never truncate.** `Rp1.240.000` fits at 320 px in
   `font.size.headline.md`; if a longer value ever would not, the container reflows to give the amount
   its own line.
8. **The map preview is deferred and optional.** It loads after the status content, and its failure
   never blocks the page.
9. **No modal is required to see the core status.** Any modal is an optional detail.
10. **At 200% text scaling on a 320 px viewport** — the hardest combination in the product — the status,
    label, and amount due remain readable without horizontal scroll. Secondary content may collapse
    behind expanders to achieve this.

### Prohibited on the portal

- Horizontal scroll of any region.
- A data table.
- An app-install interstitial, banner, or modal that obstructs the status content.
- A full address, at any viewport width (Master Source §9.2 rule 8).
- Laundry photographs (Master Source §17.2 rule 3).
- Any animation beyond a fade and a spinner (`MOTION_AND_REDUCED_MOTION.md` §6).

---

## 4. Console Web at 1366 × 768

**Every primary Console workflow must be operable at 1366 × 768 without horizontal scrolling.**

1366 × 768 is the most common laptop resolution among the target market and is therefore the Console's
design baseline, not its minimum-supported edge case. At 1366 px with a 264 dp side navigation and
`space.8` (32 dp) margins, the available content width is approximately **1038 px**. Every primary
workflow is designed against that number.

Vertical space is the harder constraint: 768 px minus browser chrome (~120 px) minus the 64 dp top bar
leaves roughly **584 px** of content height. Consequences:

1. **Primary actions are reachable without scrolling** on a form of reasonable length, or the action bar
   is sticky.
2. **Data tables show at least 8 rows** before requiring a scroll.
3. **A dialog never exceeds 560 px in height** at this resolution; longer content scrolls **inside** the
   dialog while the title and action bar stay fixed.
4. **A dashboard shows its most important KPI row plus one content block** without scrolling.
5. **Filter bars collapse into a filter button** when they would consume more than 64 px of vertical
   space.

### Primary workflows that must fit

| Workflow | Requirement at 1366 × 768 |
|---|---|
| Order list and detail | Split view; detail readable without horizontal scroll |
| Receivables / unpaid balance | All nine unclaimed-laundry dashboard fields reachable (Rule 10) |
| Payment recording and reconciliation | No horizontal scroll; all amounts fully visible |
| Shift closing | Expected, actual, and variance visible simultaneously — never scrolled apart |
| Customer detail | No horizontal scroll |
| Service catalogue and pricing | No horizontal scroll |
| Tenant and outlet configuration | No horizontal scroll |
| Owner portfolio dashboard | KPI row plus one block without vertical scroll |
| Audit timeline | No horizontal scroll |

**The shift-closing rule is the strictest.** Expected cash, actual cash, and the variance must be
visible at the same time. A layout that requires scrolling between them invites a reconciliation
mistake and undermines Rule 04, rule 10.

### The documented exception: large tables

Some tables genuinely carry more columns than 1038 px can hold — a full financial ledger export
preview, a multi-outlet comparison, a detailed audit log.

**These may scroll horizontally, under a documented responsive pattern** (§5). They are the **only**
permitted horizontal scroll in the product, and the pattern's constraints are binding.

---

## 5. The wide-table responsive pattern

When a table cannot fit its viewport, exactly one of these three patterns is applied. The choice is
recorded per table in the Step that builds it.

### Pattern A — Column priority (preferred)

Each column carries a priority. Below the width needed for all of them, low-priority columns are
progressively hidden and become available in a row-expansion or detail view.

- **Never hidden:** the identifying column (order number, customer name), the status column, and any
  money column the user must act on.
- Hidden columns are reachable in one interaction from the row.
- A visible control states what is hidden: "3 kolom disembunyikan".
- **Use when** the table has a clear priority ordering. This is the default.

### Pattern B — Horizontal scroll with a frozen identity column

The table scrolls horizontally; the first column (identity) is frozen.

- The identity column is always visible so a scrolled row is never anonymous.
- The scroll region is **keyboard-scrollable** and is exposed as a scrollable region to assistive
  technology with an accessible name.
- A visible scroll affordance is present — a scrollbar or an edge shadow, never hover-only.
- **The page itself never scrolls horizontally.** Only the table's own region does.
- **Use when** every column is genuinely needed simultaneously — a ledger, a reconciliation view.

### Pattern C — Stacked cards

Below `breakpoint.medium`, or above 150% text scaling on compact, the table becomes a list of cards.
One row is one card; column headers become labels within the card.

- Sorting and filtering remain available as explicit controls.
- Money values keep tabular figures and right alignment within the card.
- **Use when** the surface is Android or a compact viewport. **This is mandatory on Android** — a
  horizontally scrolling data table is prohibited on any Android surface
  (`PLATFORM_ADAPTATION.md`).

### Binding constraints on all three

1. **No information is lost** — only relocated. Every hidden column is reachable.
2. **A money column is never hidden, truncated, or abbreviated.**
3. **A status column is never hidden**, because status is the primary scanning dimension.
4. **The pattern is documented per table**, not chosen ad hoc at implementation time.
5. **Row selection and bulk actions survive** the pattern change.

---

## 6. Component responsive behaviour

| Component | Compact | Medium | Expanded / Wide |
|---|---|---|---|
| Navigation | Bottom navigation / drawer | Navigation rail / collapsed side nav | Persistent side nav |
| Data table | Stacked cards (C) | Reduced columns (A) or stacked (C) | Full table, pattern A or B if needed |
| Dialog | Full-screen (Android) or `size.dialog.width.sm` | `size.dialog.width` centred | `size.dialog.width` centred |
| Bottom sheet | Full width, from the bottom edge | Full width, capped at `size.bottomsheet.max` | Prefer a dialog or a side panel |
| Filter bar | Filter button opening a sheet | Inline, wrapping | Inline, full |
| Form | Single column | Single column | Single column, capped at `size.maxWidth.reading`; two columns only for genuinely paired fields |
| KPI cards | 1 per row | 2 per row | 4 per row |
| Chart | Full width, minimum 240 dp height | Full width | Constrained; never wider than 800 dp |
| Tabs | Scrollable tabs | Fixed tabs | Fixed tabs |
| App bar | 56 dp, title + 2 actions max | 56 dp | 64 dp top bar with a tenant switcher |
| List/detail | Separate screens | Separate screens | Split view |
| Action bar | Sticky bottom bar | Sticky bottom bar | Inline or sticky bottom bar |

**Forms stay single column even on wide screens.** A two-column form breaks the reading order for
keyboard and screen-reader users and increases error rates. The only exception is a genuinely paired
field set — a time window's start and end, a from/to date range.

---

## 7. Orientation

- **Portrait is the primary orientation** for Customer Android and Ops Android.
- **Landscape must work.** Rotating a device never loses data, never resets a form, and never blocks
  the primary action (`ACCESSIBILITY.md` §14, WCAG 2.2 SC 1.3.4).
- **Landscape on a phone lands in `breakpoint.medium`** and adopts that layout. The bottom action bar
  remains within reach; where vertical space is tight, the action bar becomes a trailing-edge action
  region rather than consuming a third of the screen.
- **Orientation is never locked** except where a genuine task requires it — signature capture may
  request landscape, but must still function in portrait.
- **Console Web and the tracking portal** have no orientation logic; they respond to viewport width.

---

## 8. Prohibited responsive practices

| Prohibited | Reason |
|---|---|
| Horizontal scroll on any page body | §3, §4 |
| Horizontal scroll of a table on Android | `PLATFORM_ADAPTATION.md`; use stacked cards |
| Horizontal scroll anywhere on the tracking portal | §3 |
| Device detection ("is tablet", "is iPad") in place of viewport logic | §1 rule 1 |
| Density selected by breakpoint | §1 rule 3 |
| Stretching content to fill a wide viewport | §2.4 |
| Running text wider than `size.maxWidth.reading` | §2.4 |
| A multi-column form | §6 |
| A horizontal stepper with more than 4 steps at compact | §3 rule 6 |
| Hiding a money or status column at any breakpoint | §5 constraints 2 and 3 |
| A layout valid only at an exact breakpoint value | §1 rule 4 |
| Locking orientation without a task requirement | §7 |
| A hover-only scroll affordance | §5 Pattern B |
| An app-install prompt obstructing portal content | §3 |
