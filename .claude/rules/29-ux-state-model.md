# Rule 29 — UX State Model

## Purpose

Most of what goes wrong in an operational app happens in the states nobody designed: empty, loading, stale,
offline, partially synced, failed, denied. An interface that only renders the happy path is an interface
that lies whenever reality departs from it — and in a product that takes cash on a patchy connection, that
is often. This rule fixes the state model. Delivered in Step 2.

## The canonical UX states

Every data-bearing surface accounts for all of these:

`EMPTY` · `LOADING` · `LOADED` · `STALE` · `OFFLINE` · `PENDING_SYNC` · `SYNC_FAILED` · `PARTIAL` ·
`ERROR` · `DENIED`

A surface that does not state what it renders in each applicable state is incomplete.

## Hard rules

1. **Offline and sync states must be honest.** A queued operation is never rendered as a committed one.
   Every record carries its true state: `TERSIMPAN DI PERANGKAT`, `MENUNGGU SINKRONISASI`, `TERSINKRON`, or
   `GAGAL — PERLU TINDAKAN`. Optimistic rendering is permitted; **unlabelled** optimistic rendering is not.
2. **Connectivity and sync state are visible at all times in the Ops app.** What is pending, what failed,
   and what needs attention is always reachable and never buried (Rule 07).
3. **A failed operation is never silently dropped.** It stays visible and actionable until a human resolves
   it.
4. **Payment success is never claimed from client state.** The word "berhasil", the success colour, and the
   success icon are reserved for a **server-confirmed** payment. A payment that has left the device but has
   not been confirmed is rendered as pending, in neutral styling, and the order remains visibly unpaid
   (Rule 04, Rule 32).
5. **Retry states name the idempotency guarantee.** Where a retry reuses the original `client_reference`,
   the interface says so, so an operator does not create a second payment by pressing the button again
   (Rule 07, Rule 20).
6. **A payment conflict is surfaced, never auto-resolved.** When local and server state disagree about
   money, the interface presents the conflict for human resolution and picks no winner (Rule 07).
7. **Stale content declares its age.** Any view that may render cached content shows when it was last
   synchronised, in outlet local time. Financial figures and order status carry the freshness marker
   adjacent to the value, not in a page footer.
8. **Critical screens must have error and recovery states.** Every screen that takes money, transfers
   custody, changes access, or changes order status specifies its error state **and** the recovery action
   the user takes from it. An error state with no recovery path is an incomplete specification.
9. **Errors explain recovery in Bahasa Indonesia.** What happened, and what to do next. An error code alone
   is never acceptable (Rule 30).
10. **Empty states are designed, not left blank.** An empty state states what would appear here, why it is
    empty, and the next action if one exists.
11. **Every component declares a state contract** — the states it can be in, what triggers each transition,
    and what it renders in each. A component without a state contract is incomplete (Rule 34).
12. **A `DENIED` state never confirms the existence of another tenant's record.** Denial and absence are
    indistinguishable across a tenant boundary (Rule 02, Rule 32).
13. **Loading is not a substitute for a decision.** A surface that spins indefinitely instead of resolving
    to `ERROR` or `EMPTY` has no state model; it has a placeholder.
14. **Status text comes from the canonical status vocabulary and the domain glossary**, never from an
    improvised synonym (Rule 19, Rule 30).

## Step 2 note

**No state is implemented.** There is no queue, no sync engine, no network layer, and no runtime. The
Flutter workspace is `ABSENT` and the backend runtime is `ABSENT`. Step 2 defines the state model as
**documentation only**. A documented state is not an enforced state; enforcement and its tests begin at
**Step 5** and later (Rule 13).

## Violation handling

- **A queued operation rendered as committed** — reject; this is the offline design's defining failure mode
  and is how duplicate orders and payments reach production (Rule 07).
- **A payment rendered as successful before server confirmation** — treat as a financial-integrity design
  defect (Rule 04); the automatic `NO-GO` conditions apply once such a design ships.
- **A payment conflict resolved silently in the interface** — reject; money conflicts are human decisions.
- **Sync or connectivity state hidden from the Ops user** — reject.
- **A critical screen with no specified error and recovery state** — the Step is not done.
- **Cached content rendered with no freshness indicator** — reject the specification.
- **A component with no state contract** — the component is incomplete (Rule 34).
- **A `DENIED` state that distinguishes "exists in another tenant" from "does not exist"** — treat as a
  cross-tenant disclosure path and escalate (Rule 02).
- **An error message consisting only of a code** — reject (Rule 30).
