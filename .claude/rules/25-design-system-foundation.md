# Rule 25 — Design System Foundation

## Purpose

To fix one visual and structural language for every Aish Laundry App surface, so that later Steps extend a
single product rather than assemble three apps that happen to share a name. Delivered in Step 2.

Canonical visual decisions come from the Master Source (§ design foundation) and
[`DEC-0004`](../../docs/decisions/DEC-0004-flutter-client-and-web-console.md). This rule operationalises
them; it never extends them.

## Hard rules

1. **`docs/MASTER_SOURCE.md` is the highest decision authority.** Every design decision derives from it.
   Where a design proposal and the Master Source disagree, the design proposal is wrong. A visual or
   interaction decision that the Master Source does not cover is an **open question for the repository
   owner**, never a licence to invent one (Rule 00).
2. **`docs/product/PRODUCT_REQUIREMENTS.md` is the authoritative requirement source.** A screen, component,
   or flow exists because a requirement asks for it. A requirement that appears only in a design file, a
   wireframe annotation, or a pull request description **does not exist** (Rule 16).
3. **The palette is fixed**: white, soft blue, dark blue, and a **restrained** gold accent. The gold is an
   accent, not a theme. Adding, replacing, or re-weighting a palette role requires an accepted decision
   record.
4. **The tone is professional, light, and not futuristic**, relevant to Indonesian UMKM. Heavy animation is
   avoided; motion serves comprehension, never decoration.
5. **The light theme is the MVP theme.** It is the only theme Step 2 defines, and it is the theme every
   component is specified against.
6. **Dark mode is deferred.** It carries the status `PLANNED`. It is never described as available,
   partially available, or "supported by the tokens". A deferred theme is not a delivered theme.
7. **Typography, spacing, elevation, and iconography are tuned for the stated operating environment** —
   low-end Android hardware and bright shop lighting — not for a design tool preview on a high-end display.
8. **Iconography is a curated first-party set.** Icons are never fetched at runtime from a third party and
   never assembled from user-supplied content (Rule 32).
9. **The final logo must never be fabricated.** No agent generates, improvises, or presents a final mark as
   approved. Brand slots in design artefacts carry an explicitly labelled placeholder until the owner
   confers the mark. Presenting an invention as an owner decision is a false claim under Rule 01.
10. **One component, one purpose.** A second component with the same purpose as an existing one is a review
    rejection, not a variant.
11. **The design system does not introduce a product fact.** A screen that implies a capability the PRD does
    not carry is a false claim, regardless of how plausible it looks in a wireframe (Rule 01).

## Step 2 note

**No design system is implemented.** There is no theme, no widget, no component library, no `pubspec.yaml`,
and no code. The Flutter workspace is `ABSENT` and the backend runtime is `ABSENT`. Step 2 produces
**documentation only**. A design token is not a theme; a wireframe is not a screen (Rule 35).

## Violation handling

- **A design decision made against the Master Source** — the design is wrong; correct it and report the
  drift. Never edit the Master Source to match a design file.
- **A screen or component with no requirement in the PRD** — resolve it before the Step closes: add the
  requirement properly, or remove the artefact.
- **A palette role added, replaced, or re-weighted without a decision record** — revert and escalate.
- **Dark mode described as available, or as delivered by the token layer** — correct it immediately; dark
  mode is `PLANNED`.
- **A fabricated final logo or brand mark** — remove it and replace it with a labelled placeholder; report
  that the earlier artefact was an invention.
- **A duplicate component introduced alongside an existing one** — reject; extend the existing component or
  record why a genuinely new one is needed.
- **Any Flutter, Dart, or theme code created during Step 2** — remove it and report the scope breach
  (Rule 35).
