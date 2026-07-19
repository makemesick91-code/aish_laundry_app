# Design Debt Register — Aish Laundry App

**Step:** 2 — Design System and UX Foundation
**Status:** IN PROGRESS
**Scope:** DOCUMENTATION ONLY

---

## 1. What this register is

A record of every design question Step 2 **deliberately did not answer**, why, and which Step owes the
answer.

This is not a backlog of nice-to-haves. It exists so that a gap is visible rather than discovered later
as a surprise, and so that nobody mistakes an absence for a decision.

**Debt identifiers are permanent and never reused.** A closed entry keeps its ID and gains a closure
note.

### Rules

1. **Every entry states an owner Step.** Debt with no owner is not deferred; it is abandoned.
2. **Every entry states the consequence of not resolving it**, so the cost of continued deferral is
   visible.
3. **No entry is a placeholder decision.** Where Step 2 lacked authority, the entry says so and the
   question goes to the owner (Rule 00, rule 6). A placeholder gets read as a decision.
4. **An entry is closed only by a real resolution**, recorded here with its date and the decision that
   closed it — never by the passage of time.
5. **Nothing in this register may be described as resolved, implemented, or tested.** Nothing in Step 2
   is.

### Status vocabulary

`OPEN` · `IN PROGRESS` · `PLANNED` · `NOT STARTED` · `CLOSED`. No synonyms.

---

## 2. Register

### DEBT-001 — No approved logo or brand mark

- **Status:** OPEN
- **Owner:** Repository owner (Aish Tech Solution). Not an engineering Step.
- **What is deferred:** A logo, brand mark, monogram, app icon, and favicon.
- **Current position:** `LOGO STATUS: NOT APPROVED`. Every surface uses the text wordmark
  "Aish Laundry App" per [`BRAND_FOUNDATION.md`](BRAND_FOUNDATION.md) §1. No placeholder emblem is
  authored, because a shipped placeholder becomes a de facto logo.
- **Why deferred:** Brand identity is an owner decision. **An agent never invents, generates, or
  approves a logo.**
- **Consequence if unresolved:** The Android app icon and web favicon cannot be produced. Store
  submission and any marketing surface are blocked. Blocking is correct here — a fabricated mark would
  be worse.
- **Resolution requires:** Owner approval plus a decision record.

### DEBT-002 — No branded display typeface

- **Status:** OPEN
- **Owner:** Deferred beyond MVP; revisit no earlier than Step 11.
- **What is deferred:** A distinctive brand typeface for display and marketing contexts.
- **Current position:** System-first font strategy, no font binary committed
  ([`TYPOGRAPHY.md`](TYPOGRAPHY.md) §1).
- **Why deferred:** A webfont costs a render-blocking round trip on the public tracking portal, which
  must load fast on the baseline device and network. Performance outranks brand distinctiveness for MVP,
  and no font licence is committed to a PUBLIC repository (Rule 23).
- **Consequence if unresolved:** Typography is competent but not distinctive. Metrics differ between
  Roboto and Segoe UI, so every layout must remain metric-tolerant.
- **Resolution requires:** A licence review, a performance budget for the portal, and a decision record.

### DEBT-003 — Icon set not selected

- **Status:** OPEN
- **Owner:** The Step that creates `packages/design_system`.
- **What is deferred:** Choosing the concrete open-licensed outline icon family.
- **Current position:** [`ICONOGRAPHY.md`](ICONOGRAPHY.md) specifies icon **concepts**, sizes, stroke
  weights, semantic mappings, and selection criteria. **No icon binary is committed.**
- **Why deferred:** Selection requires evaluating actual rendering at 16 dp on a low-density display,
  which requires a runtime. Step 2 has none.
- **Consequence if unresolved:** Component work cannot begin without glyphs; the semantic mapping is
  complete, so the gap is mechanical rather than conceptual.
- **Resolution requires:** Licence verification, a rendering evaluation against the §1 criteria, and a
  design decision entry.

### DEBT-004 — Dark theme not specified

- **Status:** PLANNED
- **Owner:** Deferred beyond MVP. No Step currently owns it.
- **What is deferred:** The full dark-theme palette and its contrast computation.
- **Current position:** **Light theme is the canonical MVP theme. Dark theme is
  NOT IMPLEMENTED** and is never described as available
  ([`COLOR_AND_CONTRAST.md`](COLOR_AND_CONTRAST.md) §10). No dark value is specified anywhere.
