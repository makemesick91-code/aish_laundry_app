# Rule 31 — Responsive and Device Foundation

## Purpose

The product's real operating environment is a cheap Android phone at a laundry counter, a courier's phone
outdoors on a motorbike, and a browser on an office machine. A design validated only on a high-end
simulator will fail in all three. This rule fixes the device and viewport assumptions. Delivered in Step 2.

## The assumed environment

| Dimension | Assumption |
| --- | --- |
| Primary device | Low-end to mid-range Android, small screen, limited memory |
| Network | Patchy mobile data, dead zones, interrupted transactions |
| Lighting | Bright shop lighting and direct outdoor sunlight |
| Posture | One-handed, in a hurry, frequently interrupted |
| Console | Pointer and keyboard, seated, larger viewport |
| Public portal | Any browser, no installation, unknown device |

These are design constraints, not edge cases. A component that only works outside them is not finished.

## Hard rules

1. **Layouts are specified from the smallest supported viewport upward**, never designed wide and shrunk.
   The narrow case is the common case.
2. **Layouts survive large system font scaling** without truncating a status, an amount, a warning, or any
   other critical information. Reflow is expected; truncation of critical content is a defect (Rule 27).
3. **Minimum touch target is 48×48 dp on every surface and every role**, including the courier surface,
   confirmation dialogues, and destructive controls. A visually smaller control still carries a 48×48 dp
   hit area (Rule 27).
4. **Contrast is specified for bright-light legibility**, not for a dark room. The stated environment
   includes direct sunlight (Rule 26, Rule 27).
5. **Motion is restrained and never load-bearing.** Heavy animation is avoided; motion serves comprehension.
   No state, status, or confirmation is communicated by animation alone, and every animation degrades
   gracefully when reduced-motion is requested.
6. **Assets are budgeted for low-end hardware.** Image weight, icon count, and render complexity are stated
   constraints on a component, not incidental outcomes.
7. **No design assumes a persistent network.** Every surface that reads or writes data specifies its
   offline and stale behaviour (Rule 29).
8. **No design assumes a recent device, a large screen, a fast processor, or a stable orientation.** Where
   a capability is genuinely required, its absence has a defined fallback.
9. **Console Web adapts to a larger viewport by adding structure, not by adding density** to the point of
   illegibility. Financial figures and status indicators keep their target size and contrast at every
   viewport.
10. **The public tracking portal is the lightest surface in the product.** It loads on an unknown device on
    a poor connection with no installation. It is self-contained — first-party fonts, icons, and styles —
    and carries no remote asset, analytics script, marketing pixel, session recorder, or third-party embed
    (Rule 32).
11. **Print and export renderings are specified where they exist.** A receipt, an invoice, and an exported
    report are surfaces with their own legibility and masking constraints, and they inherit the same
    classification-driven masking rules (Rule 32).
12. **Device characteristics are never used as an authorisation signal.** A device identifier is an
    untrusted hint, exactly as a client-supplied tenant identifier is (Rule 02, Rule 03).

## Step 2 note

**No layout is implemented.** There is no Flutter workspace, no widget, no breakpoint constant, and no
rendered surface — the Flutter workspace is `ABSENT` and the backend runtime is `ABSENT`. Step 2 defines
responsive and device constraints as **documentation only**. No layout has been rendered on any device, and
no performance figure has been measured. Device and performance verification arrives with the Steps that
build the surfaces and is hardened in **Step 13**.

## Violation handling

- **A layout designed wide and shrunk** — reject; re-specify from the smallest supported viewport.
- **Critical information truncated at large font sizes** — reject (Rule 27).
- **A touch target below 48×48 dp on any surface, including the courier surface** — reject.
- **A state, status, or confirmation communicated by animation alone** — reject.
- **A surface with no specified offline or stale behaviour** — the specification is incomplete (Rule 29).
- **A remote asset, analytics script, or third-party embed on the public tracking portal** — treat as a
  privacy defect and remove it; on a token-bearing surface it is a `SECRET`-class disclosure path
  (Rule 32).
- **A design that assumes a persistent network, a recent device, or a large screen** — reject and
  re-specify with a defined fallback.
- **A device characteristic used as an authorisation signal** — security defect of the highest severity,
  not a code-style comment (Rule 03).
- **Any claim that Step 2 measured performance, rendering, or device behaviour** — remove it; nothing has
  been rendered and nothing has been measured (Rule 01).
