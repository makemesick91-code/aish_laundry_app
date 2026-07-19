# Rule 22 — Acceptance Criteria and Traceability

## Purpose

To guarantee that every requirement can be verified and every verification traces back to a requirement,
so that "done" is a checkable state rather than an opinion. Delivered in Step 1, enforced at every Step
that builds something.

Canonical artefacts: `docs/quality/ACCEPTANCE_CRITERIA.md`,
`docs/security/SECURITY_ACCEPTANCE_CRITERIA.md`, `docs/product/REQUIREMENT_TRACEABILITY.md`.

## Acceptance criteria rules

1. **Every MUST requirement has at least one acceptance criterion.** A MUST with no criterion is
   unverifiable and blocks the Definition of Done.
2. **Every acceptance criterion cites at least one requirement ID.** An orphan criterion tests something
   nobody asked for, or tests something whose requirement was deleted.
3. **Criteria are testable and unambiguous.** "The system is secure" is not a criterion. "A member of
   tenant A receives 404 when requesting an order belonging to tenant B by direct ID" is.
4. **Critical scenarios use Given / When / Then.**
5. **Every criterion names its bounded context and the roadmap Step that will satisfy it.**
6. **Both the happy path and the negative path are covered.** A criterion that only proves the feature
   works when used correctly proves very little.
7. **Where relevant, a criterion explicitly names tenant boundary, financial integrity, and offline
   behaviour.** These three are where silent failure is most expensive.
8. **A criterion never assumes state it did not establish**, and never depends on the order in which
   other criteria ran.

## Mandatory scenario coverage

The criteria set covers at minimum: an owner with multiple tenants; a customer phone number reused in
different tenants; cross-tenant order access denied; immutable price snapshot; partial payment; payment
replay; duplicate offline order; the full order lifecycle; quality-control rework; tracking token expiry;
tracking token revocation; external courier guest access; proof of delivery; failed delivery;
H+1 / H+3 / H+7; opt-out honoured; overdue laundry escalation; provider notification failure;
subscription entitlement; and portfolio dashboard authorisation.

## Traceability rules

9. **The traceability matrix is bidirectional.** Requirement → acceptance criterion → bounded context →
   roadmap Step, and back again.
10. **No orphans in either direction.** A requirement with no criterion, or a criterion with no
    requirement, is a traceability defect that blocks the Step.
11. **Every `CRITICAL` and `HIGH` threat traces to a mitigation and to at least one acceptance
    criterion** (Rule 21).
12. **Every domain invariant traces to at least one acceptance criterion** (Rule 18). An unenforced
    invariant is a wish.
13. **Changing a requirement requires reviewing its criteria in the same pull request.** Criteria that
    silently outlive the requirement they described are worse than no criteria, because they create false
    confidence.
14. **The matrix is regenerated or re-verified whenever requirements, criteria, threats, or invariants
    change** — not once per Step, but per change.

## Honesty rules

15. **An acceptance criterion is not evidence that it passed.** Writing a criterion proves intent; only
    captured output at an exact SHA proves a result (Rule 01, DEC-0013).
16. **Never mark a criterion satisfied without evidence.** At Step 1 every criterion is unmet by
    definition, because nothing is implemented.
17. **A criterion is never weakened to make it pass.** If reality does not meet the criterion, either the
    implementation is wrong or the requirement was wrong — and changing the requirement is an owner
    decision (Rule 16).

## Step 1 note

Step 1 defines requirements, criteria, and the traceability matrix as **documentation only**. **No
criterion has been executed**, because there is no application. There are no unit, widget, integration,
or end-to-end tests, and none may be claimed. Application CI remains `NOT APPLICABLE`.

## Violation handling

- **A MUST requirement with no acceptance criterion** — the Step is not done.
- **An acceptance criterion citing no requirement** — resolve it: either add the missing requirement
  properly, or delete the criterion.
- **A `CRITICAL` or `HIGH` threat with no criterion** — the Step is not done (Rule 21).
- **A domain invariant with no criterion** — the Step is not done (Rule 18).
- **A criterion marked satisfied without exact-SHA evidence** — the claim is void; re-run and recapture,
  or withdraw it (Rule 01).
- **A criterion edited to match a failing implementation** — treat as evidence tampering in spirit;
  revert it and escalate to the owner.
- **A requirement changed without reviewing its criteria** — the change is incomplete; the pull request
  is not ready.
