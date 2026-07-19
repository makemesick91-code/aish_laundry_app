# Typography — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY. No font is bundled, loaded, or rendered.
**Accessibility target:** DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED

---

## 1. Font strategy: system-first

**No font binary is committed to this repository, and none will be.** No `.ttf`, `.otf`, `.woff`, or
`.woff2` file, no webfont link, no font CDN reference.

The type system uses the **platform's own system font stack**.

| Surface | Stack (in order) |
|---|---|
| Android (Customer, Ops) | Roboto → the platform default sans-serif |
| Web (Console, Tracking Portal) | `system-ui` → Segoe UI → Roboto → Helvetica Neue → Arial → sans-serif |
| Numeric contexts (all surfaces) | The same stack, with tabular figures enabled (§6) |

### Why system-first

1. **Performance on the baseline device.** Master Source §18.2 rule 8 names low-end Android as the
   baseline. A webfont on the public tracking portal costs a round trip and a render-blocking window on
   exactly the network the portal must survive. The portal's job is to answer "sudah selesai belum?"
   fast.
2. **Zero licensing exposure on a PUBLIC repository.** No font licence is committed, interpreted, or
   breached (Rule 23).
3. **Correct Indonesian rendering.** System stacks handle Indonesian orthography and diacritics without
   a subset that might drop a glyph.
4. **Respects user preference.** A user who has increased their system font size or chosen a
   readability font gets it.
5. **No FOIT/FOUT.** No flash of invisible or unstyled text.

### Consequence, stated honestly

Metrics differ slightly between Roboto and Segoe UI. Every layout must therefore be **metric-tolerant**:
no layout depends on an exact glyph width, no single-line region is sized to a specific string, and
every text container tolerates roughly ±10% width variance. A design that breaks when the font falls
back is a defective design, not a font problem.

A branded display face is not adopted in MVP. Recorded as `DEBT-002`.

---

## 2. Type scale

Sizes are in **dp** (Android) / **px** at 1× (Web). They are the base values before user text scaling
is applied (§7).

| Token | Size | Line height | Weight | Letter spacing |
|---|---|---|---|---|
| `font.size.display.lg` | 36 | 44 | `font.weight.bold` (700) | `-0.02em` |
| `font.size.display.md` | 30 | 38 | `font.weight.bold` (700) | `-0.02em` |
| `font.size.headline.lg` | 26 | 34 | `font.weight.semibold` (600) | `-0.01em` |
| `font.size.headline.md` | 22 | 30 | `font.weight.semibold` (600) | `-0.01em` |
| `font.size.headline.sm` | 20 | 28 | `font.weight.semibold` (600) | `0` |
| `font.size.title.lg` | 18 | 26 | `font.weight.semibold` (600) | `0` |
| `font.size.title.md` | 16 | 24 | `font.weight.semibold` (600) | `0` |
| `font.size.title.sm` | 14 | 20 | `font.weight.semibold` (600) | `0.01em` |
| `font.size.body.lg` | 17 | 26 | `font.weight.regular` (400) | `0` |
| `font.size.body.md` | 15 | 22 | `font.weight.regular` (400) | `0` |
| `font.size.body.sm` | 13 | 20 | `font.weight.regular` (400) | `0.01em` |
| `font.size.label.lg` | 15 | 20 | `font.weight.medium` (500) | `0.01em` |
| `font.size.label.md` | 13 | 18 | `font.weight.medium` (500) | `0.01em` |
| `font.size.label.sm` | 12 | 16 | `font.weight.medium` (500) | `0.02em` |
| `font.size.caption` | 12 | 16 | `font.weight.regular` (400) | `0.01em` |

**Minimum size floor:** 12 dp/px. No text in any surface is specified below `font.size.caption`.
Anything that "needs" to be smaller needs less content, not smaller type.

### Weights

| Token | Value | Use |
|---|---|---|
| `font.weight.regular` | 400 | Body copy, data values |
| `font.weight.medium` | 500 | Labels, table headers, chips |
| `font.weight.semibold` | 600 | Titles, headlines, emphasised money |
| `font.weight.bold` | 700 | Display only, and money in receipt totals |

Only these four. No light (300) or thin (100) weight is used anywhere — they fail legibility on
low-quality displays in bright light. Italic is not used for emphasis in Indonesian UI copy; weight and
placement carry emphasis instead.

