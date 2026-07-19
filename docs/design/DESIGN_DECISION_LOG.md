# Design Decision Log — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY

---

## 1. What this log is, and what it is not

This log records **design-level decisions taken within Step 2's authority** — decisions about how the
design system is constructed, not about what the product is.

### Identifier scheme

Entries are numbered **`DEC-D-###`** (design decision). This is deliberately distinct from the
**`DEC-####`** governance decision records under `docs/decisions/`, so the two namespaces can never
collide.

| | `DEC-####` (governance) | `DEC-D-###` (design) |
|---|---|---|
| Lives in | `docs/decisions/` | This file |
| Decides | Product decisions | Design-system construction |
| Authority | Repository owner | Step 2, within its boundary |
| Changing it | Requires an owner decision record and a Master Source review | Requires a superseding entry here |

**If a question turns out to be a product decision, it does not belong here.** It goes to the owner as
an open question (Rule 00, rule 6). Section 4 lists exactly what Step 2 has no authority over.

### Rules

1. **Identifiers are permanent and never reused.** A superseded entry keeps its ID and gains a
   supersession note.
2. **Every entry states the alternatives considered and why they were rejected.** A decision with no
   rejected alternative was not a decision.
3. **Every entry states what would justify revisiting it.** A decision that can never be wrong is a
   belief.
4. **No entry may contradict the Master Source, a governance decision record, `CLAUDE.md`, or
   `.claude/rules/**`.** Conflict resolution order is fixed (Rule 00, rule 4).
5. **No entry invents a product decision.**

---

## 2. Decisions

### DEC-D-001 — Three-layer token architecture with fixed naming

- **Status:** Accepted
- **Decision:** Tokens are organised in three layers — primitive, semantic, component-consuming — with
  a fixed naming convention (`DESIGN_SYSTEM.md` §3). **A component never references a primitive
  directly.**
- **Context:** Without a semantic layer, every component hard-codes a palette value, and any theme
  change becomes a rewrite of every component.
- **Alternatives rejected:**
  - *Primitives only* — simplest, but makes a dark theme a full rewrite and makes "why is this blue?"
    unanswerable.
  - *Four or more layers* (adding a component-token layer) — more precise, but the indirection cost
    outweighs the benefit at 70 components.
- **Consequences:** Positive — a dark theme becomes a remapping, and colour intent is legible in code.
  Negative — one extra indirection to trace, and semantic names must be maintained honestly (a token
  named `success` used for a warning is worse than a raw hex).
- **Revisit if:** The component count grows past a few hundred, or two surfaces need materially
  different component-level values.

### DEC-D-002 — 4 pt spacing grid rather than 8 pt

- **Status:** Accepted
- **Decision:** A 4 pt base unit (`SPACING_SIZING_DENSITY.md` §1).
- **Context:** The product needs 44 and 52 dp control heights, 12 dp dense-table gaps, and 20 dp
  comfortable padding. An 8 pt grid forces each to round to a value that is either wasteful or cramped.
- **Alternatives rejected:**
  - *8 pt grid* — fewer choices and easier to police, but cannot express the density range this
    product needs across compact tables and courier surfaces.
  - *No grid* — rejected outright; produces the 15/18/25 dp drift that makes a system incoherent.
- **Consequences:** Positive — enough resolution for three densities. Negative — more values to choose
  from, so the "16 dp first, justify anything else" default is stated explicitly.
- **Revisit if:** Audit shows the extra resolution is unused and drift is occurring.

### DEC-D-003 — Primary interactive colour resolves to `color.blue.600`, not `color.blue.500`

- **Status:** Accepted
- **Decision:** `color.semantic.primary` resolves to `color.blue.600`. `color.blue.500` remains the brand's "soft blue" but is **prohibited as text on white and as a filled
  background carrying white normal-size text**.
- **Context:** Master Source §18.1 names "soft blue" as the interactive colour. **Measured,
  `color.blue.500` is 3.87:1 on white** — it fails the 4.5:1 normal-text threshold in both directions
  (`COLOR_AND_CONTRAST.md` §2.2). Shipping it as the primary text and button colour would build a
  contrast failure into every screen.
