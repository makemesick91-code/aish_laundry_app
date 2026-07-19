# Data Visualization — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY. No chart exists.

---

## 1. Position

Charts in this product exist to answer an **operational question**, not to decorate a dashboard.

Before a chart is specified, three questions must be answerable:

1. **What decision does this support?** If none, remove it.
2. **Would a number or a table answer it better?** Very often yes.
3. **What does the user do differently based on what they see?**

The owner of a three-outlet laundry does not want a data-visualisation experience. They want to know
whether this week was better than last week, which outlet is behind, and how much money is sitting
uncollected on a shelf.

### The default is not a chart

| Question | Best form |
|---|---|
| "How much is uncollected right now?" | A number (KPI card) |
| "Which orders are overdue?" | A table |
| "Is revenue trending up or down?" | A line chart |
| "How did the outlets compare last month?" | A bar chart |
| "What is the payment status mix?" | A table, or a stacked bar — **not** a pie |

---

## 2. Permitted chart types

| Type | Use when | Prohibited when |
|---|---|---|
| **Line** | A continuous measure over time, 1–4 series | Categories are not ordered; more than 4 series |
| **Bar (vertical)** | Comparing a measure across few categories, or over time with ≤ 12 points | More than 12 categories — use horizontal |
| **Bar (horizontal)** | Comparing across many or long-labelled categories | Time is the axis — use vertical or line |
| **Stacked bar** | Composition **and** total both matter, ≤ 4 segments | More than 4 segments; comparing segments other than the first |
| **Grouped bar** | Comparing 2–3 series across few categories | More than 3 series |
| **Sparkline** | Trend shape inside a KPI card or a table row | The exact values matter — the value is shown as text alongside |
| **Progress bar** | Progress toward a known target | There is no defined target |

### Chart types that require justification

**Pie and donut** charts are permitted **only** with a recorded justification, and only when: there are
at most 3 segments; the segments sum to a meaningful whole; and the values are also printed as text
beside the chart. In every other case a stacked bar or a table is used. Humans compare angles poorly,
and the product's users are making money decisions.

### Prohibited chart types

| Prohibited | Reason |
|---|---|
| **Any 3D chart** | 3D distorts every value it renders. There is no legitimate use |
| **Dual-axis without justification** | Two y-axes let any two series be made to look correlated. See §3 |
| **Gauge / speedometer** | Enormous space for one number; a KPI card is better |
| **Radar / spider** | Unreadable; area implies meaning that does not exist |
| **Treemap, sunburst, sankey, chord** | Analytical toys; no operational question in this product needs them |
| **Word cloud** | Encodes nothing reliably |
| **Bubble chart** | Area is judged poorly and inconsistently |
| **Area chart with more than 2 overlapping series** | Occlusion makes lower series unreadable |
| **Animated / transitioning chart** | `MOTION_AND_REDUCED_MOTION.md` §6 |
| **Truncated-axis bar chart** | A bar chart's y-axis starts at zero, always. Truncating exaggerates differences |
| **Any chart of financial data without the values also available as text** | §6 |

---

## 3. The dual-axis rule

**A chart with two y-axes is prohibited unless it carries a written justification** recorded with the
chart's specification.

The justification must state: why the two measures must appear together; why two separate charts are
insufficient; and what the reader should and should **not** conclude from the visual relationship.

A dual-axis chart makes any two series appear correlated by choosing scales. In a product where a user
might conclude "reminders drive collections" from a coincidence, that is a real risk.

**The default alternative:** two stacked charts sharing an x-axis. Same information, no manufactured
correlation.

---

## 4. Colour-independent encoding

**No chart may rely on colour to distinguish its series** (`ACCESSIBILITY.md` §2, Master Source §18.2
rule 2).

Every chart uses at least one non-colour encoding:

