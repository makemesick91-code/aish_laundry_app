# Module — Identity

Bounded context: **Identity and Access** (Rule 17).

## Boundary

Establishes **who the actor is**. Identity answers authentication questions only. It never answers
an authorization question and never answers a tenancy question.

## In scope

- User accounts (`users`).
- Credentials and password hashing — modern, salted, deliberately slow (Rule 03, hard rule 5).
- Password reset tokens (`password_reset_tokens`).
- API tokens (`personal_access_tokens`, via Laravel Sanctum) — stored **hashed**, never in
  plaintext (Rule 03, hard rule 6).
- Sessions and device sessions (`device_sessions`), including **session revocation** and **device
  revocation**: revoking one device must not force every other device to re-authenticate
  (Rule 03, hard rules 8 and 9).

## Out of scope

- **Membership, tenant resolution, and tenant scoping** — those belong to `Tenancy`. A user account
  by itself grants access to nothing.
- **Roles and permissions** — those belong to `Authorization`. Authenticating is not authorizing.
- Customer profiles. A customer is a tenant-scoped business record owned by a later Step's context,
  and is emphatically **not** a `users` row.

## Non-negotiables

1. **A user account alone is never authorization.** Authorization derives from Membership
   (Rule 02).
2. **One user may join multiple tenants.** Identity is deliberately tenant-agnostic; `users` carries
   no `tenant_id`.
3. **Records are never merged across tenants because name, email, phone, or device match**
   (Rule 02, hard rule 11).
4. **No credential, OTP, or token is ever logged** — not at debug level, not temporarily
   (Rule 03, hard rule 20).

## Status

`NOT IMPLEMENTED` — directory boundary only. No authentication exists.
