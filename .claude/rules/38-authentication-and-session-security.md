# Rule 38 — Authentication and Session Security

## Purpose

Step 3 is where authentication moves from a documented requirement (Rule 03) to an actual login flow,
an actual token, and an actual session that a real device holds. Every credential-handling mistake made
here is inherited by every step built on top of it. This rule fixes the runtime authentication and
session baseline.

## Hard rules

1. **Passwords are never stored or transmitted in plaintext**, anywhere in the system — not in the
   database, not in a log, not in a queued job payload, not in a support ticket. Hashing uses a modern,
   salted, deliberately slow algorithm (Rule 03, hard rule 5).
2. **A bearer or session token is never stored in browser `localStorage` or `sessionStorage`** on
   Console Web or Admin Web. Script-accessible storage is readable by any script that runs on the page,
   including an injected one; token storage uses an `HttpOnly`, `Secure` cookie or an equivalent
   mechanism that ordinary page script cannot read.
3. **Android credential and token storage uses the platform secure-storage abstraction** — keystore-
   backed storage — never plain `SharedPreferences`, never a plain file, and never a value baked into
   application code (Rule 03, hard rule 7; Rule 07, hard rule 8).
4. **Any browser flow that relies on cookie-based authentication carries CSRF protection.** A
   state-changing request authenticated only by an ambient cookie, with no CSRF token or equivalent
   same-site defence, is rejected as incomplete.
5. **CORS configuration never combines a wildcard origin (`*`) with credentialed requests.** A
   credentialed cross-origin request is served only to an explicitly allow-listed origin; combining a
   wildcard with `Access-Control-Allow-Credentials: true` is a browser-enforced contradiction and a
   server-side defect if attempted.
6. **Authentication, OTP issuance, and password-reset endpoints carry rate limiting with progressive
   backoff and lockout** on repeated failure (Rule 03, hard rule 15; Rule 03, hard rule 16).
7. **Authentication and account-recovery failure responses never disclose whether an identifier
   exists.** "Invalid credentials" reads identically whether the phone number or email is registered or
   not; a distinguishable response is a user-enumeration channel and is rejected on discovery.

## Supporting expectations

- Session revocation and device revocation are both supported and take effect immediately, without
  requiring every other device to re-authenticate (Rule 03, hard rules 8–9).
- Tokens are stored server-side hashed, never in plaintext (Rule 03, hard rule 6).
- Step-up or re-authentication on session expiry never silently discards a queued offline operation
  (Rule 29, Rule 32).
- Login, OTP, and password-reset flows are tenant-aware where relevant, but authentication identity
  itself is never assumed to imply tenant membership (Rule 39).

## Step 3 note

**No authentication implementation exists to test yet.** This rule records the runtime requirement so
that the first login flow built inherits the correct baseline rather than retrofitting it after a
credential-handling defect ships. Enforcement and its negative tests are part of Step 3's Definition of
Done (Rule 47) and are not satisfied by this document alone.

## Violation handling

- **A password found stored or logged in plaintext** — treat as a critical security defect; rotate any
  exposed credential and fix the storage path before anything else proceeds (Rule 03).
- **A bearer token found in browser `localStorage`** — reject the implementation; move it to an
  `HttpOnly` cookie or an equivalent non-script-readable mechanism.
- **Android credentials found in plain `SharedPreferences` or a plain file** — reject; move to the
  platform secure-storage abstraction before the Step closes.
- **A state-changing cookie-authenticated endpoint with no CSRF protection** — reject outright.
- **A credentialed wildcard CORS configuration** — security defect; fix before any client consumes the
  endpoint.
- **An authentication endpoint with no rate limiting, or a distinguishable enumeration response** —
  reject; these are abuse-resistance failures, not polish items (Rule 03, hard rules 15–16).
