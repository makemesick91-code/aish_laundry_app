# Rule 07 — Offline-First and Synchronization

## Purpose

The Ops Android app runs at a laundry counter and on a courier's motorbike in Indonesian conditions:
patchy mobile data, dead zones, cheap devices, and interruptions mid-transaction. The app must keep
working offline, and it must never let a connectivity problem turn into a money problem.

Scope: the **Ops Android** surface primarily. Implemented in later steps (Step 5 onward); recorded
here so that no later step ships a weaker guarantee.

## The 9 offline-first rules

1. **`client_reference` on every important operation.** The client generates a stable, unique
   reference before the operation is attempted, and reuses that same reference on every retry. The
   server uses it as the idempotency key.
2. **Persistent queue.** Pending operations survive app restart, device restart, and crash. An
   in-memory queue is not acceptable.
3. **Exponential backoff retry.** Retries back off progressively rather than hammering a struggling
   or unreachable server.
4. **The financial queue is never casually deleted.** Pending financial operations are not cleared by
   a routine "clear cache", a version upgrade, a logout, or a developer convenience button. Removing
   a queued financial operation requires an explicit, permissioned, audited action.
5. **Payment conflicts are never silently overwritten.** When local and server state disagree about a
   payment, the app surfaces the conflict for resolution. It never picks a winner quietly.
6. **The server is the final source of truth.** Local state is a working copy. On divergence, server
   state prevails, and the client reconciles to it.
7. **Local data is separated per tenant and per user.** Switching tenants or users must not expose
   the previous context's cached data (Rule 02).
8. **Sensitive local data is encrypted** on device, using platform secure storage for credentials and
   tokens (Rule 03).
9. **A duplicate order or duplicate payment produced by a retry is unacceptable.** This is the
   defining requirement of the sync design, not a best-effort goal.

## Design consequences

- Idempotency is a **server contract**, not a client trick: the server must recognise a repeated
  `client_reference` and return the original result rather than creating a second record.
- `client_reference` must be generated once and persisted with the queued operation. Regenerating it
  on retry defeats the entire mechanism.
- Queue ordering matters for dependent operations (create order → add payment). Dependencies are
  respected; an operation whose predecessor failed does not jump ahead.
- **Offline and sync state are visible to the user** at all times: what is pending, what failed, what
  needs attention (Rule 05).
- A failed operation is never silently dropped. It stays visible and actionable.
- Clock skew is expected. Server timestamps are authoritative for ordering and reporting.
- Conflicts affecting money escalate to a human; conflicts affecting non-financial metadata may use
  a documented last-write rule, but only if that rule is written down.

## Testing expectation (later steps)

The Definition of Done for offline functionality requires demonstrated tests for: retry after
network loss produces exactly one order and one payment; app kill mid-submit does not lose the queued
operation; replay after long offline periods reconciles correctly; tenant switch does not leak cached
data; and payment conflict surfaces rather than overwrites.

## Step 0 note

No client, no queue, and no sync implementation exists. **Flutter workspace: ABSENT.** Step 0 records
these rules only.

## Violation handling

- **A duplicate order or duplicate payment caused by retry** — automatic **NO-GO** under Rule 04.
  Stop, preserve evidence at the exact SHA, notify the owner, fix, and add a regression test.
- **A queued financial operation deleted without an audited, permissioned action** — treat as a
  financial integrity violation.
- **A payment conflict resolved silently** — reject the implementation; conflicts affecting money are
  human decisions.
- **Local cache surviving a tenant or user switch** — treat as a tenant isolation defect (Rule 02).
- **An operation retried with a fresh `client_reference`** — reject; this is the highest-risk bug
  class in the whole offline design.
