# DEC-0022 — Canonical UX State Taxonomy and Role-Adaptive Navigation

**ID:** DEC-0022
**Title:** Canonical UX State Taxonomy and Role-Adaptive Navigation
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

Two failure modes dominate multi-tenant operational software, and both are invisible until they cost
money.

The first is the **silent state**. An operation is queued, or failed, or in conflict, and the
interface shows nothing distinguishable. A cashier at a counter cannot tell whether the server
accepted a payment. Rule 07 requires that offline and sync state be visible at all times; that is
impossible unless the states themselves are enumerated.

The second is **navigation mistaken for authorization**. A menu item is hidden for a role, and the
hiding is treated as the access control. It is not. Hiding is a usability affordance; authorization
is a server decision (Rule 03).

## Options considered

1. **Ad-hoc states per screen.** Rejected: it produces the silent state by construction, because no
   screen is obliged to distinguish anything.
2. **A small state set (loading / empty / error).** Rejected: it collapses `Pending Sync`,
   `Syncing`, `Failed Sync` and `Conflict` into one "error", which is precisely the collapse that
   loses a payment.
3. **A canonical enumerated taxonomy, every state carrying a mandatory recovery path.**
4. Navigation: **role-derived menus treated as authorization** — rejected outright as a security
   defect. **Role-adaptive navigation with authorization explicitly server-side** — adopted.

## Decision

**Option 3 for states, and server-authoritative role-adaptive navigation, are adopted.**

**Twenty canonical UX states** are enumerated in `docs/ux/UX_STATE_MODEL.md`: Loading, Empty, Error,
Offline, Pending Sync, Syncing, Synced, Failed Sync, Conflict, Permission Denied, Session Expired,
Device Revoked, Tenant Unavailable, Outlet Inactive, Subscription Limited, Provider Degraded, Rate
Limited, Maintenance, Partial Data, Stale Data.

**Every state carries a mandatory recovery path.** A state with no way out is a dead end a user hits
at a counter with a customer waiting, and `validate-ux-states.py` fails if any state lacks one.

The nine Ops Android sync states — Saved Locally, Waiting to Sync, Syncing, Synced, Sync Failed,
Conflict, Server Rejected, Retry Scheduled, Manual Attention Required — are distinguished
separately. **There is no silent sync failure.** A payment is never presented as final before the
server has acknowledged it.

**Navigation is role-adaptive across all fourteen roles, and visibility is never authorization.**
Every IA document states this, and server-side authorization is delivered in Step 3. The External
Courier never receives tenant-wide navigation. Tenant and outlet context is always visible on
operational screens; a tenant switch is explicit, never silent, warns on unsynced critical
operations, and leaves no readable cached data behind.

## Consequences

Every state a user can reach is named, has a message, and has a way out. Every role's navigation is
specified without that specification being mistaken for a security control.

### Positive consequences

- The silent-failure class of defect is designed out rather than discovered in production.
- `Conflict` is a first-class state resolved by a human, never a silent overwrite.
- The visibility-is-not-authorization rule is written where a developer will read it, and checked.
- Tenant context ambiguity — the precondition for a cross-tenant mistake — is structurally prevented.

### Negative consequences / trade-offs

- Twenty states is a large surface; every screen must decide which apply, and the state matrix is
  correspondingly large to maintain.
- Distinguishing nine sync states costs interface space on small handsets and demands careful copy
  so the distinctions read as useful rather than noisy.
- Role-adaptive navigation multiplies the specification by fourteen roles, and each new destination
  must be resolved against all of them.

## Verification

`python3 scripts/validate-ux-states.py` enforces all twenty states, the full per-state contract, and
the mandatory recovery path.
`python3 scripts/validate-privacy-ux.py` enforces the nine sync states, the no-silent-failure rule,
the `client_reference` idempotency key, and the rule that payment is never final from client state.
`python3 scripts/validate-navigation.py` enforces all fourteen roles, the visibility-is-not-
authorization statement, the external-courier confinement, and the non-silent tenant switch.

Adversarial mutations 11, 12 and 15 prove the recovery requirement, the non-silent tenant switch and
the payment-acknowledgement rule are genuinely enforced.

**No state is implemented. There is no runtime.**

## Requirement references

OFF-001 … OFF-025, TEN-001 … TEN-030, SEC-001 … SEC-068 in so far as each has an interface consequence; see `docs/quality/STEP_02_TRACEABILITY.md`.

## Threat references

DUX-001 (hidden tenant context), DUX-002 (ambiguous outlet context), DUX-010 (false payment success), DUX-014 (offline false assurance), DUX-015 (stale-data confusion).

## Rule references

Rule 28 (platform-adaptive navigation), Rule 29 (UX state model), Rule 32 (security and privacy UX).

## Supersession policy

Adding, removing or renaming a canonical UX state requires a new decision record; states are not
adjusted to fit an implementation. The same applies to the role set and to the nine sync states.

## Related Master Source sections

§13 (offline-first), §13.2 (sync visibility), §4 (multi-tenancy), §7 (roles), §15 (security), §18.2 (canonical UX rules).