---

## 3. Named text styles

Each style declares family strategy, size, line height, weight, letter spacing, wrapping, truncation,
maximum line length, and numeric behaviour.

### 3.1 Display

- **Family:** system stack
- **Token:** `font.size.display.lg` / `.md`
- **Weight:** 700 · **Letter spacing:** `-0.02em`
- **Wrapping:** wraps; maximum 2 lines
- **Truncation:** none — display text that does not fit is a content problem, shortened at source
- **Max line length:** 20 characters
- **Numeric:** proportional
- **Use:** the single largest figure on a KPI-led dashboard view; onboarding headline
- **Prohibited:** in any Ops Android operational screen; in tables; in any dense region; for money in a
  financial context (money uses `receipt-numeric` or `numeric-emphasis`)

### 3.2 Headline

- **Family:** system stack · **Token:** `font.size.headline.lg` / `.md` / `.sm`
- **Weight:** 600 · **Letter spacing:** `-0.01em` (lg, md), `0` (sm)
- **Wrapping:** wraps freely; no line limit
- **Truncation:** never — a truncated heading destroys page structure and breaks screen-reader
  navigation
- **Max line length:** 40 characters
- **Numeric:** proportional
- **Use:** screen titles, major section headings. Maps to heading levels (`ACCESSIBILITY.md` §5)

### 3.3 Title

- **Family:** system stack · **Token:** `font.size.title.lg` / `.md` / `.sm`
- **Weight:** 600 · **Letter spacing:** `0` (lg, md), `0.01em` (sm)
- **Wrapping:** wraps to a maximum of 2 lines
- **Truncation:** `title.md` and `title.sm` may truncate with an ellipsis at line 2 **only** when the
  full value is available elsewhere — a tooltip on Web, a detail view on Android. A truncated customer
  name is never the only rendering of that name
- **Max line length:** 50 characters
- **Numeric:** proportional
- **Use:** card titles, list item primary text, dialog titles, app bar titles, the wordmark

### 3.4 Body

- **Family:** system stack · **Token:** `font.size.body.lg` / `.md` / `.sm`
- **Weight:** 400 · **Letter spacing:** `0` (lg, md), `0.01em` (sm)
- **Wrapping:** wraps freely
- **Truncation:** body copy is **never** truncated in an error, warning, financial, privacy, or consent
  context. Elsewhere, a "Selengkapnya" expander is used instead of an ellipsis
- **Max line length:** 70 characters for comfortable reading; hard ceiling 80. Console Web text columns
  are width-constrained rather than filling a 1440 px viewport
- **Numeric:** proportional in prose; **tabular whenever a Rupiah amount appears**, even inline (§6)
- **Use:** all running text, descriptions, help text, message bodies
- **Default:** `font.size.body.md` is the product's default text style

### 3.5 Label

- **Family:** system stack · **Token:** `font.size.label.lg` / `.md` / `.sm`
- **Weight:** 500 · **Letter spacing:** `0.01em` (lg, md), `0.02em` (sm)
- **Wrapping:** wraps to 2 lines maximum; **status badges must wrap, never truncate**
- **Truncation:** prohibited for status labels, form field labels, and button labels. A button whose
  label does not fit needs a shorter label, not an ellipsis
- **Max line length:** 30 characters
- **Numeric:** tabular
- **Use:** buttons, form labels, chips, status badges, table column headers, tabs
- **Casing:** sentence case. **Never all-caps** — uppercase harms Indonesian legibility and reads as
  shouting (`CONTENT_DESIGN.md`)

### 3.6 Caption

- **Family:** system stack · **Token:** `font.size.caption`
- **Weight:** 400 · **Letter spacing:** `0.01em`
- **Wrapping:** wraps; maximum 3 lines
- **Truncation:** prohibited when the caption carries a privacy notice, a legal note, a variance
  explanation, or an error detail
- **Max line length:** 60 characters
- **Colour:** `color.semantic.text.secondary` (6.80:1)
- **Use:** timestamps, helper text, metadata, footnotes, masking explanations
- **Prohibited:** as the sole carrier of an error message; for any Rupiah amount a user must act on

### 3.7 Numeric emphasis

The style for money and quantities that carry decision weight.

