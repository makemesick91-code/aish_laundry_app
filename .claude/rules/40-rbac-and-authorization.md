# Rule 40 — Role-Based Access Control and Authorization

## Purpose

Membership answers "does this user belong to this tenant." Role and permission answer "what is this
user allowed to do once inside it." Step 3 is where both answers first become server-enforced code
rather than a documented intention. This rule fixes the runtime authorization baseline that every later
step's endpoints must satisfy.

## Hard rules

1. **A client-supplied role or permission claim is never authorization proof.** A role name or
   permission flag sent by the client — in a token payload the client controls, a request body, or a
   UI state — is never trusted on its own; the server re-derives the caller's actual roles and
   permissions from its own authoritative membership record on every request (Rule 03, hard rule 2).
2. **Every permission check is performed server-side, at the API boundary**, not only in a client's UI
   layer. Hiding a button is a user-experience affordance; it is never the access control itself (Rule
   03, hard rule 4; Rule 28, hard rule 6).
3. **Removing a role from a membership affects authorization immediately.** A permission granted by a
   now-removed role stops working on the very next request; it does not persist until the next login,
   the next token refresh, or the next cache expiry.

## Supporting expectations

- Roles and permissions are least-privilege by default: a new membership starts with the minimum access
  its function requires, not the maximum convenient to grant (Rule 03, hard rule 1).
- Permission checks are independent of tenant-membership checks (Rule 39) and both must pass; being an
  active member of a tenant is necessary but never sufficient on its own to perform a privileged action
  within it.
- A denied action is explained where the denial is for a state reason, and is simply not offered where
  the denial is for a permission reason — the interface never renders a control and then denies it
  silently (Rule 28, hard rule 5).
- Support impersonation, where it exists, is a distinct and audited authorization path — it is never
  implemented as a blanket role that happens to have every permission (Rule 03, hard rules 18–19; Rule
  32, hard rule 19).

## Step 3 note

**No RBAC runtime exists yet.** This rule fixes the requirement for the first role and permission
backend Step 3 builds. RBAC negative tests — a member without a permission proven unable to perform the
gated action, and a member whose role was just removed proven unable to continue performing it — are
part of Step 3's Definition of Done (Rule 47) and are not satisfied merely by this document.

## Violation handling

- **Authorization decided from a client-supplied role or permission value** — security defect of the
  highest severity; fix before the endpoint ships (Rule 03).
- **A permission enforced only in client UI, with no matching server-side check** — reject the change
  until server-side enforcement exists.
- **A removed role that continues to grant access** — defect; fix the authorization check to read live
  state and add a regression test.
- **A visible control that is silently denied on press** — reject the screen specification; either
  explain the denial or do not render the control (Rule 28, Rule 34).
- **A blanket "support" or "admin" role used in place of an audited impersonation path** — treat as
  silent platform access and an automatic `NO-GO` (Rule 03, Rule 12).
