# DEC-0020 — System-First Typography; No Font Binary Committed

**ID:** DEC-0020
**Title:** System-First Typography; No Font Binary Committed
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

Typography must serve a counter operator reading an order at speed, a courier reading an address
outdoors, and an owner reading a financial column. It must also download quickly on a low-end
Android device on a congested Indonesian mobile network (§19).

There is a second constraint that is easy to overlook: this repository is **PUBLIC**
(AMENDMENT-0001, DEC-0016). A committed font binary is a licensed artifact published to the world,
permanently, with deletion offering no remediation.

## Options considered

1. **Ship a custom or licensed brand typeface.** Strongest brand expression, but adds download
   weight on the exact devices least able to absorb it, and commits a licensed binary to a public
   repository.
2. **Ship an open-licensed webfont (e.g. a Google Font) as a committed binary.** Licensing is
   tractable, but the download cost and the public-repository binary remain.
3. **System-first font stack, no binary committed.** Zero download cost, no licensing exposure, and
   the platform's own UI face is the one the user already reads comfortably.
4. **System-first now, revisit a brand face when a brand identity is approved.**

## Decision

**Option 3 is adopted, with option 4 as the explicit future path.**

- `font.family.sans` is a system-first stack beginning with `system-ui`.
- `font.family.mono` is a system-first monospace stack, used where a fixed column grid carries
  meaning — receipt previews above all.
- **No font binary is committed to this repository.** `validate-typography.py` walks the entire tree
  and fails if any `.ttf`, `.otf`, `.woff`, `.woff2`, `.eot`, `.ttc` or `.fon` file appears.
- **Tabular figures are mandatory** wherever integer Rupiah amounts, weights, quantities or
  timestamps are stacked in a column. `font.feature.tabularNumbers` is bound by both the money-field
  alias and the numeric-table alias. Misaligned digits in a financial column are a legibility defect
  with a financial consequence.
- Layouts must survive **200% text scaling** without losing a primary action.

This decision is separable from brand identity. The logo is `NOT APPROVED` (DEC-0023) and a brand
typeface is not required to make the product look professional.

## Consequences

Type renders instantly, costs nothing to download, and carries no licence into a public repository.
The trade is a less distinctive typographic voice, which the brand does not currently depend on.

### Positive consequences

- No download weight, which matters most on the devices and networks the product actually targets.
- No licensed binary published irrevocably to a public repository.
- The platform UI face is already optimised for its own rendering stack and text-scaling behaviour.
- Tabular figures are enforced by token binding rather than left to a developer's discretion.

### Negative consequences / trade-offs

- Typography is less distinctive, and the product will look subtly different across Android
  versions, iOS, Windows and Linux.
- Precise vertical rhythm is harder to guarantee when the metrics vary by platform.
- A future brand typeface would require re-verifying every line-height pairing in the type scale.

## Verification

`python3 scripts/validate-typography.py` verifies the system-first stacks, the completeness of the
type scale, that every font size has a paired line height, that tabular figures are bound by both
the money and table aliases, that the 200% scaling commitment is stated, and — walking the whole
repository — that no font binary is committed.

At the time of writing that validator reports 24/24 checks passed and 0 font binaries.

## Requirement references

NFR-002 (portal renders quickly on a cold cache), NFR-020, FIN-001 … FIN-040 in so far as money is displayed legibly.

## Threat references

DUX-016 (accessibility exclusion), DUX-027 (public repository exposure) — a committed binary would be an irrevocable publication.

## Rule references

Rule 25, Rule 27 (accessibility foundation), Rule 30 (content design and localization), Rule 31 (responsive and device foundation).

## Supersession policy

Superseded by a decision record that names a specific typeface, its licence, its delivery mechanism,
and whether it is committed or fetched at build time. Any such record must re-verify the type scale
against the new metrics and must state explicitly how the public-repository licensing constraint is
satisfied.

## Related Master Source sections

§18.1 (visual language), §18.3 (copy rules), §19 (performance), §15.8 (public repository).