- **Alternatives rejected:**
  - *Use `color.blue.500` and accept the failure* — rejected. Contrast is not negotiable
    (`DESIGN_PRINCIPLES.md` P3), and NFR-027 requires accessible ratios.
  - *Use `color.blue.500` only at large text sizes* — passes at 3:1, but would mean the primary action
    colour changes with text size, which is incoherent.
  - *Lighten the whole ramp* — would move further from the accessible operating point, not closer.
- **Consequences:** Positive — every primary interaction clears AA at 5.79:1, and pressed and hover
  states clear 7.86:1 and 10.72:1. Negative — the interface reads marginally deeper than the lightest
  reading of "soft blue". This is a legibility gain, and `color.blue.500` remains available for
  boundaries, large text, and chart marks, so the brand family is unchanged.
- **This is not a product decision.** Master Source §18.1 specifies a colour *family* and a *tone*; it
  does not specify a hex value. Selecting the accessible operating point within that family is design
  work. **Had the Master Source specified an exact hex that failed contrast, that would have been an
  open question for the owner, not a design decision.**
- **Revisit if:** The owner specifies an exact brand hex, or WCAG thresholds change.

### DEC-D-004 — `color.neutral.500` added solely as an accessible interactive border

- **Status:** Accepted
- **Decision:** `color.neutral.500` (4.17:1 on the page surface) is used as
  `color.semantic.border.interactive`.
- **Context:** WCAG 2.2 SC 1.4.11 requires 3:1 for an interactive component's boundary. **Measured,
  `color.neutral.400` is 2.50:1 and `color.neutral.300` is 1.61:1 — both fail.** `color.neutral.500`
  passes at 4.36:1 but is visually heavy for a resting field outline.
- **Alternatives rejected:**
  - *Use `color.neutral.400`* — fails the threshold. Rejected.
  - *Use `color.neutral.500`* — passes, but every text field would carry a border heavier than the
    brand's light, clean attribute permits.
  - *Shift the whole neutral ramp darker* — would affect surfaces and text tokens that are already
    correct.
- **Consequences:** Positive — interactive boundaries clear 3:1 (3.37:1 on white, 3.19:1 on the sunken
  surface) at the lightest weight that does so. Negative — one off-scale ramp step, which is a small
  inconsistency accepted in exchange for a measured accessibility pass.
- **Revisit if:** The neutral ramp is regenerated, at which point 450 should be absorbed into a
  redesigned scale.

### DEC-D-005 — `color.semantic.conflict` is a distinct hue from warning and danger

- **Status:** Accepted
- **Decision:** `SYNC_CONFLICT` gets its own semantic colour, `color.semantic.conflict`, which
resolves to `color.violet.500` (7.37:1),
  distinct from warning (amber) and danger (red).
- **Context:** A sync conflict is neither an error nor an advisory. **Both values are valid and a human
  must choose** (Rule 07, rule 5). It is the highest-consequence state in the offline design, because
  getting it wrong means a wrong payment amount.
- **Alternatives rejected:**
  - *Reuse warning* — would make a conflict indistinguishable from a soft advisory in a queue of thirty
    items.
  - *Reuse danger* — would imply the system already failed, which it has not.
  - *Reuse a purple or violet* — rejected on brand grounds; it reads as futuristic
    (`BRAND_FOUNDATION.md` §2).
- **Consequences:** Positive — conflicts are findable at a glance. Negative — one more hue in a
  deliberately closed palette. Mitigated because conflict is rare and its presentation always carries
  the alert-diamond icon and the label "Perlu Diperiksa".
- **Revisit if:** Field use shows conflicts are confused with errors despite the distinct icon and
  label.

### DEC-D-006 — Four breakpoints, viewport-based, decoupled from density

- **Status:** Accepted
- **Decision:** compact < 600 · medium 600–1023 · expanded 1024–1439 · wide ≥ 1440, measured on the
  viewport. **Density is assigned by context, never by breakpoint**
  (`RESPONSIVE_FOUNDATION.md` §1).
