# Rule 03 — Security and Privacy

## Purpose

Aish Laundry App holds customer phone numbers, home addresses, order histories, photographs of
personal belongings, and tenant revenue data. That combination is sensitive. This rule fixes the
security and privacy baseline for every step of the roadmap.

## Access control

1. **Least privilege** everywhere: roles, tokens, workflow permissions, storage policies, database
   grants.
2. **Server-side authorization** on every protected operation. Client-side hiding of a button is a
   UX affordance, never an access control.
3. **Tenant-scoped access** on all business data (see Rule 02).
4. Permission checks are enforced at the API boundary, not only in the UI layer.

## Credentials, tokens, and sessions

5. **Secure password hashing** using a modern, salted, deliberately slow algorithm. No MD5, no SHA-1,
   no unsalted digests, no home-grown schemes.
6. **Secure token storage** server-side: tokens are stored hashed, never in plaintext.
7. **Android secure storage** for credentials and tokens on device — platform keystore-backed
   storage, never plain shared preferences or plain files.
8. **Session revocation** must be supported: a session can be terminated server-side and stops
   working immediately.
9. **Device revocation** must be supported: a specific device's access can be revoked without
   forcing every other device to re-authenticate.

## Secrets

10. **No secrets in the repository** — not in code, config, documentation, examples, test fixtures,
    evidence files, commit messages, or issue text. This repository is **PUBLIC** (AMENDMENT-0001),
    which makes any committed secret immediately compromised.
11. A leaked secret is rotated first and discussed second.

## Input, uploads, and files

12. **Uploads are validated**: type, size, and content are checked server-side. Client-declared
    content types are untrusted.
13. **Private files are served via signed, expiring URLs.** Object storage is never publicly listable
    or publicly readable for tenant data.
14. **Laundry photographs are private data.** They may show the inside of a customer's home or
    personal garments. They are never public, never indexed, never attached to a public link.

## Abuse resistance

15. **Rate limiting** on authentication, OTP issuance, tracking-token lookup, and other abusable
    endpoints.
16. **Brute-force protection** with progressive backoff and lockout on repeated failures.
17. High-entropy tracking tokens, stored hashed, revocable and expiring (see Rule 09 and the public
    tracking portal rules in the Master Source).

## Support access

18. **Support impersonation is time-bound and audited.** Every impersonation session records who,
    which tenant, when, for how long, and why.
19. **Platform support has no silent tenant access.** There is no invisible back door into tenant
    data.

## Logging and data handling

20. **Logs never contain passwords, OTPs, tokens, or credentials.** Not at debug level, not
    temporarily, not in a local branch.
21. **Phone numbers and addresses are masked per context.** The masking level depends on who is
    looking and where.
22. **The public tracking portal never shows a full address.**
23. **Backups are encrypted**, and restoration is verified rather than assumed.
24. **Tenant data is not used to train AI without explicit consent** from the tenant.

## Privacy posture

- Collect the minimum personal data needed to run a laundry operation.
- Exports carry the same access rules as the underlying records.
- Personal data appearing in error reports, analytics, or crash traces must be redacted at source.

## Step 0 note

None of the above is implemented yet. Security is delivered progressively across Steps 3, 7, 8, 12
and hardened in Step 13. Step 0 records the baseline so that no later step can quietly fall below it.

## Violation handling

- **Secret committed** — treat as compromised. Rotate the credential immediately, notify the owner,
  then remove it from the repository. Removal without rotation is not a fix.
- **Credential, OTP, or token found in logs** — stop and fix the logging path before further work;
  purge the affected log data where possible.
- **Personal data exposed publicly** (including via the tracking portal or an unsigned file URL) —
  automatic **NO-GO**; revoke the exposure path immediately and notify the owner.
- **Authorization enforced only client-side** — the change is rejected until server-side enforcement
  exists.
- **Silent or unaudited support access to tenant data** — automatic **NO-GO**.
- Never weaken a security control to unblock a deadline. Escalate to the owner instead.