- **Why deferred:** Doubling the palette doubles the contrast verification surface for a theme no
  requirement asks for. The semantic token layer exists precisely so a dark theme can later be added by
  remapping semantics to different primitives **without editing any component specification**.
- **Consequence if unresolved:** Users preferring dark interfaces are not served. Given a
  white-dominant brand and shop-floor lighting as the primary environment, this is an accepted
  trade-off, not an oversight.
- **Resolution requires:** A palette, computed contrast ratios for every semantic pairing, and a
  decision record.

### DEBT-005 — No rendered visual mockups

- **Status:** NOT STARTED
- **Owner:** Step 11 and the subsequent surface-building Steps.
- **What is deferred:** Any rendered screen, high-fidelity mockup, or interactive prototype.
- **Current position:** Step 2 delivers specifications in text. **No screen has been designed, drawn,
  rendered, or reviewed visually.**
- **Why deferred:** Step 2's scope is the foundation, and screen design belongs to the Steps that build
  each surface (Rule 24). Producing mockups now would leak Step 11 work backwards.
- **Consequence if unresolved:** Specifications have not been visually validated. Composition problems
  — visual rhythm, real-data density, how twelve status badges look in one list — will surface only when
  screens are built. **This is the most significant unverified assumption in the entire design system.**
- **Resolution requires:** Screen design in the owning Step, reviewed against these specifications.

### DEBT-006 — Contrast ratios computed, not runtime-measured

- **Status:** OPEN
- **Owner:** Each surface-building Step; automated checking owed by the design-system Step.
- **What is deferred:** Verification of contrast on real devices.
- **Current position:** Every ratio in `COLOR_AND_CONTRAST.md` is **computed from hex values** using the
  WCAG relative-luminance formula. **No colour has been rendered on a device, no screen photographed,
  no scanner run.**
- **Why deferred:** Runtime measurement requires a runtime. Step 2 has none.
- **Consequence if unresolved:** Computed ratios are mathematically correct but say nothing about
  real-world legibility — a cheap display in direct sunlight can defeat a compliant ratio. **The courier
  surfaces are the highest risk**, which is why they specify `color.semantic.border.strong` (6.80:1)
  rather than the interactive default.
- **Resolution requires:** Automated contrast checking in the design-system build, plus a field check on
  a physical low-end device.

### DEBT-007 — Tracking-token expiry period undecided

- **Status:** OPEN
- **Owner:** Step 7 (Customer Tracking and WhatsApp), with owner input.
- **What is deferred:** The concrete validity period of a public tracking token.
- **Current position:** The design specifies that the token **is** expiring and revocable, and that
  expired, revoked, invalid, and non-existent all render the **same** non-enumerable message
  ([`CONTENT_DESIGN.md`](CONTENT_DESIGN.md) §7). **The duration itself is not decided.**
- **Why deferred:** **Step 2 has no authority over it.** It is a security and product trade-off between
  customer convenience and exposure window, and it is not a design variable.
- **Consequence if unresolved:** Step 7 cannot implement expiry. The UX is unaffected — the copy is
  identical whatever the period.
- **Resolution requires:** An owner decision recorded in a decision record. **Step 2 must not invent a
  number.**

### DEBT-008 — Legal retention period for proof artefacts undecided

- **Status:** OPEN
- **Owner:** Repository owner, with legal input. Implementation in Step 8 / Step 13.
- **What is deferred:** How long proof-of-pickup and proof-of-delivery photographs and signatures are
  retained.
- **Current position:** The design specifies that proof artefacts are RESTRICTED, stored privately,
  served only via signed expiring URLs, and tenant-scoped
  ([`COMPONENT_CATALOG.md`](COMPONENT_CATALOG.md) CMP-062, CMP-063). **Retention duration is not
  specified.**
- **Why deferred:** A retention period is a legal question about personal data in Indonesia, not a
  design decision. **Step 2 has no authority to set one.**
- **Consequence if unresolved:** Step 8 cannot implement a retention policy, and the privacy notice
  shown to customers cannot state a period.
- **Resolution requires:** Legal review and an owner decision record.

### DEBT-009 — No independent accessibility audit scheduled

- **Status:** NOT STARTED
- **Owner:** Not scheduled. Requires an owner decision.
- **What is deferred:** An independent, external accessibility audit.
- **Current position:** [`ACCESSIBILITY.md`](ACCESSIBILITY.md) states the posture exactly:
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.** Internal verification is
  owed by each surface-building Step.
- **Why deferred:** An audit needs something to audit. It is recorded now so that self-assessment is
  never mistaken for an audit.
