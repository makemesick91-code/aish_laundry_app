# Rule 28 — Platform-Adaptive Navigation

## Purpose

Aish Laundry App spans four surfaces with different input models, different session lengths, and different
users: Customer Android, Ops Android, Console Web, and the public tracking portal. One navigation model
imposed on all four produces an app that is wrong three times. Four unrelated models produce a product
nobody can learn. This rule fixes the adaptation. Delivered in Step 2.

## The four surfaces

| Surface | Primary input | Session shape | Navigation model |
| --- | --- | --- | --- |
| Aish Laundry Customer Android | Touch, one-handed | Short, occasional | Shallow, task-led |
| Aish Laundry Ops Android | Touch, one-handed, in a hurry | Long, interrupt-driven | Shortest-path, counter-first |
| Aish Laundry Console Web | Pointer and keyboard | Long, seated | Structured, keyboard-complete |
| Portal Tracking Publik | Touch, browser, no install | Single-purpose, seconds | Single view, no navigation chrome |

## Hard rules

1. **Tenant and outlet context must be visible.** The active tenant is rendered persistently in the primary
   chrome of every authenticated screen on Ops Android and Console Web. Outlet is rendered alongside it
   whenever the signed-in user has access to more than one outlet. Context is text — never a colour swatch
   alone, never an avatar alone, never only inside a collapsed menu, never only on a settings page.
2. **A screen that writes data restates the tenant inside the same visual block as its primary action.**
   Chrome-level context is not sufficient at the moment of commitment.
3. **A tenant switcher exists wherever a user can belong to more than one tenant.** Switching is deliberate,
   confirmed, clears the visible working set, and announces the new context to assistive technology
   (Rule 02, Rule 32).
4. **New screens must define tenant and permission behaviour.** Every screen specification states: which
   tenant scope it operates in, which roles may reach it, what a user without the permission sees, and what
   happens when the tenant context changes while the screen is open. A screen specification lacking any of
   these is incomplete and is not accepted.
5. **A control the user may not use is not rendered.** The interface never shows a control and then denies
   the action silently. Where a visible control is denied for a state reason rather than a permission
   reason, the denial is explained (Rule 29).
6. **Client-side navigation is never an access control.** Hiding a route is a user-experience affordance.
   Authorisation is server-side, on every request (Rule 03).
7. **Shortest possible primary actions.** The counter is busy. The common path is the fastest path, and
   navigation depth is a cost paid by every transaction of every day.
8. **Console Web is keyboard-complete.** Every action reachable by pointer is reachable by keyboard, in a
   defined focus order (Rule 27).
9. **The courier surface is deliberately minimal** — large targets, few steps, one-handed, usable outdoors.
   The external-courier guest surface has **no navigation chrome at all**: one assignment, no search, no
   history, no path to any other record (Rule 32).
10. **The public tracking portal requires no app installation, ever.** It is a differentiator locked by
    [`DEC-0006`](../../docs/decisions/DEC-0006-public-tracking-without-app-installation.md) and
    [`DEC-0014`](../../docs/decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md). A
    change that makes public tracking require an install is rejected. The Customer Android app does not
    replace the portal; both exist.
11. **Adaptation is per input model, not per brand whim.** A surface adapts because its input, session, and
    environment differ — not because a designer preferred a different pattern.
12. **Deep links resolve to an authorised, tenant-scoped destination or to a neutral not-found state.** A
    deep link never reveals whether a record exists in another tenant (Rule 32).

## Step 2 note

**No navigation is implemented.** There is no router, no screen, no widget, and no Flutter workspace — the
Flutter workspace is `ABSENT`. Step 2 defines navigation models and screen-level tenant and permission
behaviour as **documentation only**. A wireframe is not a screen.

## Violation handling

- **A screen where the active tenant is not visible** — treat as a tenant-isolation design defect
  (Rule 02), not a layout preference.
- **Outlet context omitted where a user has access to more than one outlet** — reject the screen
  specification.
- **A screen specification with no stated tenant behaviour or no stated permission behaviour** — the
  specification is incomplete; the Step is not done.
- **A control rendered and then silently denied** — reject; either explain the denial or do not render the
  control.
- **Navigation relied upon as an access control** — security defect (Rule 03); reject until server-side
  authorisation exists.
- **A change requiring an app install to use public tracking** — reject; it contradicts DEC-0006 and
  DEC-0014 and requires an owner decision record to even consider.
- **Navigation, search, or history added to the external-courier guest surface** — reject outright; it is
  a traversal path (Rule 32).
- **A deep link that discloses the existence of another tenant's record** — treat as a cross-tenant
  exposure path and escalate (Rule 02).
