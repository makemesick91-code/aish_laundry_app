# Module — Audit

Bounded context: **Audit and Compliance** (Rule 17).

## Boundary

Owns the **append-only record of what happened**. Audit is written by every other module and
rewritten by none.

## In scope

- Audit entries (`audit_entries`), recording actor, tenant, outlet where relevant, action,
  subject, timestamp, and reason.
- The write interface other modules call. Audit exposes a way to *append* and a way to *read*; it
  exposes no way to edit or delete.

## Out of scope

- Application logging and telemetry. A log is diagnostic; an audit entry is a record.
- Business reporting — Step 10.

## Non-negotiables

1. **Append-only.** An audit entry is never edited and never hard-deleted. A correction is a new
   entry, exactly as a financial correction is a reversal rather than a rewrite (Rule 04, hard
   rule 8).
2. **Tenant-scoped.** `audit_entries` carries `tenant_id` and is read only within its tenant. A
   platform-administration read path is distinct and is itself audited (Rule 02).
3. **No secret is ever written into an audit entry** — no password, OTP, token, credential, or
   private key (Rule 03, hard rule 20; Rule 21, hard rule 18).
4. **Support impersonation is time-bound and audited**, recording who, which tenant, when, for how
   long, and why. **Platform support has no silent tenant access** (Rule 03, hard rules 18 and 19).
   Silent or unaudited platform access to tenant data is an automatic NO-GO (Rule 12).
5. **An audit record is classified `CONFIDENTIAL` or `RESTRICTED`** depending on its contents
   (Rule 21).

## Status

`NOT IMPLEMENTED` — directory boundary only. No audit trail is written by anything, because
nothing writes yet.