- **Context:** Two failure modes had to be prevented: device detection ("is tablet") producing wrong
  layouts on split screens and unusual form factors, and wide screens automatically producing dense
  layouts.
- **Alternatives rejected:**
  - *Device-class detection* — brittle and wrong on split-screen, foldables, and browser zoom.
  - *Three breakpoints* — insufficient; the 1366 × 768 Console baseline and a 1440+ desktop want
    different treatment of the content cap.
  - *Density derived from breakpoint* — rejected explicitly. A courier on a large screen still needs
    comfortable density (`DESIGN_PRINCIPLES.md` P9).
- **Consequences:** Positive — layouts respond to actual available space, and density stays a
  deliberate choice. Negative — density must be assigned per context, which is more specification work
  than deriving it automatically. That work is the point.
- **Revisit if:** Real device distribution shows a boundary in the wrong place.

### DEC-D-007 — Navigation: bottom navigation on Android, side navigation on Console, none on the portal

- **Status:** Accepted
- **Decision:** Per-surface navigation patterns as specified in `PLATFORM_ADAPTATION.md`, with a
  navigation rail at medium width on Android.
- **Context:** One shared navigation pattern across four surfaces would be wrong on at least three of
  them. The portal in particular needs no navigation at all — it answers one question.
- **Alternatives rejected:**
  - *A drawer on Android* — hides destinations behind a tap and is out of thumb reach on a large phone.
  - *Bottom navigation on Console Web* — wastes vertical space on the tightest axis at 1366 × 768 and
    ignores what a pointer and keyboard make possible.
  - *Any navigation chrome on the portal* — costs load time and implies there is somewhere else to go.
- **Consequences:** Positive — each surface uses the pattern its input model deserves. Negative — four
  navigation implementations rather than one, which is the honest cost of not enlarging a phone layout
  into a desktop one.
- **Revisit if:** A surface's role changes materially.

### DEC-D-008 — Three named densities, assigned explicitly per context

- **Status:** Accepted
- **Decision:** compact, standard, comfortable — with an explicit assignment table and hard
  prohibitions (`SPACING_SIZING_DENSITY.md` §5).
- **Context:** A finance user reconciling 400 rows and a courier at a gate in the rain have opposite
  needs, and both are real users of the same design system.
- **Alternatives rejected:**
  - *One density* — would either be too sparse for the receivables table or too dense for the courier
    screen.
  - *Two densities* — insufficient; the courier and financial-confirmation cases need more room than
    "standard", not merely the same as it.
  - *User-selectable density* — a real option, but it does not remove the need for a correct default
    per context, and it adds a preference surface with no requirement behind it.
- **Consequences:** Positive — density serves the task. Negative — three sets of values to maintain, and
  every context needs an explicit assignment. The prohibitions are the load-bearing part: **compact is
  banned from all Android surfaces and from every financial confirmation.**
- **Revisit if:** A fourth genuinely distinct usage context emerges.

### DEC-D-009 — System-first font strategy; no font binary committed

- **Status:** Accepted
- **Decision:** Platform system font stacks. **No `.ttf`, `.otf`, `.woff`, or `.woff2` is committed, and
  no font CDN is referenced** (`TYPOGRAPHY.md` §1).
- **Context:** The public tracking portal must load fast on a low-end Android browser over a poor
  network. A webfont is a render-blocking round trip on exactly that path. The repository is also PUBLIC,
  making any committed font licence a permanent public artefact.
- **Alternatives rejected:**
  - *A self-hosted webfont* — best brand control, worst portal performance, and adds licence exposure.
  - *A CDN webfont* — adds a third-party dependency and a privacy consideration for portal visitors,
    both outside Step 2's authority.
  - *A variable font subset* — smaller, but still render-blocking and still a committed binary.
- **Consequences:** Positive — no FOIT/FOUT, no licence exposure, respects user font preferences, zero
  bytes on the critical path. Negative — typography is not brand-distinctive, and **metrics differ
  between Roboto and Segoe UI, so every layout must be metric-tolerant to roughly ±10%.** Recorded as
  `DEBT-002`.
