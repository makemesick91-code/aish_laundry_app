# Brand Foundation — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Derived from:** Master Source §18.1 (Visual language), Rule 05
**Scope:** DOCUMENTATION ONLY

---

## 1. LOGO STATUS: NOT APPROVED

**There is no approved logo, brand mark, monogram, or icon for Aish Laundry App.**

No logo file exists in this repository. No logo has been commissioned, designed, reviewed, or approved
by the owner (Aish Tech Solution). Any image, glyph, or shape presented as the Aish Laundry App logo
would be a fabrication.

### Placeholder policy

Until the owner approves a mark, every surface uses a **text wordmark**:

```
Aish Laundry App
```

Rules for the placeholder wordmark:

1. It is **text**, rendered with the system font stack (`TYPOGRAPHY.md`). It is not an image, not an
   SVG path, not a font binary.
2. Style: `font.size.title.lg`, `font.weight.semibold`, `color.semantic.text.primary` on light
   surfaces, `color.semantic.text.inverse` on the dark-blue app bar.
3. Letter spacing: `letter.spacing.tight`. No custom kerning, no stylised glyph substitution.
4. It is written exactly "Aish Laundry App" — not "AishLaundry", not "AISH", not "ALA", not "Aish".
5. It is never combined with an invented emblem, swoosh, droplet, bubble, or washing-machine glyph.
6. It carries no tagline. No tagline has been approved either.
7. Where a square app icon or favicon is structurally required, the specification records the
   requirement and marks it **NOT IMPLEMENTED**. A placeholder letterform is **not** authored here,
   because a shipped placeholder becomes a de facto logo.

### What happens when a logo is approved

Approval is an owner decision and requires a decision record. When it lands, this section is replaced,
`DESIGN_DEBT_REGISTER.md` entry DEBT-001 is closed, and the wordmark policy is superseded. Until then,
this section stands unchanged. **An agent never approves a logo and never generates one to fill the
gap.**

---

## 2. Brand attributes

Six attributes, in priority order. Where two conflict, the higher one wins.

| # | Attribute | Means | Does not mean |
|---|---|---|---|
| 1 | **Trustworthy** | Figures are legible and complete; state is never ambiguous; the interface is comfortable to show a customer across the counter | Corporate stiffness, legal-looking density |
| 2 | **Clean** | White dominant, generous whitespace, few competing emphases per view | Empty, sparse to the point of being hard to scan |
| 3 | **Professional** | Considered typography, consistent alignment, restrained colour | Formal, cold, or aimed at enterprise procurement |
| 4 | **Simple** | One obvious next action; plain Indonesian; no jargon | Simplistic; hiding information the user needs |
| 5 | **Light** | Visually light — bright surfaces, low visual weight, minimal shadow | Flimsy or unsubstantial |
| 6 | **Modern** | Current conventions, clear type, sensible spacing | Trend-chasing, fashionable, or experimental |

### Explicit anti-attributes

The brand is **NOT futuristic** and **NOT luxurious**. These are stated as prohibitions because both are
the likeliest drift directions for a design system built with contemporary tooling.

Not futuristic forbids: neon, glow, dark-mode-first aesthetics, glassmorphism, blur-heavy surfaces,
gradient meshes, science-fiction typography, "AI" visual tropes, animated backgrounds, holographic or
iridescent treatments.

Not luxurious forbids: gold as a dominant surface, black-and-gold pairings, serif display faces used
for prestige, wide-tracked uppercase headings, imagery of aspiration or opulence, "premium" framing of
ordinary features.

**Relevance test:** the product must look like something a laundry owner in Bandung with three
employees is comfortable using in front of a customer, and comfortable being seen to pay for. If a
design choice would look out of place in a small Indonesian shop, it is wrong for this product,
regardless of how well it renders.

---

## 3. Colour identity

