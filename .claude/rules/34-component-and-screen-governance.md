# Rule 34 — Component and Screen Governance

## Purpose

A component library grows by accretion unless something stops it. Two buttons become five, three of which
behave differently under error, and the design system stops being a system. This rule fixes what a component
and a screen must declare before either is accepted. Delivered in Step 2, enforced at every Step that builds
a surface.

## What a component must declare

Every component in the inventory documents **all** of the following. A component missing any of them is
incomplete and is not accepted.

1. **Purpose** — the single job it does, and the job it deliberately does not do.
2. **State contract** — every state it can be in, what triggers each transition, and what it renders in
   each. Applicable states are drawn from the canonical UX state set: `EMPTY`, `LOADING`, `LOADED`,
   `STALE`, `OFFLINE`, `PENDING_SYNC`, `SYNC_FAILED`, `PARTIAL`, `ERROR`, `DENIED` (Rule 29).
3. **Accessibility contract** — role, accessible name, state announcement, focus behaviour on open, close,
   error, and asynchronous completion, and target size. Minimum touch target is 48×48 dp (Rule 27).
4. **Token usage** — the semantic tokens it consumes. A component references semantic tokens only and never
   a raw value (Rule 26).
5. **Content contract** — the copy it carries, in Bahasa Indonesia, using glossary terms (Rule 30).
6. **Requirement references** — at least one requirement ID (Rule 33).
7. **Delivering Step** — the roadmap Step that will build it.

## What a screen must declare

Every screen specification documents **all** of the following:

8. **Requirement references** — at least one requirement ID. A screen with none does not exist as product
   scope (Rule 33).
9. **Tenant behaviour** — the tenant scope it operates in, how tenant context is displayed, and what happens
   when the tenant context changes while the screen is open.
10. **Permission behaviour** — which roles may reach it, what a user without the permission sees, and
    whether the entry control is hidden or explained. A control the user may not use is not rendered; a
    visible control denied for a state reason is explained (Rule 28).
11. **Error and recovery states.** **Critical screens must have error and recovery states** — every screen
    that takes money, transfers custody, changes access, or changes order status specifies its error state
    **and** the recovery action available from it. An error state with no recovery path is incomplete
    (Rule 29).
12. **Offline and stale behaviour** — what it renders when data is cached, queued, or unsynchronised
    (Rule 29).
13. **Sensitive data handling** — which fields are masked, at what level, for which role, and what unmasking
    requires (Rule 32).
14. **Delivering Step.**

## Hard rules

15. **One component, one purpose.** A second component with the same purpose as an existing one is a review
    rejection. Extend the existing component or record why a genuinely new one is required (Rule 25).
16. **The inventory is the single definition.** A component that exists in a wireframe but not in the
    inventory does not exist.
17. **Component and screen identifiers are permanent.** A withdrawn artefact keeps its identifier and gains
    a withdrawal note (Rule 33).
18. **A new screen requires its tenant and permission behaviour before it is accepted**, not afterwards.
    Retro-fitting them is acceptable only before the Step closes, and never after code depends on the
    screen.
19. **Status rendering is centralised.** Order, payment, sync, and quality-control status render through the
    designated status component so that a change to status presentation cannot diverge across surfaces. No
    screen renders a status by improvising a chip (Rule 27, Rule 30).
20. **Destructive and financial controls are governed centrally.** Their placement, separation,
    confirmation strength, and reason-capture behaviour are properties of the component, not decisions
    re-made per screen (Rule 32).
21. **Wireframes must never be claimed implemented.** A wireframe, mockup, flow diagram, or component
    specification describes an obligation. Presenting one as a working screen, a shipped component, or a
    tested surface is a false claim under Rule 01.
22. **A component or screen never introduces a product fact.** If it implies a capability the PRD does not
    carry, the artefact is wrong (Rule 16, Rule 33).
23. **Changing a component's state contract or accessibility contract requires reviewing every screen that
    uses it**, in the same pull request.

## Step 2 note

**No component and no screen is implemented.** There is no Flutter workspace, no widget, no component
library, no storybook, no rendered surface, and no test. The Flutter workspace is `ABSENT`; the backend
runtime is `ABSENT`; application CI is `NOT APPLICABLE`.

Step 2 produces the component inventory and screen specifications as **documentation only**. **A wireframe
is not a screen.** Component implementation begins at **Step 3** and later, and no component may be
described as built, tested, or shipped before then.

## Violation handling

- **A component with no state contract or no accessibility contract** — the component is incomplete; the
  Step is not done.
- **A component referencing a raw value instead of a semantic token** — reject (Rule 26).
- **A screen specification with no requirement reference, no tenant behaviour, or no permission behaviour**
  — reject the specification.
- **A critical screen with no error and recovery state** — the Step is not done (Rule 29).
- **A duplicate component introduced alongside an existing one** — reject; extend the existing one or record
  the justification.
- **A status rendered by an improvised chip rather than the designated status component** — reject; this is
  how colour-only status and terminology drift re-enter the product (Rule 27, Rule 30).
- **A destructive or financial control re-specified per screen instead of governed centrally** — reject
  (Rule 32).
- **A wireframe, mockup, or specification presented as an implemented screen, component, or tested
  surface** — correct the claim immediately and visibly, and state that the earlier claim was wrong
  (Rule 01).
- **A component or screen implying a capability absent from the PRD** — remove the implication or add the
  requirement properly (Rule 16).
- **A contract changed without reviewing its consumers** — the pull request is incomplete.