- **Family:** system stack with **tabular figures mandatory**
- **Size:** `font.size.headline.md` (22) or `font.size.title.lg` (18) depending on hierarchy
- **Weight:** 600 · **Letter spacing:** `0`
- **Wrapping:** **never wraps.** A Rupiah amount is atomic. If it does not fit, the container grows or
  the layout changes — the number does not break across lines
- **Truncation:** **absolutely prohibited.** A truncated money value is a correctness failure under
  P1 (`DESIGN_PRINCIPLES.md`). `Rp1.240.0…` is not an acceptable rendering of anything
- **Max line length:** not applicable — single token
- **Numeric:** tabular, with the currency prefix `Rp` in the same style, non-breaking space suppressed
  (`Rp79.000`, no space)
- **Use:** order total, amount due, payment amount, shift-close expected/actual/variance, KPI values
- **Rule:** the value shown is an integer-Rupiah value formatted for display. The design never implies
  a client-computed figure is authoritative (Rule 04, `FORM_AND_VALIDATION_PATTERNS.md` §7)

### 3.8 Receipt numeric

The style for the printed and previewed nota, where column alignment is the whole point.

- **Family:** system stack with **tabular figures mandatory**
- **Size:** `font.size.body.sm` (13) for line items, `font.size.title.md` (16) at 700 for the total
- **Weight:** 400 line items, 700 total · **Letter spacing:** `0`
- **Wrapping:** amounts never wrap; item descriptions wrap to 2 lines then use a "Selengkapnya"-free
  hard stop, since a receipt is a fixed artefact
- **Truncation:** amounts never truncate; descriptions may truncate at 2 lines
- **Alignment:** amounts **right-aligned**, decimal-free (integer Rupiah), currency prefix on every
  amount line and on the total
- **Max line length:** governed by receipt width (typically 58 mm or 80 mm thermal), not by character
  count
- **Numeric:** tabular, mandatory. Misaligned digits on a nota are the single most common cause of a
  customer disputing a total

### 3.9 Data table

- **Family:** system stack · **Size:** `font.size.body.sm` (13) at compact density,
  `font.size.body.md` (15) at standard density
- **Weight:** 400 cells, 500 headers · **Letter spacing:** `0.01em`
- **Wrapping:** text columns wrap to 2 lines; **numeric columns never wrap**
- **Truncation:** text cells may truncate with an ellipsis when the full value is reachable via row
  expansion or a detail view; numeric cells never truncate
- **Max line length:** per column width; a column narrower than 8 characters is prohibited for text
- **Numeric:** **tabular, mandatory, right-aligned.** Text left-aligned. Dates left-aligned. Status
  columns left-aligned with badge
- **Headers:** `font.size.label.md`, weight 500, `color.semantic.text.primary`, with
  `color.semantic.border.strong` bottom rule

---

## 4. Style-to-context mapping

| Context | Style |
|---|---|
| Screen title | `headline.md` |
| Section heading | `title.lg` |
| List item primary | `title.md` |
| List item secondary | `body.sm`, `text.secondary` |
| Card title | `title.md` |
| Body copy | `body.md` |
| Helper / hint text | `caption`, `text.secondary` |
| Error message | `body.sm`, `color.semantic.danger` |
| Button label | `label.lg` (large), `label.md` (medium/small) |
| Form field label | `label.md` |
| Form field value | `body.md` |
| Status badge | `label.md` |
| Table header | `label.md` |
| Table cell | `data-table` per §3.9 |
| Order total | `numeric-emphasis` at `headline.md` |
| KPI value | `numeric-emphasis` at `headline.md`, or `display.md` on a dashboard hero |
| Receipt line item | `receipt-numeric` at `body.sm` |
| Receipt total | `receipt-numeric` at `title.md` weight 700 |
| Timestamp | `caption`, `text.secondary` |
| Tracking portal status | `headline.sm` + badge at `label.lg` |

---

## 5. Text scaling

**The system supports user text scaling up to 200%** (Master Source §18.2 rule 6, WCAG 2.2 SC 1.4.4).

### Obligations

1. **No fixed-height text container.** Every container that holds text sizes to its content.
2. **No `maxLines: 1` on critical information** — status labels, money values, error messages, customer
   names in a confirmation context, or any consent text.
3. **Buttons grow vertically.** A button whose label wraps at 200% becomes taller; it does not clip and
   does not shrink its font.