The brand palette is fixed by Master Source §18.1: **white; soft blue; dark blue; restrained gold
accent.** Full values, roles, and measured contrast ratios are in
[`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md). The brand-level intent:

| Role | Family | Brand meaning |
|---|---|---|
| Dominant surface | White / near-white neutrals | Cleanliness, legibility in a bright shop |
| Interaction and information | Soft blue (`color.blue.*`) | "This is where you act" |
| Structure and weight | Dark blue (`color.blue.900`, `color.blue.800`) | Headers, navigation, authority |
| Accent | Gold (`color.gold.*`) | Value, achievement, loyalty — rare by design |

### The gold budget

Gold is the attribute most likely to be abused, so it carries hard limits:

1. **Never body text.** `color.gold.400` measures 2.24:1 on white and fails every text threshold.
   Gold text is permitted only as `color.gold.600` (4.90:1) or `color.gold.700` (7.58:1), and only for
   short labels — never running prose.
2. **Never the sole warning indicator.** Warnings use `color.semantic.warning` (amber family) with an
   icon and a text label. Gold is a value accent; conflating it with warning destroys both meanings.
3. **Never dominant.** Gold occupies at most a small fraction of any view — a chip, a small icon, a
   thin rule, a single tier badge. It is never a page background, a card background, an app bar, or a
   primary button.
4. **Never contrast-reducing.** Gold never sits on gold, never on light amber, and never behind text
   that would then fall below its threshold.
5. **Never a primary action colour.** The primary action is always `color.semantic.primary`.

Permitted gold usages, in full: loyalty tier badge; a "pelanggan setia" marker; a small achievement or
milestone accent in reporting; a thin accent rule in a receipt header; a star icon in a rating display.
That list is the whole budget. Adding to it is a design decision that goes in
`DESIGN_DECISION_LOG.md`.

---

## 4. Tone of voice

Full copy rules live in [`CONTENT_DESIGN.md`](CONTENT_DESIGN.md). The brand-level position:

**The product speaks like a competent colleague, not a bank, a robot, or a friend.**

| Dimension | Position |
|---|---|
| Language | Bahasa Indonesia, everyday register |
| Formality | Semi-formal. "Kamu" for customers; neutral/imperative for staff surfaces |
| Person | Second person for instructions; system in third person for state |
| Length | Short. One idea per sentence |
| Jargon | None user-facing. No English technical terms where an Indonesian one exists |
| Humour | None in operational, financial, or error copy |
| Blame | Never the user. "Nomor tidak ditemukan", not "Kamu salah memasukkan nomor" |

Voice examples (fictional data):

| Situation | Write | Do not write |
|---|---|---|
| Order ready | "Cucian kamu sudah siap diambil di Outlet Melati." | "Yeay! Cucianmu udah kelar nih! 🎉" |
| Payment outstanding | "Belum lunas. Sisa Rp45.000." | "Ups, masih ada tagihan nih." |
| Network failure | "Gagal terkirim. Periksa koneksi, lalu coba lagi." | "Terjadi kesalahan." |
| Route ordering | "Usulan urutan kunjungan" | "Rute optimal" |
| Delivery timing | "Perkiraan selesai: 20 Juli 2026" | "Dijamin sampai besok" |

---

## 5. Do and do not

### Do

- Let white carry the layout. Whitespace is the primary compositional tool.
- Use dark blue for structure — app bars, side navigation, table headers, section dividers.
- Use one filled, primary-coloured action per view.
- Use text plus icon plus colour for every status, in that order of importance.
- Keep numbers aligned, tabular, and complete. `Rp1.240.000`, never `Rp1,2jt`.
- Design the empty state and the error state with the same care as the happy path.
- Assume the screen is being read at arm's length, in sunlight, at 200% text scaling.

### Do not

- Do not introduce a fifth brand colour. The palette is white, soft blue, dark blue, gold.
- Do not use gold outside the budget in §3.
- Do not use a gradient as a brand element.
- Do not use photography of people, homes, or laundry as decoration — laundry photographs are
  RESTRICTED data (Rule 21) and are never decorative.
- Do not use illustration that implies capability the product lacks (a map with a drawn optimal route,
  a robot, a delivery guarantee).
- Do not create a dark-mode-first design. Light theme is canonical for MVP.
- Do not invent a mark, monogram, emblem, or app icon. See §1.
- Do not use uppercase for emphasis in Indonesian copy; it reads as shouting and harms legibility.
- Do not restyle a canonical status label to fit a layout. The label is fixed in
  [`UX_COPY_GLOSSARY.md`](UX_COPY_GLOSSARY.md).

---

## 6. Brand across the four surfaces

One brand, four expressions. Detail in [`PLATFORM_ADAPTATION.md`](PLATFORM_ADAPTATION.md).

| Surface | Brand emphasis | Restraint |
|---|---|---|
| Customer Android | Warmest expression. Gold permitted for loyalty. Friendly but never jokey | No marketing pressure in operational flows |
| Ops Android | Most utilitarian. Speed and legibility outrank polish | Gold effectively absent; colour reserved for status meaning |
| Console Web | Most structured. Dark blue navigation, dense data, tabular numerals | No decorative colour in data regions |
| Public Tracking Portal | Most reassuring and most minimal. Loads fast, says one thing clearly | No branding weight that costs load time; no app-install prompt that blocks content |

The tracking portal carries the tenant's **Laundry Brand** identity alongside the platform wordmark
(Master Source §9.3 permits brand and outlet identity). Tenant brand rendering is constrained: name as
text, no arbitrary tenant-supplied CSS, no tenant-supplied script, and any tenant logo served as a
size-limited, validated image through the platform's own storage rules (Rule 03, rule 12).

---

## 7. Status of brand assets

| Asset | Status |
|---|---|
| Logo / brand mark | NOT APPROVED |
| App icon (Android) | NOT IMPLEMENTED |
| Favicon (Console, Portal) | NOT IMPLEMENTED |
| Wordmark | Text placeholder only, per §1 |
| Brand illustration set | NOT STARTED |
| Photography direction | NOT APPLICABLE (no decorative photography in MVP) |
| Tenant brand rendering rules | IN PROGRESS (this document, §6) |
| Colour palette | IN PROGRESS ([`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md)) |
| Typography | IN PROGRESS ([`TYPOGRAPHY.md`](TYPOGRAPHY.md)) |
| Brand guidelines for external / marketing use | NOT STARTED |