- **Revisit if:** Portal performance budgets prove generous enough to absorb a font, and a licence
  review clears.

### DEC-D-010 — Accessibility baseline is WCAG 2.2 AA, stated as designed-not-tested

- **Status:** Accepted
- **Decision:** The target is WCAG 2.2 Level AA, stated verbatim everywhere as
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.** Touch targets are set at
  48 × 48 dp, exceeding SC 2.5.8's 24 × 24 minimum.
- **Context:** Master Source §18.2 rule 6 requires accessible contrast, adequate targets, and font
  scaling. WCAG 2.2 AA is the recognised standard that operationalises those. **Claiming conformance
  without testing would be a false claim under Rule 01.**
- **Alternatives rejected:**
  - *WCAG 2.1 AA* — 2.2 adds SC 2.4.11 (Focus Not Obscured), 2.5.7 (Dragging Movements), 2.5.8 (Target
    Size), and 3.3.8 (Accessible Authentication), all of which matter directly here — sticky action
    bars, courier drag interactions, and OTP entry.
  - *AAA* — 7:1 contrast would eliminate most of the brand palette, and no requirement asks for it.
  - *Claiming compliance* — rejected outright. Nothing has been tested (Rule 01).
  - *24 × 24 dp targets* — technically conformant, but wrong for a user in a hurry with wet hands.
- **Consequences:** Positive — a recognised, testable baseline, honestly stated. Negative — the
  48 dp floor constrains dense layouts, resolved by letting targets overflow their visual bounds in
  compact rows.
- **Revisit if:** WCAG 2.3 is published, or a regulatory requirement applies.

### DEC-D-011 — Seventeen-state UX taxonomy applied to every component

- **Status:** Accepted
- **Decision:** Seventeen canonical states, and **every component is resolved against every state as
  APPLICABLE or NOT APPLICABLE, with no blank cells**
  (`COMPONENT_STATE_MATRIX.md`).
- **Context:** The states that get forgotten — offline, syncing, conflict, permission denied, expired,
  revoked — are precisely the ones this product cannot afford to forget. They are where an offline-first,
  multi-tenant, money-handling product actually fails.
- **Alternatives rejected:**
  - *A conventional set* (default/hover/focus/disabled/error) — omits every state unique to this
    product's risk profile.
  - *Per-component ad hoc states* — produces inconsistency and, worse, silent omission.
  - *Leaving inapplicable cells blank* — rejected. **A blank is an undecided design, and an undecided
    design becomes an implementer's guess.**
- **Consequences:** Positive — offline, conflict, and permission states are designed rather than
  discovered. Negative — a large matrix to maintain; mitigated by generating it from the catalog so a
  new component cannot arrive with an unresolved row.
- **Revisit if:** A genuinely new state class emerges that none of the seventeen covers.

### DEC-D-012 — Status presentation is always text + icon + colour

- **Status:** Accepted
- **Decision:** Every status renders all three, with **text as the primary carrier and colour as the
  least important**.
- **Context:** Master Source §18.2 rule 2 and NFR-026 require that status is never conveyed by colour
  alone. This is both an accessibility requirement and a shop-floor-lighting requirement.
- **Alternatives rejected:**
  - *Colour + icon* — still fails for a user who cannot distinguish the icons at 16 dp in bright light.
  - *Colour + text* — workable, but the icon materially aids scanning a long list.
  - *Text only* — accessible but slow to scan, and scanning speed matters at a counter.
- **Consequences:** Positive — the interface works in greyscale, in sunlight, and with a screen reader.
  Negative — badges are wider, which is why they must wrap rather than truncate at 200% scaling.
- **Revisit if:** Never, for the colour-alone prohibition — it is inherited from the Master Source and is
  not Step 2's to change.

### DEC-D-013 — `CANCELLED` is neutral-coloured, not red

- **Status:** Accepted
- **Decision:** `CANCELLED` uses `color.semantic.neutral`. Red is reserved for `ISSUE`, `FAILED`, and
  genuine failures (`COLOR_AND_CONTRAST.md` §8).
