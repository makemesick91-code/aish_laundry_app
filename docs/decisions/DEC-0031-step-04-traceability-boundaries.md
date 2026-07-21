# DEC-0031 — Step 4 Traceability Boundaries

**ID:** DEC-0031
**Title:** Step 4 Traceability Boundaries
**Status:** ACCEPTED
**Date:** 21 July 2026

---

## Context

Two traceability questions surfaced while building the Step 4 requirement matrix. Both were recorded as
open rather than resolved by an agent, and both are answered here by the repository owner.

### Question A — staff and role assignment has no Step 4 FR identifier

[`ROADMAP.md`](../ROADMAP.md) lists **"Staff and role assignment within a tenant"** in the Step 4 scope
summary. The PRD assigns no identifier in the Step 4 range (FR-021 … FR-047) to it. Role scoping is
**FR-018**, assigned to Step 3, and Step 3 delivered it: `memberships`, `roles`, `permissions`,
`role_permission`, `membership_role`, a `PermissionRegistry` that is the single source of truth, and
brand/outlet-scoped policy checks.

Two wrong answers were available. Inventing an FR identifier would breach Rule 16 (a requirement lives
in the PRD or does not exist). Declaring the roadmap bullet already satisfied by Step 3 would quietly
drop work the roadmap asks for: Step 3 built the *authorization* machinery, but the outlet master data
that staff are assigned **to** does not exist until Step 4 creates it.

### Question B — seven requirements cannot be proven without an order

FR-029, FR-033, FR-036, FR-039, FR-044, FR-046 and FR-047 sit in the Step 4 range, but end-to-end proof
of each needs an order — and orders are FR-048+, Step 5. FR-036 is the clearest case: "an order shall
capture the price that applied when the order was created, and shall be immune to any later price-list
change" cannot be demonstrated without an order to capture a price.

Marking them satisfied because a price table exists would be a false claim under Rule 01. Marking them
failed would be equally wrong — Step 4 genuinely owns and can deliver the master data they configure.
Building a Step 5 order early to manufacture an end-to-end test would be scope leakage under Rule 36
and is forbidden by DEC-0030.

## Options considered

**For A — invent `FR-048` (or similar) for staff assignment.** Rejected. It breaks Rule 16 and permanently
renumbers the Step 5 range that FR-048 already occupies.

**For A — treat the roadmap bullet as fully delivered by Step 3's FR-018.** Rejected. It reads as a
completed obligation while the outlet-assignment surface does not exist, which is exactly the
"documentation is not implementation" failure Rule 01 forbids in reverse.

**For A — implement the master-data side, trace it to the roadmap scope plus FR-018, and name the Step 4
acceptance criteria explicitly.** **Adopted.**

**For B — mark all seven closed at Step 4.** Rejected: a false claim.

**For B — move all seven to Step 5.** Rejected. It would strip Step 4 of master-data obligations it can
and must meet, and the PRD assigns them to Step 4 because Step 4 owns the data they configure.

**For B — split each requirement into a Step 4 master-data responsibility and a Step 5 order-integration
responsibility, with a distinct partial status.** **Adopted.**

## Decision

### A — staff and role assignment

1. **No new FR identifier is created.** The work traces to the Step 4 roadmap scope line and to the
   existing **FR-018** authorization contract.
2. **Step 3's authorization foundation is not reopened, rewritten, or duplicated.** Step 4 introduces
   **no second RBAC system**: no new roles table, no new permission engine, no parallel role catalogue.
   It consumes `PermissionRegistry`, `Role`, `Permission`, `Membership` and `membership_role` as they
   already exist (Rule 40, DEC-0025).
3. **Step 4 implements the master-data side**: tenant membership management, staff-to-outlet assignment
   against the outlets Step 4 owns, activation, suspension, revocation, and role assignment performed
   through the existing catalogue.
4. **Step 4 tests must prove its assignment management respects FR-018's scope rules** — a role granted
   through a membership is scopable to a brand or outlet, and assignment cannot widen a caller's own
   scope.
5. **Traceability is recorded as `ROADMAP Step 4 scope + FR-018`** in the requirement matrix, never as a
   bare invented identifier.

### B — cross-step requirements

6. **A distinct status is introduced for exactly this case:**
   **`PARTIAL — STEP 4 FOUNDATION COMPLETE / STEP 5 E2E PENDING`.**
   It qualifies an approved base status and never replaces one (Rule 49 maintenance item 3). It is
   applied to a requirement **only after** the Step 4 side has passed; before that the requirement is
   `NOT STARTED`, and this status is never used to describe unbuilt work.

7. **Each of the seven splits into two named responsibilities:**

   | ID | Step 4 responsibility (must pass here) | Step 5 responsibility (deferred) |
   |---|---|---|
   | FR-029 | Tenant-scoped customer anchor; proof that no cross-tenant read path exists | Order-history surface |
   | FR-033 | Add-on catalogue entry, lifecycle, tenant scoping | Applying an add-on to an order line |
   | FR-036 | Immutable, addressable published price version the snapshot will reference | Proving a real order's captured price survives a price-list change |
   | FR-039 | Permission and mandatory-reason contract | The override flow on an order |
   | FR-044 | Shift definitions on outlet master data | Shift closing and cash-variance reconciliation |
   | FR-046 | Tenant proof-policy configuration | Proof capture at custody transfer (Step 8) |
   | FR-047 | Per-outlet quiet-hours configuration with the canonical 20.00–08.00 default | Quiet-hours enforcement in messaging (Step 7) |

