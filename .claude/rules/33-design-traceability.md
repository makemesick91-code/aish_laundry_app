# Rule 33 — Design Traceability

## Purpose

To guarantee that every design artefact traces back to a requirement and forward to a verification, so that
"the design is done" is a checkable state rather than an opinion. A screen nobody can trace is a feature
nobody asked for. Delivered in Step 2, enforced at every Step that builds a surface.

## Hard rules

### Identity

1. **Requirement IDs are mandatory.** Every design artefact that expresses product behaviour cites at least
   one requirement ID from `docs/product/PRODUCT_REQUIREMENTS.md`, using the canonical prefixes: `FR-`,
   `NFR-`, `SEC-`, `TEN-`, `FIN-`, `OFF-`, `TRK-`, `DEL-`, `UCL-`, `NOT-`, `SUB-`, `RPT-` (Rule 16).
2. **All screens must carry requirement references.** A screen specification, wireframe, or flow with no
   requirement ID does not exist as product scope and is removed or given a requirement before the Step
   closes.
3. **All requirements must carry a UX classification**, recording how the requirement reaches a user:
   which surface, which role, which screen or component family, and whether it is user-facing, background,
   or purely server-side. A requirement with no UX classification cannot be checked for interface coverage.
4. **Identifiers are permanent and never reused.** A withdrawn screen, component, or design finding keeps
   its identifier and gains a withdrawal note. Reusing an identifier silently rewrites every artefact that
   cited it (Rule 16).
5. **A design artefact never introduces a requirement.** If the design needs a behaviour the PRD does not
   carry, the requirement is added to the PRD first — never asserted in a wireframe annotation and left
   there (Rule 16).

### Bidirectional traceability

6. **Traceability is bidirectional**: requirement → UX classification → screen or component → acceptance
   criterion → roadmap Step, and back again.
7. **No orphans in either direction.** A user-facing requirement with no screen, or a screen with no
   requirement, is a traceability defect that blocks the Step (Rule 22).
8. **Every design finding rated `CRITICAL` or `HIGH` traces to a concrete UX mitigation and to at least one
   requirement ID.** A `CRITICAL` or `HIGH` finding with no mitigation blocks the Definition of Done
   (Rule 21).
9. **Every domain invariant that a user can observe or violate through an interface traces to a screen
   behaviour**, so that the interface is not the path around the invariant (Rule 18).
10. **Design changes must update traceability.** Adding, renaming, splitting, merging, or removing a screen
    or component requires the traceability record to be updated **in the same pull request**. Traceability
    updated later is traceability that was wrong in between.
11. **Changing a requirement requires reviewing its screens and criteria in the same pull request.** A
    screen that silently outlives the requirement it served creates false confidence (Rule 22).
12. **The matrix is re-verified per change, not once per Step.**

### Review obligations

13. **Graphify relationship review is mandatory.** Before Step 2 closes, a relationship review is run over
    the design documentation set, checking that every screen, component, requirement, threat, and rule
    reference resolves and that no orphan exists in either direction. Its output is captured at the exact
    commit SHA.
14. **Diagram and visual tooling renders only what is already approved.** Graphify — or any diagram tool —
    may visualise documentation that already exists in the Master Source and the derived documents. It must
    **never** introduce a new product fact, screen, flow, or relationship. A diagram is a rendering, not a
    source (Rule 14 of `CLAUDE.md` §14).
15. **Adversarial validator testing is mandatory.** Every Step 2 validator is tested against deliberately
    broken input — a missing requirement reference, an orphan screen, a colour-only status, an unclosed code
    fence, a broken internal link — and is shown to **fail** on each. A validator that has only ever been
    run against correct input is an untested validator, and reporting it as a passing gate overstates the
    assurance it provides.
16. **Internal markdown links must resolve** to files that exist. A broken internal link in governance or
    design documentation is a validator failure, not a cosmetic issue (Rule 00).

### Honesty

17. **A traceability entry is not evidence of verification.** Writing that a screen satisfies a requirement
    proves intent. Only captured output at an exact commit SHA proves a result (Rule 01, DEC-0013).
18. **No design artefact is marked satisfied, complete, or verified without exact-SHA evidence.** At Step 2
    every screen is unbuilt by definition and every acceptance criterion is unexecuted.
19. **A criterion is never weakened to make a design pass.** If the design does not meet the criterion,
    either the design is wrong or the requirement was wrong — and changing a requirement is an owner
    decision (Rule 16, Rule 22).

## Step 2 note

**Nothing traced here is implemented.** All product features are `NOT IMPLEMENTED`; the backend runtime is
`ABSENT`; the Flutter workspace is `ABSENT`; application CI is `NOT APPLICABLE`. Step 2 produces the design
traceability record as **documentation only**. **A wireframe is not a screen**, and a traceability row is not
a test result.

## Violation handling

- **A screen or component with no requirement reference** — resolve before the Step closes: add the
  requirement to the PRD properly, or remove the artefact.
- **A requirement with no UX classification** — the classification set is incomplete; the Step is not done.
- **A user-facing requirement with no screen, or a screen with no requirement** — traceability defect;
  blocks the Step (Rule 22).
- **A `CRITICAL` or `HIGH` design finding with no UX mitigation** — the Step is not done (Rule 21).
- **A design change merged without updating traceability** — the change is incomplete; the pull request is
  not ready.
- **An identifier reused for a different screen, component, or finding** — reject the change; every citation
  has silently changed meaning.
- **A requirement asserted in a wireframe, diagram, or annotation but absent from the PRD** — the PRD wins;
  add it properly or delete the assertion (Rule 16).
- **A diagram introducing a product fact, screen, or flow not present in the approved documentation** —
  remove it and report the invention (Rule 01).
- **A validator never tested against broken input** — the gate is unverified; test it adversarially before
  relying on it, and do not report it as assurance it has not demonstrated.
- **A broken internal markdown link** — validator failure; fix it.
- **A design artefact marked verified without exact-SHA evidence** — the claim is void; re-run and
  recapture, or withdraw it (Rule 01).