- **Context:** A cancelled order is a legitimate terminal outcome with a recorded reason and actor
  (FR-058), not an error. Colouring it red would train users to ignore red.
- **Alternatives rejected:**
  - *Red for `CANCELLED`* — intuitive at first glance, but dilutes red's meaning across a list where
    cancellations are routine.
  - *A dedicated fifth colour* — unnecessary; neutral plus a distinct icon and label is sufficient.
- **Consequences:** Positive — red retains its urgency. Negative — a cancelled order is slightly less
  visually prominent, mitigated by the distinct circle-slash icon and the explicit label.
- **Revisit if:** Users report missing cancellations in lists.

### DEC-D-014 — Elevation is minimal; borders carry separation

- **Status:** Accepted
- **Decision:** `elevation.0` is the default. Five levels exist, maximum shadow alpha 0.12, and
  elevation is prohibited on rows, chips, badges, inline fields, and above `elevation.1` on the portal
  and on Ops production and courier surfaces (`SHAPE_BORDER_ELEVATION.md` §5).
- **Context:** The brand is light and clean; soft shadows on white read as dirt. More practically,
  **shadows are invisible in direct sunlight and cost frames on the baseline device** — both of which
  describe the Ops app's actual operating conditions.
- **Alternatives rejected:**
  - *A full Material elevation scale* — more depth vocabulary than a business tool needs, and heavier to
    render.
  - *No elevation at all* — modals and sheets genuinely need to read as above the page.
- **Consequences:** Positive — flatter, faster, legible outdoors. Negative — hierarchy relies more on
  borders and spacing, which requires more care in layout. That is the correct trade for this product's
  environment.
- **Revisit if:** Usability testing shows modal layering is unclear.

### DEC-D-015 — Motion is functional only, with a 400 ms ceiling

- **Status:** Accepted
- **Decision:** Every animation must answer one of four questions, or it is removed. Maximum duration
  400 ms. No spring, bounce, or overshoot. Only opacity and transform animate
  (`MOTION_AND_REDUCED_MOTION.md`).
- **Context:** Master Source §18.2 rule 8 states it directly: motion is functional, never decorative,
  and low-end Android is the baseline.
- **Alternatives rejected:**
  - *A conventional motion system with expressive easing* — pleasant on a flagship, stuttering on the
    baseline device, which makes the product feel broken exactly where it must feel reliable.
  - *No motion at all* — removes genuine orientation cues, particularly for sheets and drawers.
- **Consequences:** Positive — smooth on cheap hardware, less to specify, less to break. Negative — the
  product will feel plainer than a consumer app. That is consistent with the brand.
- **Revisit if:** The baseline device assumption changes materially.

### DEC-D-016 — Reduced motion removes movement, never feedback

- **Status:** Accepted
- **Decision:** Under reduced motion, animations become cross-fades or instant changes; **spinners and
  shimmers stop rotating and are replaced by static indicators plus explicit text**
  (`MOTION_AND_REDUCED_MOTION.md` §5).
- **Context:** The naive implementation of reduced motion — disabling all animation — leaves a user
  unable to tell that anything is happening. A stopped spinner with no text is worse than a spinning
  one.
- **Alternatives rejected:**
  - *Disable all animation* — removes information along with movement.
  - *Ignore the preference* — inaccessible, and causes real discomfort for vestibular-sensitive users.
- **Consequences:** Positive — the preference is honoured without information loss. Negative — every
  animated state needs a static equivalent specified, which is more work per component and is the point.
- **Revisit if:** Never on the principle; individual mappings may be refined.

### DEC-D-017 — Canonical identifiers in English, user-facing labels in Bahasa Indonesia

- **Status:** Accepted
- **Decision:** `READY_FOR_PICKUP` in code, events, API fields, and logs. "Siap Diambil" in every user
  interface. **One-to-one mapping, defined once** (`UX_COPY_GLOSSARY.md`).
