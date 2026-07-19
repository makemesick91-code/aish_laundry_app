# DEC-0023 — Low-Fidelity SVG Wireframes; No Final-Logo Fabrication

**ID:** DEC-0023
**Title:** Low-Fidelity SVG Wireframes; No Final-Logo Fabrication
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

Step 2 must communicate layout intent without producing anything that can be mistaken for a built
interface. Two distinct risks apply.

The first is the **fidelity claim**. A polished mockup circulated outside its context becomes, in
someone's memory, a screen that exists. Rule 01 forbids presenting a design artifact as an
implemented feature, and a high-fidelity image is the easiest way to break that rule accidentally.

The second is the **brand claim**. No logo has been approved by the owner. An agent that generates a
plausible logo and places it in a public repository has manufactured a brand asset the owner never
chose, in a medium where deletion is not remediation.

There is also a security dimension specific to SVG: it is an executable document format. An SVG
carrying a `<script>` element or a remote reference in a PUBLIC repository is an attack surface.

## Options considered

1. **High-fidelity mockups in a design tool, exported as images.** Rejected: highest
   mistaken-for-real risk, and the canonical artifact would live outside the repository.
2. **No visual artifacts; prose specifications only.** Rejected: layout intent genuinely does not
   survive prose alone.
3. **Low-fidelity SVG wireframes, committed, constrained and labelled.**
4. Logo: **generate a placeholder logo mark** — rejected, because a placeholder mark is
   indistinguishable from a real one once it is in a file. **Use a text wordmark** — adopted.

## Decision

**Option 3 for wireframes, and a text wordmark for the brand, are adopted.**

Wireframes are **low-fidelity SVG**, committed to the repository so the canonical artifact is
version-controlled, diffable, and subject to the same review as every other file. Every wireframe:

- is valid XML with a `viewBox`;
- carries its screen ID and its platform as visible text;
- carries the literal label **`LOW-FIDELITY — NOT IMPLEMENTED`**;
- contains **no** `<script>`, no inline event handler, no `<foreignObject>`, no `<iframe>`, no XML
  entity declaration, no `javascript:` URL, no remote reference and no embedded binary;
- contains no personal data — every example datum is fictional.

Additionally, every wireframe's screen ID must resolve to a screen in `SCREEN_INVENTORY.md`, so an
orphan wireframe depicting a screen nobody specified cannot survive.

**Logo status: `NOT APPROVED`.** The design usage is the **text wordmark "Aish Laundry App"** as an
explicit placeholder. No logo mark is generated, and **no artifact may be described as the final or
official logo**. `color.semantic.accent.strong` is the single gold permitted in the wordmark.

## Consequences

Layout intent is communicated in a form that is version-controlled, mechanically checkable, and
structurally unable to masquerade as a finished product or a finished brand.

### Positive consequences

- A wireframe cannot be mistaken for an implemented screen: the label is mandatory and enforced.
- SVG is diffable and reviewable, unlike a binary image.
- The SVG injection and remote-content surfaces are closed by a validator rather than by convention.
- The owner retains an unprejudiced brand decision; nothing has been chosen on their behalf.

### Negative consequences / trade-offs

- Low fidelity communicates less about visual quality, and a stakeholder may under-read the design
  intent as a result.
- Hand-authored SVG is slower to produce and revise than a design tool.
- A text wordmark makes the wireframes look unfinished — which is accurate, and is the point, but it
  is still a cost.
- Wireframes and the screen inventory can drift; the orphan check mitigates this but does not
  eliminate it.

## Verification

`python3 scripts/validate-wireframes.py` enforces minimum counts per platform, valid XML, the
`viewBox`, the mandatory label, the screen ID, the full forbidden-construct list, the absence of
real-format phone numbers, and that no wireframe is an orphan.

`python3 scripts/validate-design-required-files.py` fails on any claim of a final or official logo.

Adversarial mutations 19, 20, 26 and 28 prove that an embedded script, a remote reference, an
"IMPLEMENTED" relabel and a final-logo claim are each caught.

At the time of writing there are 32 wireframes across the four surfaces, all passing. **They are
`NOT IMPLEMENTED` and are not final UI.**

## Requirement references

Every UI-DIRECT requirement whose screen carries a wireframe; see `docs/quality/STEP_02_TRACEABILITY.md` and `docs/ux/wireframes/README.md`.

## Threat references

DUX-028 (SVG injection), DUX-029 (remote embedded content), DUX-030 (malicious links), DUX-027 (public repository exposure).

## Rule references

Rule 23 (public repository safety), Rule 25 (design system foundation), Rule 34 (component and screen governance).

## Supersession policy

Superseded in two independent parts. Higher-fidelity design artifacts require a decision record
naming where they live, how they are versioned, and how they remain distinguishable from
implemented screens. A final logo requires explicit owner approval recorded in its own decision
record; until such a record exists, `LOGO STATUS: NOT APPROVED` stands and the text wordmark remains
the only permitted usage.

## Related Master Source sections

§18.1 (visual language), §18.4 (foundation delivery), §15.8 (public repository safety), §24 (roadmap).