8. **The split does not by itself block Step 4 `GO`**, provided all four hold:
   - the Master Source, PRD traceability, roadmap, requirement matrix, and evidence pack each document
     the split explicitly;
   - every Step 4-side acceptance criterion passes with captured exact-SHA evidence;
   - no functionality is claimed that does not exist;
   - the Step 5 obligations are carried as **mandatory** closure obligations for Step 5, not as
     optional follow-up.

9. **No Step 5 order workflow may be built to manufacture an end-to-end test.** DEC-0030 keeps `orders`,
   `payments`, `receipts` and the rest forbidden, and this record does not relax that by one token.

10. **This is a scope-boundary decision, not a reduction of Step 4's obligations.** Every master-data
    invariant, contract, reference, lifecycle rule, and persistence behaviour these seven requirements
    depend on is Step 4 work and must pass here.

## Consequences

Both open questions are closed without inventing a requirement, without reopening Step 3, and without
overstating what Step 4 delivers.

### Positive consequences

- The roadmap bullet is honoured by real work rather than absorbed into a prior step's `GO`.
- Step 4 cannot accidentally claim FR-036 because a price table exists; the split makes the remaining
  obligation visible in the same row as the delivered part.
- Step 5 inherits a written, enumerated list of what it must close, rather than a vague memory that
  something was deferred.
- The `PARTIAL — STEP 4 FOUNDATION COMPLETE / STEP 5 E2E PENDING` label is self-describing: a reader who
  has never seen this record still learns from the status alone that end-to-end proof is outstanding.

### Negative consequences / trade-offs

- A new compound status is added to a repository that deliberately keeps its status vocabulary small.
  Every additional label is a chance to blur a boundary, and this one is narrow precisely to limit that.
- Seven requirements will read as incomplete for as long as Step 5 takes, even though Step 4 has done
  everything it can. That is honest, but it makes the roadmap look less finished than it is.
- Tracing staff assignment to "roadmap scope + FR-018" rather than to a dedicated identifier means the
  requirement traceability matrix has one entry that is not a simple FR lookup. It is documented, but it
  is an exception to an otherwise uniform scheme.
- Deferring FR-036's end-to-end proof means the single most important financial invariant in the product
  — historical price immutability — is not fully demonstrated until Step 5. Step 4 proves the price
  version is immutable; it cannot prove an order honours it. That gap is real and is accepted, not
  hidden.

## Verification

Verified on `feature/step-04-laundry-master-data` at checkpoint SHA
`60d4593567cafde9225143c211018a11dd2b47b5`, with `origin/main` at
`1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d` and PR #18 open and mergeable:

- `docs/ROADMAP.md` Step 4 scope includes the staff-and-role-assignment line.
- `docs/product/PRODUCT_REQUIREMENTS.md` §15.2 assigns FR-018 to Step 3 and §15.3–§15.5 assign
  FR-021 … FR-047 to Step 4; no Step 4-range identifier covers staff assignment.
- `backend/app/Modules/Authorization/PermissionRegistry.php` is the existing single source of truth for
  roles and permissions, with `membership_role` as its assignment surface.
- The seven requirements' statements were read directly and each is confirmed to reference an order,
  a shift close, a proof capture, or a messaging send that Step 4 does not build.

Per-requirement pass/fail evidence for the Step 4 side is recorded in `evidence/step-04/`, bound to the
exact commit it was produced from (Rule 01, DEC-0013). This record quotes no test result.

## Requirement references

FR-018 (Step 3, consumed not modified); FR-029, FR-033, FR-036, FR-039, FR-044, FR-046, FR-047 (split);
FR-021 … FR-047 (the Step 4 set this record scopes). No requirement is created, changed, or withdrawn —
identifiers, statements, and priorities are untouched.

## Threat references

The governance risk is over-claiming: a partially delivered requirement reported as closed, which would
propagate into Step 5 as an assumption that historical price immutability or consent durability had
already been proven end to end. Mitigated by a status label that names the outstanding half, by
mandatory Step 5 closure obligations, and by the requirement matrix carrying both halves in one row.

A second risk is under-claiming — the split being used to quietly move Step 4 work into Step 5.
Mitigated by decision 10 and by the Step 4-side acceptance criteria having to pass before the partial
status may be applied at all.

## Rule references

- Rule 01 — status vocabulary; a partially delivered requirement is never reported as closed.
- Rule 16 — requirements live in the PRD; identifiers are permanent and never invented.
- Rule 22 — bidirectional traceability; no orphan requirement and no orphan criterion.
- Rule 36 / DEC-0030 — Step 5+ features stay forbidden; no order built to manufacture a test.
- Rule 40 / DEC-0025 — server-side authorization; no second RBAC system.
- Rule 49 / Rule 50 — compound qualifiers narrow an approved base status, never replace one.

## Supersession policy

Superseded only by a later accepted decision record naming this one. The seven Step 5 obligations are
discharged when Step 5 delivers and evidences them, at which point each requirement moves to its closed
status through the ordinary canonical process — not by editing this record. **Step 5 may not close
without discharging them or recording an owner-accepted deviation.** Applying the
`PARTIAL — STEP 4 FOUNDATION COMPLETE / STEP 5 E2E PENDING` label to any requirement outside the seven
listed in decision 7 requires its own record.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §16 — financial integrity, and FR-036's historical price immutability specifically.
- §24 — Roadmap; the Step 4 and Step 5 entries.
- §25 — Definition of Done.
- §31 — Decision records.
- §32 — Changelog.