- **Context:** Master Source §1.6 makes Bahasa Indonesia the user-facing language; Rule 17 makes the
  domain glossary binding and prohibits synonyms; Rule 19 fixes the canonical status spellings.
- **Alternatives rejected:**
  - *Indonesian identifiers in code* — would fork the domain vocabulary against the Master Source's
    canonical spellings and complicate every API contract.
  - *English labels in the UI* — contradicts §1.6.
  - *Per-tenant label overrides* — would make one status mean different things in different tenants and
    break every shared surface, including the portal.
- **Consequences:** Positive — one canonical vocabulary, one user-facing vocabulary, no drift.
  Negative — a mapping to maintain, and a translation must never be invented ad hoc at a call site.
- **Revisit if:** A second user-facing language is added, at which point the mapping becomes
  one-to-many.

### DEC-D-018 — Indonesian numeric formats are fixed and money is never abbreviated

- **Status:** Accepted
- **Decision:** `Rp79.000` · `1,5 kg` · `3 pcs` · `08:00–10:00` · `19 Juli 2026` · `H+3`. **Money is
  never abbreviated** (`Rp1,2jt` is prohibited everywhere, including charts and KPI cards) and never
  truncated (`TYPOGRAPHY.md` §6, `UX_COPY_GLOSSARY.md` §10).
- **Context:** Money is integer Rupiah (Rule 04, rule 1). The users reconcile cash by hand. An
  abbreviated or truncated amount is a correctness failure, not a formatting preference
  (`DESIGN_PRINCIPLES.md` P1).
- **Alternatives rejected:**
  - *Abbreviating on charts and KPI cards* — the usual excuse is axis width. Rejected: the axis is
    rotated, the chart is made horizontal, or the unit is stated once in the axis title. **The number is
    not abbreviated.**
  - *Decimal Rupiah* — there is no sub-Rupiah unit.
- **Consequences:** Positive — every displayed amount is exact and reconcilable. Negative — some layouts
  must accommodate longer strings, which is why numeric-emphasis text never wraps and containers grow
  instead.
- **Revisit if:** Never for the abbreviation prohibition; it derives from Rule 04.

### DEC-D-019 — 48 × 48 dp minimum touch target, 56 dp on courier surfaces

- **Status:** Accepted
- **Decision:** 48 dp floor everywhere; 56 dp on courier surfaces; 64 dp for proof capture. **Density
  and text scaling never reduce a target.**
- **Context:** NFR-027 and NFR-028. The Ops app is used one-handed, outdoors, in a hurry, sometimes in
  the rain, on a cheap phone.
- **Alternatives rejected:**
  - *24 × 24 dp* (the WCAG 2.2 SC 2.5.8 minimum) — conformant but wrong for the actual usage context.
  - *44 dp* — a common platform convention, but 48 aligns to the 4 pt grid and is the Android
    convention.
- **Consequences:** Positive — reliable interaction under adverse conditions. Negative — dense tables
  need targets that overflow their visual row bounds, which is specified rather than left to chance.
- **Revisit if:** Never downward. Upward, per surface, if field use warrants it.

### DEC-D-020 — Light theme is canonical for MVP; dark theme deferred

- **Status:** Accepted
- **Decision:** Light theme only. **Dark theme is PLANNED / NOT IMPLEMENTED and is never described as
  available.** No dark value is specified anywhere.
- **Context:** The brand is white-dominant (Master Source §18.1), and the primary environment is a
  brightly lit shop. No requirement asks for a dark theme.
- **Alternatives rejected:**
  - *Ship both* — doubles the contrast verification surface for a theme nothing requires, and would mean
    doubling `DEBT-006`'s unverified surface.
  - *Dark-first* — contradicts the brand and reads as futuristic (`BRAND_FOUNDATION.md` §2).
- **Consequences:** Positive — one theme to specify and verify. Negative — users preferring dark
  interfaces are not served; recorded honestly as `DEBT-004`. The semantic token layer (DEC-D-001) keeps
  the door open at low cost.
- **Revisit if:** A requirement is raised, or field use in low-light environments proves significant.

---

## 3. Summary