4. **Badges wrap.** A status badge at 200% becomes a two-line badge.
5. **Tables switch layout.** Above 150% scaling on a compact breakpoint, a data table adopts the
   stacked-card responsive pattern (`RESPONSIVE_FOUNDATION.md` §6) rather than compressing columns.
6. **Bottom navigation labels may hide at extreme scaling** only if the icon retains an accessible name
   and the tooltip/long-press label remains available.
7. **Touch targets never shrink** to make room for larger text. 48 × 48 dp is a floor at every scale.
8. **Density steps down automatically.** Compact density is not applied above 130% text scaling; the
   layout falls back to standard.

### The 200% test

Every screen specification must state that it has been reasoned about at 200%. The failing conditions:
truncated money, clipped status label, unreachable primary action, horizontal scroll introduced on a
non-table region, or overlapping text.

**No screen has been tested at 200%, because no screen exists.** This is a specification obligation
owed by Step 11 and beyond.

---

## 6. Numerals

### Tabular figures are mandatory for

- Every Rupiah amount, anywhere, in any surface.
- Every data-table numeric column.
- Every receipt line and total.
- Weight values (kg), quantity values (pcs), and item counts.
- Timestamps and durations in a list or table.
- Order numbers, invoice numbers, and any reference code.
- KPI values and chart axis labels.

Proportional figures are permitted only in running prose where a number appears incidentally.

### Indonesian numeric formatting

These follow Indonesian conventions and are fixed (Master Source §1.6, Rule 04):

| Kind | Format | Example |
|---|---|---|
| Currency | `Rp` + thousands separated by `.` , no space, no decimals | `Rp79.000`, `Rp1.240.000`, `Rp999.000` |
| Zero amount | Explicit | `Rp0` |
| Negative / variance | Minus before the currency prefix | `-Rp25.000` |
| Weight | Decimal comma, one decimal place, space before unit | `1,5 kg`, `12,0 kg` |
| Quantity | Integer, space before unit | `3 pcs`, `12 pcs` |
| Percentage | Decimal comma, space before `%` | `12,5 %` |
| Time | 24-hour, colon separator | `08:00`, `20:00` |
| Time window | En dash between bounds | `08:00–10:00` |
| Date | Day, Indonesian month name, year | `19 Juli 2026` |
| Short date | `DD/MM/YYYY` | `19/07/2026` |
| Date and time | Date, space, 24-hour time | `19 Juli 2026 14:30` |
| Relative age | `H+` and an integer | `H+3`, `H+14` |
| Phone (masked, public) | Prefix, masked middle, last two digits | `08•• •••• ••21` |

**Money is never abbreviated.** `Rp1,2jt`, `Rp1.2M`, and `1,24 juta` are prohibited in every surface,
including charts and KPI cards. A user reconciling cash needs the exact figure. Where a chart axis
cannot fit the full value, the axis is reformatted or the chart is changed — the number is not
abbreviated.

**Timezone:** amounts and events are stored in UTC and displayed in the **outlet's local time**
(Master Source; Asia/Jakarta for business-day logic). Where a displayed time could be ambiguous — a
cross-outlet report — the timezone is stated explicitly next to the value.

---

## 7. Prohibited typographic practices

| Prohibited | Reason |
|---|---|
| Committing any font binary | §1; licensing and repository-size discipline on a PUBLIC repo |
| A webfont or font CDN reference | §1; render-blocking on the baseline network |
| Font weights 100, 200, 300 | Illegible on low-quality displays in bright light |
| All-caps for emphasis in Indonesian copy | Harms legibility; reads as shouting |
| Italic for emphasis in UI copy | Poor rendering in system stacks at small sizes |
| Text below 12 dp/px | Legibility floor |
| Truncating a money value | Correctness failure (P1) |
| Truncating a status label | Violates "status never by colour alone" — the label is the carrier |
| Truncating an error message | Violates "errors explain recovery" |
| Justified text | Creates rivers and irregular spacing in Indonesian |
| Letter spacing above `0.02em` on body copy | Reduces reading speed |
| Line length above 80 characters | Reading fatigue |
| Proportional figures in a numeric column | Digits fail to align; misreading risk |
| Rendering `READY_FOR_PICKUP` or any raw identifier | P7; use the Indonesian label |
| Abbreviated currency (`Rp1,2jt`) | Ambiguity in a financial surface |
| Fixed-height text containers | Breaks at 200% scaling |
