# Rule 27 — Accessibility Foundation

## Purpose

Accessibility retrofitted is accessibility done twice and done badly. Contrast, target size, font scaling,
focus order, and assistive-technology semantics are properties of the token layer and the component
contract — they cannot be added to a finished screen without rebuilding it. This rule fixes them in the
foundation. Delivered in Step 2, enforced at every Step that builds a surface.

## Accessibility is a hard gate

1. **Accessibility is a hard gate.** A component or screen that does not meet the accessibility contract is
   not done, regardless of feature completeness or schedule. It is not a backlog item, not a polish task,
   and not a later-phase concern.
2. The target is **WCAG 2.2 AA**. Every accessibility statement in this repository uses the exact wording:
   **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**
   No shorter form, no softening, no claim of conformance.

## Hard rules

3. **Status never depends on colour alone.** Every status — order, payment, sync, offline, quality control,
   impersonation, permission — is carried by a **text label** and an **icon or shape** in addition to
   colour. Colour is reinforcement. A user with colour vision deficiency, or a cheap screen in direct
   sunlight, must read the same state as everyone else.
4. **Statuses adjacent in a workflow are distinguishable by shape as well as by hue.** Two statuses that
   differ only in hue will be confused.
5. **Minimum touch target is 48×48 dp**, including confirmation buttons, destructive actions, the
   impersonation exit control, and any control a courier uses outdoors one-handed. A visually smaller
   control still carries a 48×48 dp hit area.
6. **Contrast meets WCAG 2.2 AA** for text and for non-text UI components, and is specified at the semantic
   token pair rather than per screen (Rule 26).
7. **Layouts survive large system font scaling** without truncating a status, an amount, a warning, or any
   other critical information. Reflow is the expectation; horizontal truncation of critical content is a
   defect.
8. **Every screen defines a focus order**, and every component states where focus moves on open, on close,
   on error, and on asynchronous completion. Dialogues trap focus and return it to the invoking control on
   dismissal. Validation errors move focus to the first invalid field and announce the error text. Route
   changes move focus to the new page heading.
9. **Every component carries an accessibility contract** stating role, accessible name, state,
   announcement behaviour, focus behaviour, and target size. A component without this contract is
   incomplete (Rule 34).
10. **Icon-only controls carry a text alternative naming the action and its object** — "Batalkan pesanan
    ALS-2026-000042", never "Batal" alone, and never nothing.
11. **Decorative imagery is marked decorative.** Meaningful alternative text on decoration is noise that
    degrades the experience it was meant to improve.
12. **Live regions announce security- and money-relevant changes**: sync state, payment state, session
    expiry, impersonation start and end.
13. **Errors explain recovery.** An error code alone is never an acceptable message. What happened, and
    what the user should do next, in Bahasa Indonesia (Rule 30).
14. **Accessibility applies to the courier surface with no exemption.** "Simple" is not a reason to drop
    labels, targets, or contrast; the courier works outdoors, in a hurry, on a cheap phone, which makes the
    requirement stronger rather than weaker.

## Step 2 note

**No accessibility conformance has been tested, because there is no runtime.** The Flutter workspace is
`ABSENT`; there is no screen to audit, no screen reader to run against it, and no contrast checker with a
rendered surface to measure.

**An accessibility requirement is not a passed audit.** Step 2 records the requirements. Runtime
accessibility verification arrives with the Steps that build the surfaces, and is hardened in **Step 13**.
Any claim of a completed accessibility audit in Step 2 is a false claim under Rule 01.

## Violation handling

- **A status conveyed by colour alone** — Definition of Done failure for the Step that introduced it, not a
  cosmetic backlog item.
- **A touch target below 48×48 dp** — reject the component.
- **A token pairing below the AA contrast threshold** — treat as an accessibility defect and fix the token
  pairing, not the individual screen (Rule 26).
- **A layout that truncates critical information at large font sizes** — reject.
- **A component with no accessibility contract** — the component is incomplete; the Step is not done
  (Rule 34).
- **An icon-only control with no text alternative** — reject.
- **Focus lost on dialogue open, dialogue close, route change, or validation error** — reject.
- **An error message consisting only of a code** — reject (Rule 30).
- **Any claim that Step 2 delivered an accessibility audit, a conformance result, or a tested screen** —
  correct it immediately and visibly, and state that the earlier claim was wrong (Rule 01). The permitted
  wording is **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**