- **Consequence if unresolved:** The product can never claim verified WCAG conformance. **Under
  single-maintainer governance, internal re-verification is not independent review** (Rule 23, rule 22),
  and accessibility defects that both the maintainer and the validators miss will not be caught.
- **Resolution requires:** An owner decision to commission an audit, and a Step to own the remediation.

### DEBT-010 — Tracking portal production framework not selected

- **Status:** OPEN
- **Owner:** Step 7, which builds the portal.
- **What is deferred:** Whether the portal is Flutter Web or a lighter web stack.
- **Current position:** Master Source §5.4 makes Flutter **not** mandatory for this surface and states
  that performance outranks stack uniformity. The design constraints — 320 px, no webfont,
  `elevation.0`–`elevation.1`, fade-and-spinner motion only — hold **regardless** of the stack
  ([`PLATFORM_ADAPTATION.md`](PLATFORM_ADAPTATION.md) §5).
- **Why deferred:** **Step 2 has no authority over it.** Master Source §5.4 assigns the choice to the
  Step that builds the surface, with a decision record.
- **Consequence if unresolved:** None for Step 2. The design is stack-independent by construction.
- **Resolution requires:** A performance evaluation in Step 7 and a decision record.

### DEBT-011 — Map provider not selected

- **Status:** OPEN
- **Owner:** Step 8 (Pickup and Delivery Operations).
- **What is deferred:** Which map provider supplies tiles and geocoding.
- **Current position:** [`COMPONENT_CATALOG.md`](COMPONENT_CATALOG.md) CMP-065 specifies the map as
  **always optional and never blocking**, with a mandatory text fallback, tenant-scoped and
  minimum-precision destination data, and **no route-optimisation claim of any kind**.
- **Why deferred:** Introducing a third-party service is outside Step 2's authority and requires owner
  approval (Rule 12).
- **Consequence if unresolved:** None for Step 2 — the design works without a map by construction, which
  is deliberate.
- **Resolution requires:** Owner approval, a cost and privacy review of the provider, and a decision
  record.

### DEBT-012 — Print and thermal-receipt rendering unverified

- **Status:** OPEN
- **Owner:** Step 5 (POS, Order, and Payment Foundation).
- **What is deferred:** Verification that the receipt specification renders correctly on real 58 mm and
  80 mm thermal printers.
- **Current position:** CMP-031 specifies receipt-numeric typography, tabular figures, right-aligned
  amounts, and no truncation. **Nothing has been printed.**
- **Why deferred:** Requires hardware and a runtime.
- **Consequence if unresolved:** Column alignment on a nota is the most common cause of a customer
  disputing a total. A specification that fails on real hardware would surface late and visibly.
- **Resolution requires:** A print test on both paper widths with real order data (fictional in any
  committed evidence — Rule 23).

### DEBT-013 — Requirement traceability for design specifications incomplete

- **Status:** IN PROGRESS
- **Owner:** Step 2 completion.
- **What is deferred:** Full bidirectional traceability between every design specification and the
  requirement it serves.
- **Current position:** `COMPONENT_CATALOG.md` cites requirement IDs (FR-, NFR-, RPT-, SUB-), Master
  Source sections, and governance rules per component. **The reverse direction — every relevant
  requirement pointing to its design specification — is not yet reflected in
  `docs/product/REQUIREMENT_TRACEABILITY.md`.**
- **Why deferred:** The traceability matrix is a separate artefact and is updated per change (Rule 22,
  rule 14).
- **Consequence if unresolved:** An orphan in either direction is a traceability defect that blocks the
  Step (Rule 22, rule 10).
- **Resolution requires:** Updating the traceability matrix before Step 2 closes.

### DEBT-014 — Colour-blindness simulation not performed

- **Status:** NOT STARTED
- **Owner:** Step 2 review, before Step 11 begins.
- **What is deferred:** Simulating deuteranopia and protanopia across every status set.
- **Current position:** The design mitigates structurally — **every status carries text + icon +
  colour**, and icon silhouettes are chosen to be distinguishable in greyscale
  ([`ICONOGRAPHY.md`](ICONOGRAPHY.md) §4). **No simulation has been run.**
- **Why deferred:** Requires rendered output.
- **Consequence if unresolved:** `color.semantic.success` (6.79:1) and `color.semantic.danger` (6.54:1)
  are close in lightness. **The label and icon are designed to carry the distinction**, but this has not
  been confirmed empirically.
