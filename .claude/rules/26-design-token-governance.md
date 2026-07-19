# Rule 26 — Design Token Governance

## Purpose

A design token is the smallest unit at which a product stays consistent. Once a raw colour, spacing value,
or font size is written directly into a screen, consistency stops being a property of the system and becomes
a property of whoever last touched that screen. This rule fixes the token layer. Delivered in Step 2,
enforced from Step 3 onward.

## The two-layer model

Tokens exist in exactly two layers:

- **Primitive tokens** — raw values with no meaning attached (a colour value, a spacing step, a type size).
- **Semantic tokens** — a named role that maps to a primitive (surface, on-surface, accent, danger,
  success, warning, disabled, focus, offline, pending).

**Components reference semantic tokens only.** A component never references a primitive token, and never
references a raw value at all.

## Hard rules

1. **Design tokens are never hardcoded without a semantic mapping.** A raw colour, spacing value, radius,
   elevation, or type size written directly into a component, a screen specification, or a copy deck is a
   defect. If a value is needed, it gets a semantic token first.
2. **Semantic tokens are named by role, never by appearance.** `surface-danger`, not `merah-terang`;
   `text-on-accent`, not `putih`. An appearance-named token is unusable the moment a second theme exists and
   will be misapplied long before that.
3. **The token set is the single source of visual truth.** A value that exists in a design artefact but not
   in the token set does not exist.
4. **Adding, renaming, removing, or remapping a semantic token requires a recorded change** and a review of
   every component that references it. A silent remap changes the meaning of every screen that used it.
5. **Token identifiers are permanent.** A withdrawn token keeps its name and gains a withdrawal note.
   Reusing a token name for a different role silently rewrites every artefact that cited it.
6. **The light theme is the MVP theme** and is the only theme Step 2 maps. **Dark mode is deferred** and
   carries the status `PLANNED`; the token layer is structured so a second theme can be introduced by
   remapping semantic tokens rather than by rewriting components, but no dark mapping is defined, claimed,
   or implied (Rule 25).
7. **No component hard-codes a light-theme assumption.** A component that only reads correctly on a light
   background because a value was baked in has defeated the token layer.
8. **Contrast is a property of the token pair, not of the screen.** Every foreground/background semantic
   pairing is specified with its intended contrast outcome, so a component author cannot produce an
   inaccessible combination by choosing two valid tokens (Rule 27).
9. **Status colour is never the sole carrier of meaning.** A status token is always accompanied by a text
   token and an icon token in the component that uses it (Rule 27, Rule 29).
10. **Token names never encode commercial packaging.** No token name references an unreleased plan, an
    unannounced feature, a customer, or a tenant. This repository is `PUBLIC` and token names are published
    (Rule 31).
11. **Tokens carry no data.** A token is a presentation value. It never carries a tenant identifier, a
    customer value, an environment secret, or a configuration string.

## Step 2 note

**No token is implemented.** There is no theme file, no Dart constant, no CSS variable, and no generated
artefact of any kind. The Flutter workspace is `ABSENT`. Step 2 defines the token governance and the token
inventory as **documentation only**. A design token is not a theme.

Mechanical enforcement — a token linter that rejects a raw value — requires a runtime and arrives in
**Step 3 or later**. Until then the constraint is enforced by review, and that limitation is stated rather
than hidden.

## Violation handling

- **A raw value used where a semantic token exists** — reject the change; introduce or reuse the semantic
  token.
- **A primitive token referenced directly by a component** — reject; route it through a semantic token.
- **A token named by appearance rather than role** — rename it before anything else depends on it.
- **A token name reused for a different role** — reject the change; every artefact citing that name has
  silently changed meaning.
- **A token remapped without reviewing its consumers** — the change is incomplete; the pull request is not
  ready.
- **A dark-mode mapping introduced during Step 2, or dark mode described as available** — remove the claim;
  dark mode is `PLANNED` (Rule 25).
- **A token pairing that produces insufficient contrast** — treat as an accessibility defect, not a visual
  preference (Rule 27).
- **A token name disclosing commercial intent** — rename it and report the disclosure (Rule 31).
