# Rule 19 — State Machines

## Purpose

Order status is the spine of the whole product: production reads it, tracking displays it, aging is
anchored to it, notifications fire from it, and reporting counts it. An arbitrary status write corrupts
all of them at once. This rule makes every lifecycle explicit. Delivered in Step 1, enforced from Step 3.

Canonical lifecycles live in `docs/state-machines/`.

## The canonical order statuses

Exactly these fifteen, in this spelling:

`DRAFT` · `RECEIVED` · `AWAITING_PROCESS` · `SORTING` · `WASHING` · `DRYING` · `FINISHING` ·
`QUALITY_CONTROL` · `REWORK` · `READY_FOR_PICKUP` · `SCHEDULED_FOR_DELIVERY` · `OUT_FOR_DELIVERY` ·
`COMPLETED` · `CANCELLED` · `ISSUE`

## The canonical pickup and delivery statuses

Exactly these eleven:

`REQUESTED` · `CONFIRMED` · `SCHEDULED` · `ASSIGNED` · `EN_ROUTE` · `ARRIVED` · `PICKED_UP` ·
`DELIVERED` · `FAILED` · `RESCHEDULED` · `CANCELLED`

## The canonical quality control statuses

Exactly these four:

`PENDING` · `PASSED` · `FAILED_REWORK_REQUIRED` · `WAIVED_WITH_AUTHORIZATION`

A waiver requires an explicit permission, a recorded reason, and an audit entry. A silent waiver is a
defect.

## Hard rules

1. **Adding, removing, renaming, or reordering a canonical status requires a decision record.** Statuses
   are not adjusted to fit an implementation.
2. **Every transition is explicitly enumerated.** If a transition is not in the documented machine, it is
   forbidden. There is no arbitrary status update and no generic "set status" operation exposed to
   clients.
3. **Every transition documents**: precondition, the actor role permitted to perform it, the domain
   events it emits, the timestamps it records, its reason-code requirement, and its corrective path.
4. **Transitions are server-side and authorised.** A client requests a transition; the server decides
   whether it is legal and whether that actor may perform it (Rule 03).
5. **A rejected transition changes nothing.** It fails closed, atomically.
6. **Terminal states are explicit.** `COMPLETED` and `CANCELLED` are terminal; leaving a terminal state
   requires a documented corrective path with permission, reason, and audit — never a casual edit.
7. **`ISSUE` is a real state, not an error screen.** It records a problem with a reason and an owner, and
   it has documented exits.
8. **A failed delivery is a first-class outcome**, not an exception. The laundry returns to the outlet
   and the order returns to a defined status with a recorded reason (Master Source §10.2).
9. **Rework never restarts the aging clock.** An order returning to `REWORK` and reaching
   `READY_FOR_PICKUP` a second time keeps its original first-ready timestamp (Rule 10, Rule 18).
10. **Status changes and payment state are independent.** Delivery completion does not by itself mean
    the order is paid; being paid does not by itself advance production.
11. **Messaging never gates a transition.** A WhatsApp or provider failure never blocks, reverses, or
    cancels a status change (Rule 08).
12. **Offline transitions carry a `ClientReference`** and replay idempotently. A replayed transition that
    already applied is a no-op, not a second application (Rule 07).
13. **Conflicting transitions surface rather than resolve silently.** When client and server disagree,
    the server is the source of truth and the divergence is made visible (Rule 07).

## Documentation requirements

14. Every state machine document contains **both** a Mermaid diagram **and** a written transition table.
    The diagram is an aid; the table is the specification. A diagram alone is not a specification.
15. Every fenced code block is properly closed. An unclosed fence is a validator failure.
16. Forbidden transitions are listed explicitly, not merely implied by omission — the reader must be able
    to see what was deliberately excluded.

## Step 1 note

No state machine is implemented. There is no code that transitions anything, because there is no runtime.
Step 1 records the lifecycles only. Enforcement and transition tests begin at **Step 5** and **Step 6**.

## Violation handling

- **A status value outside the canonical sets** — reject the change; it breaks tracking, aging,
  reporting, and notification simultaneously.
- **A transition performed that the machine does not enumerate** — treat as a correctness defect; add the
  transition to the machine deliberately, or remove the code path. Never do the reverse and widen the
  machine to match a bug.
- **A generic client-controlled "set status" endpoint** — reject outright.
- **A transition without server-side authorisation** — security defect (Rule 03).
- **The aging clock restarted by a return to `READY_FOR_PICKUP`** — defect; the first-ready timestamp is
  immutable (Rule 10).
- **An order cancelled, blocked, or failed because a notification failed** — reject the design (Rule 08).
- **A quality-control waiver without permission, reason, and audit** — reject.
- **A state machine documented as a diagram only, with no transition table** — the Step is not done.