- **Resolution requires:** A simulation review of all status badge sets.

### DEBT-015 — Wireframes are low-fidelity only

- **Status:** ACCEPTED
- **Owner:** Step 11 and the subsequent surface-building Steps.
- **What is deferred:** High-fidelity layout, real typography rendering, real spacing, real colour,
  and real content density in the wireframes under `docs/ux/wireframes/`.
- **Current position:** The wireframes are deliberately **low-fidelity SVG structural diagrams**
  recording layout intent, region hierarchy, and control placement
  ([DEC-0023](../decisions/DEC-0023-low-fidelity-wireframes-and-no-final-logo-fabrication.md)). They
  are **not** mockups and must never be presented as the product's appearance.
- **Why accepted now:** A high-fidelity comp implies a visual decision that Step 2 has not taken and
  cannot take without a rendering runtime. A polished picture would be read as an approved design and
  would quietly pre-empt Step 11.
- **Risk carried:** A reader may mistake a wireframe for a design. Real-content density — long
  Indonesian labels, twelve status badges in one list, a 320 px portal — is **unproven**. Layouts
  that read cleanly as boxes may fail when filled with real strings.
- **Resolution requires:** Screen design in the owning Step, reviewed against these specifications
  and against real content.

### DEBT-016 — No motion prototypes

- **Status:** ACCEPTED
- **Owner:** The Step that creates `packages/design_system`, verified in each surface-building Step.
- **What is deferred:** Any animated prototype demonstrating the specified durations, easings,
  transitions, and the reduced-motion contract.
- **Current position:** [`MOTION_AND_REDUCED_MOTION.md`](MOTION_AND_REDUCED_MOTION.md) specifies
  durations (`motion.duration.fast` through `motion.duration.deliberate`), easing curves, and
  `motion.reduced.duration`. **Nothing has been animated, played back, or reviewed in motion.**
- **Why accepted now:** Motion cannot be evaluated as text, and prototyping it requires a runtime.
  Step 2 has none.
- **Risk carried:** Durations chosen on paper may feel sluggish or abrupt on a low-end device, where
  a nominal 200 ms transition can drop frames and read as a stutter. The reduced-motion path is
  specified but **unexercised**, so a surface could ship honouring it only partially.
- **Resolution requires:** A motion review on real hardware in the owning Step, including the
  reduced-motion path on all four surfaces.

### DEBT-017 — No real-device testing on low-end Android

- **Status:** OPEN
- **Owner:** Each surface-building Step; consolidated in Step 13.
- **What is deferred:** Rendering, legibility, touch-target, performance, and sunlight testing on a
  physical low-end Android device of the kind the target market actually uses.
- **Current position:** [`RESPONSIVE_FOUNDATION.md`](RESPONSIVE_FOUNDATION.md) states the baseline
  device assumptions and the 320 px minimum supported width, and
  [`SPACING_SIZING_DENSITY.md`](SPACING_SIZING_DENSITY.md) fixes `size.touch.min` at 48 dp.
  **No device has been held, no screen photographed, no frame timing measured.**
- **Why accepted now:** There is nothing to install. The Flutter workspace is `ABSENT`.
- **Risk carried:** This is the assumption most likely to be wrong in practice. A counter operator in
  a bright shopfront and a courier outdoors at midday are the two hardest environments in the
  product, and both are currently served only by computed ratios and specified target sizes. Font
  scaling at 200%, cheap-panel colour rendering, and scroll performance in a long order list are all
  **unverified**.
- **Resolution requires:** A field check on at least one physical low-end device per surface, with
  sanitised evidence captured at an exact SHA.

### DEBT-018 — No usability testing performed

- **Status:** SCHEDULED
- **Owner:** Step 14 (Pilot and Commercial Launch) for UAT; earlier informal rounds owned by each
  surface-building Step.
- **What is deferred:** Any usability session with a real kasir, manager, courier, or customer.
- **Current position:** [`../ux/USABILITY_TEST_PLAN.md`](../ux/USABILITY_TEST_PLAN.md) defines the
  method, participants, tasks, and success measures. **The plan has not been executed. UAT is
  `NOT STARTED`.**
- **Why accepted now:** There is no product to put in front of a participant. A plan written now is
  cheap; a plan written after the screens exist tends to be shaped by them.
- **Risk carried:** Every claim about task speed, discoverability, and error recovery in this design
  system rests on reasoning, not observation. The "shortest primary action" principle is asserted,
  **not measured**. If the intake flow is slower in practice than the paper process it replaces,
  nothing in Step 2 would have detected it.