| ID | Decision | Status |
|---|---|---|
| DEC-D-001 | Three-layer token architecture with fixed naming | Accepted |
| DEC-D-002 | 4 pt spacing grid rather than 8 pt | Accepted |
| DEC-D-003 | Primary resolves to `color.blue.600`, not `.500` | Accepted |
| DEC-D-004 | `color.neutral.500` added as an accessible border | Accepted |
| DEC-D-005 | `conflict` is a distinct hue from warning and danger | Accepted |
| DEC-D-006 | Four viewport breakpoints, decoupled from density | Accepted |
| DEC-D-007 | Per-surface navigation patterns | Accepted |
| DEC-D-008 | Three densities, assigned explicitly by context | Accepted |
| DEC-D-009 | System-first fonts; no font binary | Accepted |
| DEC-D-010 | WCAG 2.2 AA baseline, stated as designed-not-tested | Accepted |
| DEC-D-011 | Seventeen-state taxonomy, no blank cells | Accepted |
| DEC-D-012 | Status is always text + icon + colour | Accepted |
| DEC-D-013 | `CANCELLED` is neutral, not red | Accepted |
| DEC-D-014 | Minimal elevation; borders carry separation | Accepted |
| DEC-D-015 | Functional motion only, 400 ms ceiling | Accepted |
| DEC-D-016 | Reduced motion removes movement, not feedback | Accepted |
| DEC-D-017 | English identifiers, Indonesian labels | Accepted |
| DEC-D-018 | Fixed Indonesian formats; money never abbreviated | Accepted |
| DEC-D-019 | 48 dp touch target, 56 dp for couriers | Accepted |
| DEC-D-020 | Light theme canonical; dark theme deferred | Accepted |

**20 decisions. None superseded.**

---

## 4. What Step 2 has NO authority over

This section is as important as the decisions above. **Step 2 did not decide any of the following, and
must not.** Each is either a product decision, a commercial decision, a legal question, or a
third-party dependency — and inventing an answer would be a governance violation (Rule 00, rule 6;
Rule 12).

| Question | Why it is outside Step 2 | Where it goes |
|---|---|---|
| **Payment provider** | Introducing a third-party service requires owner approval (Rule 12). Commercial and security implications | Owner decision + decision record; Step 5 |
| **WhatsApp provider** | A third-party service with per-message cost. Rule 08 mandates provider abstraction precisely so this is not a design choice | Owner decision + decision record; Step 7 |
| **Storage-fee legality** | Whether a tenant may charge for storing unclaimed laundry is a legal question about Indonesian consumer law. **Step 2 invents no fee, and no interface element implies one** | Owner + legal review |
| **Final tracking-token expiry period** | A security and product trade-off between convenience and exposure window. The UX is identical whatever the number, so Step 2 does not need it and must not guess | Owner + Step 7; `DEBT-007` |
| **Legal proof-retention periods** | How long delivery photographs and signatures are retained is a personal-data legal question | Owner + legal; Step 8 / Step 13; `DEBT-008` |
| **Tracking portal production framework** | Master Source §5.4 explicitly assigns this to the Step that builds the surface, with a decision record. The design is stack-independent by construction | Step 7; `DEBT-010` |
| **Map provider** | A third-party service requiring owner approval, with cost and privacy implications | Owner + Step 8; `DEBT-011` |

### Additional boundaries

Step 2 also has **no authority** over: pricing or plan structure (Rule 14 — owner territory); the
roadmap or step numbering (Rule 03); canonical status sets, which are fixed by Master Source §19 and
require a decision record to change (Rule 19, rule 1); the tenant hierarchy (Rule 02); the reminder
ladder stages (Rule 10); repository visibility or settings (Rule 11, Rule 12); the Master Source
version or checksum (Rule 00); and **conferring `GO` on any Step** — that is the owner's alone
(Rule 01).

### The rule that governs this section

Where a design depends on one of these answers, **the design records the dependency and stops.** It
does not choose a plausible value "as a placeholder", because a placeholder in a specification gets
read as a decision by whoever implements it.
