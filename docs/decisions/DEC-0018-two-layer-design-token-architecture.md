# DEC-0018 — Two-Layer Design Token Architecture

**ID:** DEC-0018
**Title:** Two-Layer Design Token Architecture
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

Step 2 must produce a design foundation that four surfaces — Customer Android, Ops Android,
Console Web and the Public Tracking Portal — can share without drifting apart, and that later Steps
can extend without renegotiating what a colour means.

The failure mode this decision exists to prevent is well understood: a hex value copied into a
component, then copied again, until the same brand colour exists in fourteen slightly different
forms and no one can change it safely. On a product where colour carries operational meaning —
`OFFLINE` versus `SYNCING` versus `CONFLICT` — that drift is not cosmetic. It produces a cashier who
misreads whether the server accepted a payment.

## Options considered

1. **Single flat token set.** One list of named values. Simple, but a token named `blue700` carries
   no meaning, so every consumer re-decides what it is for, and the meaning lives nowhere.
2. **Two layers — primitive and semantic.** Primitives hold values; semantics hold meaning and
   reference a primitive. A component names a meaning, never a value.
3. **Three layers — primitive, semantic and component alias.** As above, plus a component-scoped
   layer so a component specification names one token that is unambiguously its own.
4. **No tokens; specify colours in prose per component.** Rejected immediately: unenforceable, and
   contrast could never be checked mechanically.

## Decision

**Option 3 is adopted, with colour treated differently from every other category.**

- **Primitive** tokens hold literal values and carry no meaning. They are never named by a component.
- **Semantic** tokens carry meaning and reference a primitive. `color.semantic.conflict` is the
  single place the meaning of "local and server disagree" is bound to a colour.
- **Component aliases** are component-scoped and reference a semantic colour, or — for dimension,
  motion, elevation and typography — the corresponding primitive directly.

Colour is the exception that requires the semantic tier, because a hex value carries no meaning on
its own. `size.touch.min` and `motion.reduced.duration` already carry their role in the name, so a
separate semantic tier for those categories would add indirection without adding meaning.

The layering is machine-enforced, not merely documented.

## Consequences

Every colour a component uses is changeable in exactly one place. Contrast can be recomputed
mechanically because the value and its declared target sit together. A hard-coded colour in a
component specification becomes a validator failure rather than a review comment somebody may or
may not make.

### Positive consequences

- One source of truth per meaning; a brand change is a single edit at the semantic layer.
- Contrast is computed from the token file, so an accessibility regression fails CI rather than
  reaching a user.
- The four surfaces share meaning while remaining free to adapt presentation.
- `validate-token-references.py` can prove there is no orphan, no unresolved reference and no cycle.

### Negative consequences / trade-offs

- Three layers is more indirection than a small product needs, and a newcomer must learn the rule
  that a component never names a primitive colour.
- Every new colour meaning costs two edits (a primitive and a semantic) rather than one.
- The layering rule is only as strong as the validator that enforces it; if that validator were
  weakened, the architecture would decay silently. This is why weakening a validator is itself a
  governance violation under Rule 00.

## Verification

`python3 scripts/validate-design-tokens.py` (structure, uniqueness, contract completeness),
`python3 scripts/validate-token-references.py` (resolution, circularity, layering, orphans), and
`python3 scripts/validate-color-contrast.py` (contrast recomputed from hex).

Adversarial mutations 1, 2 and 3 in `scripts/test-step-02-validators.sh` prove those validators turn
red on a duplicate token, an unresolved reference and a circular reference respectively.

At the time of writing the token set holds 249 tokens with 0 duplicates, 0 unresolved references and
0 circular references. **No runtime consumes these tokens. The design token system is
`NOT IMPLEMENTED`.**

## Requirement references

NFR-041 … NFR-050 (maintainability and consistency), FR-001 … FR-120 in so far as each has a UI surface classified in `docs/quality/STEP_02_TRACEABILITY.md`.

## Threat references

DUX-017 (colour-only status), DUX-018 (focus loss) — both depend on the semantic layer existing so that status colour and focus colour are bound in one place.

## Rule references

Rule 25 (design system foundation), Rule 26 (design token governance), Rule 33 (design traceability).

## Supersession policy

Superseded only by a new decision record that states which layer model replaces this one and how
existing token names migrate. Token names are permanent: a rename silently changes the meaning of
every specification that cited it, so a rename requires a decision record and a migration note in
`docs/design/DESIGN_DECISION_LOG.md`.

## Related Master Source sections

§18 (UX and design foundation), §18.4 (foundation delivery), §5 (Platforms), §24 (roadmap).
