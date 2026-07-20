# Module — Authorization

Bounded context: **Identity and Access** — the permission half (Rule 17).

## Boundary

Answers **what the authenticated actor may do**, given a resolved tenant context. Authorization
begins where `Identity` ends and depends on `Tenancy` having already resolved the boundary.

## In scope

- Roles (`roles`) and permissions (`permissions`).
- The role-to-permission grant (`role_permission`).
- The membership-to-role assignment (`membership_role`) — a user's roles are a property of their
  **membership**, never of their user account.
- Server-side permission checks at the API boundary.

## Out of scope

- Authentication and session lifecycle — `Identity`.
- Tenant resolution and scoping — `Tenancy`.
- Any product feature's own business rules.

## Non-negotiables

1. **Authorization derives from Membership, never from the user account alone** (Rule 02).
2. **Permission checks are enforced at the API boundary, not only in the UI layer.** Client-side
   hiding of a control is a UX affordance, never an access control (Rule 03, hard rules 2 and 4).
3. **Least privilege everywhere** — roles, tokens, workflow permissions, storage policies
   (Rule 03, hard rule 1).
4. **Navigation is never an access control.** Hiding a route hides nothing from an HTTP client
   (Rule 28, hard rule 6).
5. **Platform administration is a distinct, audited path.** It is never implemented by relaxing
   tenant scoping for ordinary roles (Rule 02).

## Status

`NOT IMPLEMENTED` — directory boundary only. No RBAC enforcement exists.