| Chart | Required non-colour encoding |
|---|---|
| Line | **Direct labelling** at the end of each line; distinct dash patterns for 3+ series; distinct point markers |
| Bar (single series) | No encoding needed — position and category label carry it |
| Grouped bar | Direct labels or a legend **plus** distinct fill patterns |
| Stacked bar | Segment labels inside the segment where it fits, otherwise a legend **plus** patterns; values printed |
| Sparkline | Never carries a series distinction — one series only |
| Progress bar | Percentage printed as text |

### Series palette

Chart series draw from a restricted, ordered set. Each colour is used only when the ones before it are
taken. All values are from the palette in [`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md).

| Order | Token | Hex | Non-colour partner |
|---|---|---|---|
| 1 | `color.blue.600` | Solid line, circle marker |
| 2 | `color.blue.900` | Dashed line, square marker |
| 3 | `color.green.600` | Dotted line, triangle marker |
| 4 | `color.amber.600` | Dash-dot line, diamond marker |

**Four series maximum.** A chart needing a fifth series is answering more than one question and should
be split.

Every series colour measures at least 5.79:1 against `color.semantic.surface`, exceeding the 3:1
requirement for meaningful graphical objects.

### Status colours in charts

When a chart segments by a status, it uses that status's semantic token
(`COLOR_AND_CONTRAST.md` §8) **plus** the status label as a direct segment label. A payment-mix chart
uses warning for "Belum Lunas" and success for "Lunas", and prints both labels.

### Chart chrome

| Element | Token |
|---|---|
| Axis line | `color.semantic.border.strong` (6.80:1) |
| Grid line | `color.semantic.border` — decorative, minimal, horizontal only |
| Axis label | `font.size.label.sm`, `color.semantic.text.secondary` (6.45:1) |
| Data label | `font.size.label.sm`, `color.semantic.text.primary` |
| Chart title | `font.size.title.md` |
| Empty region | `color.semantic.surface.sunken` |

Vertical grid lines are omitted unless the chart is genuinely read across. Grid lines are the first
thing removed when a chart feels busy.

---

## 5. Text alternative — mandatory

**Every chart has a text alternative and an accessible data table.** No exceptions.

### The text alternative

One to two sentences stating what the chart shows and its key finding. Not a description of the shape.

- Write: "Pendapatan mingguan Outlet Melati, 4 minggu terakhir. Minggu ini Rp12.400.000, naik dari
  Rp11.100.000 minggu lalu."
- Do not write: "Grafik garis menunjukkan tren naik."

### The accessible data table

Every chart is accompanied by a table carrying the same values:

1. Reachable by keyboard and screen reader.
2. Visually available too — a "Lihat data" expander, not a screen-reader-only element. Sighted users
   who need exact values are not a minority.
3. Money values in full, with tabular figures, never abbreviated.
4. Marked up as a real table with headers (`ACCESSIBILITY.md` §9).

### Chart interaction

- Any interaction is keyboard operable.
- Tooltip content is also present in the data table — a tooltip is never the only route to a value.
- Tooltips are dismissible, hoverable, and persistent (WCAG 2.2 SC 1.4.13).
- On touch, tap shows a value; no hover-only affordance exists.

---

## 6. Financial data in charts

1. **A chart is never the authoritative source of a money value.** An amount a user must act on is
   text.
2. **Money is never abbreviated on an axis.** `Rp1,2jt` is prohibited (`TYPOGRAPHY.md` §6). If the axis
   cannot fit `Rp1.240.000`, the axis is rotated, the chart is made horizontal, the unit is stated once
   in the axis title ("Pendapatan (Rupiah)") with full values in the data table — the number is not
   abbreviated.
3. **Bar charts of money start at zero.** Always.
4. **A variance is never smoothed, rounded, or trend-lined away** (`DESIGN_PRINCIPLES.md` P1).
5. **No projection, forecast, or trend line** is drawn unless the product actually computes one and can
   explain its basis. A drawn trend line is a claim (Rule 01).
6. **Cross-tenant aggregation** in an owner portfolio chart is permitted only across tenants the user
   legitimately belongs to, and the chart names which tenants are included (Rule 02, hard rule 13).
7. **A chart of unpaid balance or held invoices reads from the authoritative financial records**, never
   from a recomputation (Rule 10).

---

## 7. KPI card specification

The KPI card is the most-used data component in the product and is preferred over a chart wherever it
answers the question.

### Anatomy

```
┌─────────────────────────────────────┐
│ Label                          [i]  │   label.md, text.secondary
│                                     │
│ Rp12.400.000                        │   numeric-emphasis, headline.md
│                                     │
│ ▲ Rp1.300.000 dari minggu lalu      │   body.sm + direction icon + text
│ [optional sparkline]                │   40 dp height
└─────────────────────────────────────┘
```

### Specification

| Property | Value |
|---|---|
| Container | `radius.lg`, `border.width.hairline` / `color.semantic.border`, `elevation.0` |
| Padding | `space.4` (standard density), `space.5` (comfortable) |
| Label | `font.size.label.md`, `color.semantic.text.secondary` |
| Value | numeric-emphasis at `font.size.headline.md`, tabular figures, `color.semantic.text.primary` |
| Comparison | `font.size.body.sm`, with a direction icon **and** the words "naik"/"turun" |
| Sparkline | Optional, 40 dp height, single series, `color.blue.600` |
| Info affordance | Optional icon button explaining what the metric counts and its period |
| Layout | 1 per row compact, 2 medium, 4 expanded (`RESPONSIVE_FOUNDATION.md` §6) |

### Rules

1. **The value is never truncated and never abbreviated.** A KPI card that cannot fit its value grows
   or the card layout changes (`TYPOGRAPHY.md` §3.7).
2. **A comparison states its baseline.** "dari minggu lalu", never a bare "▲ 12%".
3. **Direction is never colour-only.** An arrow glyph **and** the word "naik" or "turun" accompany it.
4. **Up is not automatically good.** An increase in unpaid balance is bad. The card's semantic colour
   reflects whether the change is favourable, and the label makes it explicit — never a blanket
   green-up / red-down rule.
5. **The period is always stated**, in the label or the info affordance.
6. **A KPI card carrying a money value uses the authoritative financial record**, never a client
   computation (Rule 04).
7. **An empty or unknown value shows "—" with an explanation**, never "0" and never a blank. Zero and
   unknown are different facts.
8. **A KPI card is not a link unless the whole card is the target**, and if it is, it is a real button
   with an accessible name.

---

## 8. Chart sizing and responsiveness

| Breakpoint | Behaviour |
|---|---|
| Compact | Full width, minimum 240 dp height; ≤ 6 x-axis categories or a horizontal bar chart |
| Medium | Full width, 280 dp height |
| Expanded | Constrained to the content column, 320 dp height |
| Wide | **Never wider than 800 dp.** A stretched chart exaggerates trend shape |

At 200% text scaling, a chart's labels take priority over its plotting area: the chart shrinks, labels
do not. If labels cannot fit legibly, the chart is replaced by its data table.

---

## 9. Prohibited practices

| Prohibited | Reason |
|---|---|
| Any 3D chart | §2 |
| Dual axis without a recorded justification | §3 |
| More than 4 series | §4 |
| Series distinguished by colour alone | §4 |
| A chart with no text alternative or data table | §5 |
| Abbreviated money on an axis or label | §6 rule 2 |
| A bar chart of money not starting at zero | §6 rule 3 |
| A drawn trend line or forecast the product does not compute | §6 rule 5 |
| A chart as the sole source of an actionable money value | §6 rule 1 |
| Animated chart transitions | `MOTION_AND_REDUCED_MOTION.md` §6 |
| A pie chart with more than 3 segments | §2 |
| Hover-only access to a value | §5 |
| A chart stretched beyond 800 dp | §8 |
| A chart on the public tracking portal | Performance and privacy (`PLATFORM_ADAPTATION.md` §5) |
| A chart mixing data from tenants the user does not belong to | Rule 02 |
| Green-up / red-down applied without regard to whether the change is favourable | §7 rule 4 |
| A chart implying route optimisation or delivery performance guarantees | Rule 09, rule 1 |