- **Resolution requires:** Executed sessions with recorded outcomes, sanitised before commit
  (Rule 23).

### DEBT-019 — Storage-fee UX not designed

- **Status:** OPEN
- **Owner:** Repository owner (policy and legality), then Step 9 (Unclaimed Laundry and Cashflow
  Recovery).
- **What is deferred:** Any interface for configuring, accruing, displaying, disputing, or waiving a
  storage fee on unclaimed laundry.
- **Current position:** [`../ux/UNCLAIMED_LAUNDRY_UX.md`](../ux/UNCLAIMED_LAUNDRY_UX.md) §6 records
  the fee as **OPTIONAL / TENANT-CONFIGURED / SUBJECT TO POLICY / NOT ASSUMED ACTIVE**, and
  `UXAC-065` states that where a tenant has not configured one, **no column, figure, or zero value
  appears and nothing accrues**. **No fee interface is designed.**
- **Why accepted now:** **Step 2 has no authority here.** Whether a storage fee may lawfully be
  charged, on what notice, and with what dispute route is a legal and owner question about a
  customer's belongings, not a design variable (Rule 00, rule 6; Rule 10).
- **Risk carried:** Designing this surface speculatively would produce a placeholder that reads as a
  decision and would imply the product endorses charging. The absence is deliberate. The residual
  risk is only that Step 9 has no design to build from until the owner decides.
- **Resolution requires:** An owner decision with legal input and a decision record. **Step 2 must
  not invent a fee, a rate, a schedule, or a waiver rule.** Note that Rule 10's absolute prohibition
  stands regardless: the product never discards, sells, auctions, donates, or transfers a customer's
  laundry.

---

## 3. Summary

| ID | Title | Status | Owner |
|---|---|---|---|
| DEBT-001 | No approved logo or brand mark | OPEN | Repository owner |
| DEBT-002 | No branded display typeface | OPEN | Deferred beyond MVP |
| DEBT-003 | Icon set not selected | OPEN | Design-system Step |
| DEBT-004 | Dark theme not specified | PLANNED | Unassigned |
| DEBT-005 | No rendered visual mockups | NOT STARTED | Step 11 and later |
| DEBT-006 | Contrast computed, not runtime-measured | OPEN | Each surface Step |
| DEBT-007 | Tracking-token expiry undecided | OPEN | Step 7 + owner |
| DEBT-008 | Proof-artefact retention undecided | OPEN | Owner + legal |
| DEBT-009 | No independent accessibility audit | NOT STARTED | Unassigned |
| DEBT-010 | Portal framework not selected | OPEN | Step 7 |
| DEBT-011 | Map provider not selected | OPEN | Step 8 |
| DEBT-012 | Print rendering unverified | OPEN | Step 5 |
| DEBT-013 | Design traceability incomplete | IN PROGRESS | Step 2 |
| DEBT-014 | Colour-blindness simulation not run | NOT STARTED | Step 2 review |
| DEBT-015 | Wireframes are low-fidelity only | ACCEPTED | Step 11 and later |
| DEBT-016 | No motion prototypes | ACCEPTED | Design-system Step |
| DEBT-017 | No real-device testing on low-end Android | OPEN | Each surface Step + Step 13 |
| DEBT-018 | No usability testing performed | SCHEDULED | Step 14 + surface Steps |
| DEBT-019 | Storage-fee UX not designed | OPEN | Owner + Step 9 |

**19 entries. None closed.**

`ACCEPTED` marks a debt taken knowingly with its risk stated and its owner Step named. It is not a
softer word for resolved, and it never becomes one through the passage of time (§1, rule 4).

### The five outside Step 2's authority

**DEBT-007** (token expiry), **DEBT-008** (retention), **DEBT-010** (portal framework),
**DEBT-011** (map provider), and **DEBT-019** (storage-fee UX) are **not** deferred by choice. Step 2
has no authority over any of them, and inventing an answer would be a governance violation (Rule 00,
rule 6; Rule 12). They are recorded as open questions for the owner, not as design gaps.

### The three that make every design claim provisional

**DEBT-005** (no rendered mockups), **DEBT-017** (no real-device testing), and **DEBT-018** (no
usability testing) together mean that **nothing in this design system has been observed working**.
Every specification here is reasoned, internally re-verified under single-maintainer governance
(Rule 23, rule 22), and unproven in use. That is the honest position, and it is not softened by the
volume of documentation surrounding it.
