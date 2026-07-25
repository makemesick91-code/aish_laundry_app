# DEC-0041 — Laravel Blade is the Canonical Public Tracking Portal Stack (OQ-014 Ratification)

**ID:** DEC-0041
**Title:** Laravel Blade is the Canonical Public Tracking Portal Stack (OQ-014 Ratification)
**Status:** ACCEPTED
**Date:** 26 July 2026

---

## Context

Master Source §5.4 and [DEC-0004](DEC-0004-flutter-client-and-web-console.md) lock Flutter for the three
application surfaces but deliberately do **not** mandate it for the fourth: the Portal Tracking Publik may
use a lighter web stack where that performs materially better on a low-end Android browser.
[DEC-0006](DEC-0006-public-tracking-without-app-installation.md) and
[DEC-0014](DEC-0014-customer-android-does-not-replace-public-tracking.md) fix that the portal requires no
app installation and is not replaced by the Customer Android app.

Which stack was left as [OQ-014](../product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md), to be recorded in a
decision record by the Step that builds the portal. Step 7 built it: server-rendered Laravel Blade at
`/lacak/{token}`, three views, one web route, no script on the page, no new dependency, no new toolchain,
and no third-party asset. That is the lightest option available and it is reversible, but Step 7
deliberately did **not** declare it ratified — an agent does not accept a product decision on the owner's
behalf (Rule 12, Rule 00 hard rule 6).

This is the most exposed and most performance-critical surface in the product (Rule 21 hard rule 10,
Rule 31 hard rule 10): opened once, on an unknown device, over an unknown network, by a customer who chose
to install nothing.

The repository owner has now decided OQ-014.

## Options considered

- **Server-rendered Laravel Blade, first-party assets only.** Adopted. Blade ships with the Laravel
  runtime Step 3 already established, so the portal adds zero dependencies, zero build steps, and zero
  bytes of framework JavaScript. A `default-src 'none'` CSP is achievable because the page genuinely needs
  no script.
- **Flutter Web.** Rejected for this surface. It would ship a substantial engine payload to a
  single-purpose page that renders under thirty fields, on exactly the low-end hardware §5.4 anticipates,
  and would make the no-script CSP impossible.
- **A separate JavaScript SPA (React/Vue/Svelte).** Rejected. It introduces a second toolchain, a build
  pipeline, a dependency tree to audit on a public repository, and a client-side render for content the
  server already has — for no capability the page needs.
- **A static generator or edge-rendered page.** Rejected. The projection is per-token, per-request, and
  revocable; caching it anywhere is precisely the behaviour `Cache-Control: no-store` exists to prevent.

## Decision

1. **Laravel Blade is the canonical Step 7 public tracking portal stack**, serving `/lacak/{token}` from
   `backend/resources/views/tracking/`. This RATIFIES the existing implementation; it changes no
   behaviour and introduces no new capability. OQ-014 is thereby resolved.

2. **Blade is used for the public tracking portal only.** It must not become a parallel administrative or
   operational application. Console Web and Admin Web remain Flutter Web (DEC-0004); Ops and Customer
   Android remain Flutter. A second Blade surface — an operator page, an admin page, a login page, a
   dashboard — is outside this record and requires its own decision.

3. **Business rules stay in canonical backend services and domain code, and Blade views never duplicate
   them.** Token validation, tenant isolation, the customer-visible status projection, masking, consent,
   and every notification rule live in `App\Modules\Tracking\**` and `App\Modules\Notification\**`. A view
   renders an already-decided projection; it never re-derives a status, re-applies a mask, or re-decides
   what a customer may see. The allow-list projection remains an allow-list: a field absent from it is
   never assembled, so it cannot leak through a template (Rule 32 hard rule 7).

4. **No persistent browser storage of the tracking token.** The portal sets no cookie carrying the token,
   and writes nothing to `localStorage` or `sessionStorage` — it has no script with which to do so. The
   token exists in the URL for the life of the request and nowhere else (Rule 32 hard rule 10).

5. **No unnecessary public authentication session.** The portal establishes no session for a public
   visitor. Possession of a live, unexpired, unrevoked token is the whole of the access decision;
   verification of an FR-091 sensitive action is a per-action OTP challenge, not a login.

6. **The security controls already implemented are mandatory and are not weakened by this
   ratification** — applied centrally in `PublicTrackingHeaders` so no handler can forget one:
   `Cache-Control: no-store`; `X-Robots-Tag: noindex` plus an in-page `<meta name="robots">` so an
   intermediary stripping the header cannot cause indexing; `Referrer-Policy: no-referrer`, which is
   load-bearing because the token is in the URL path; a `default-src 'none'` Content-Security-Policy that
   makes "no remote asset, no analytics, no pixel, no embed" structural rather than conventional;
   `frame-ancestors 'none'` with `X-Frame-Options: DENY`; `X-Content-Type-Options: nosniff`; Blade's
   contextual escaping on every rendered value, with no unescaped output of any customer-supplied or
   tenant-supplied string; rate limiting on token lookup and on OTP issuance; and one generic
   invalid-link response that never distinguishes expired, revoked, wrong-tenant, and never-existed.

7. **Accessibility and responsive behaviour remain required**, to the Step 2 foundation the portal was
   specified against: text-plus-icon status rendering with no colour-only meaning, a 48×48 dp minimum
   target, legibility at large system font scaling, and a layout that works at the smallest supported
   viewport. The permitted wording remains **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
   RUNTIME-TESTED**; ratifying the stack asserts no audit result (Rule 27, Rule 41, Rule 01).

8. **No Step 8 or Step 9 capability may be added to this surface.** No pickup or delivery scheduling
   control, no courier surface, no proof capture, and no H+1/H+3/H+7/H+14 reminder control appears on the
   portal. A verified FR-091 action records an **accepted request** and does not act on Step 8 workflow,
   exactly as DEC-0039 §5 bounds it.

9. **This record adds no dependency.** Blade is part of the Laravel runtime already authorised by
   DEC-0024 and Rule 06; nothing is installed, and the toolchain pins are unchanged (Rule 37).

## Consequences

OQ-014 is closed. The portal's stack is a ratified product decision with written boundaries, rather than
an implementation choice flagged as provisional.

### Positive consequences

- The most exposed surface in the product carries the smallest possible attack surface: no script, no
  third-party origin, no build artefact, and a CSP that can state `default-src 'none'` truthfully.
- Nothing is added to the dependency tree of a PUBLIC repository, so the portal contributes no
  supply-chain surface to audit.
- Business rules cannot drift into a second implementation, because the boundary is written down here and
  the views are rendering-only by rule rather than by habit.
- DEC-0006 and DEC-0014 are preserved intact: tracking still requires no installation, and the Customer
  Android app still does not replace the portal.

### Negative consequences / trade-offs

- The product now has two rendering technologies — Flutter for the three application surfaces, Blade for
  the portal. That is a real, permanent cost in reviewer attention and in shared-component reuse, and it
  is accepted because the alternative is shipping an application framework to a single-purpose public
  page on a low-end phone.
- Blade sits inside `backend/`, so a contributor adding a view is one file away from a surface that must
  never grow into an admin application. Boundary 2 of this record, and the Step 8+ structural guard, are
  what hold that line; neither is self-enforcing against a determined mistake.
- Richer client-side interaction on the portal (live status polling, an inline map) is not available
  without revisiting this record. That is the intended constraint, not an oversight.

## Verification

Verified on `feature/step-07-runtime-customer-tracking-whatsapp`, bound to the exact commit SHA recorded
in the Step 7 evidence pack (Rule 01, DEC-0013). This record quotes no result it did not produce.

- `backend/routes/web.php` declares exactly one web route, `GET lacak/{token}`, behind
  `public.tracking.headers`.
- `backend/resources/views/tracking/` contains exactly three views — `layout`, `show`, `unavailable` —
  and no other web surface exists.
- `backend/tests/Feature/Tracking/PublicTrackingApiTest.php` and `PublicProjectionTest.php` assert the
  transport contract (`no-store`, `noindex`, `no-referrer`, CSP, `DENY`), the allow-list projection, and
  the single generic invalid-link body for expired, revoked, and unknown tokens alike.
- `scripts/validate-dec-0041-portal-stack.py` audits the boundaries structurally: the view directory
  stays confined to tracking, no Blade view carries a script or a remote origin, no view emits unescaped
  output, no browser-storage or session API appears, and no Step 8/Step 9 control label appears on the
  surface. It is exercised against deliberately broken input by
  `scripts/test-step-07-validators.sh`.

## Requirement references

FR-089, FR-090, FR-091, FR-092 (public tracking portal, projection, OTP gate, transport contract),
TRK-010, TRK-029, SEC-042 … SEC-045, NFR accessibility and responsive requirements carried from Step 2.
**No requirement is created, changed, or withdrawn.** This record fixes the stack §5.4 deliberately left
to the delivering Step, and resolves OQ-014.

## Rule references

- Rule 05 — Flutter is not mandatory for the public tracking portal; a lighter stack is permitted.
- Rule 06 — backend architecture; the portal consumes the same canonical services, not a private back
  channel.
- Rule 28 hard rule 10 — public tracking never requires an app installation.
- Rule 31 hard rule 10 — the portal is the lightest surface, self-contained, no remote asset.
- Rule 32 hard rules 7–10 — the allow-list projection, the `SECRET` token, and no persistent token
  storage.
- Rule 27 / Rule 41 — accessibility is designed-for, not audited; the bounded wording is unchanged.

## Supersession policy

This record supersedes nothing. It resolves OQ-014 and ratifies the stack DEC-0004 and Master Source §5.4
deliberately left open for this surface. It does not amend DEC-0004, DEC-0006, or DEC-0014, all of which
remain `ACCEPTED` and binding.

Adopting a different portal stack, extending Blade to any surface other than the public tracking portal,
or relaxing any transport control in decision item 6 requires a **new** accepted decision record naming
this one.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §5 — platforms; §5.4 the public tracking portal.
- §6 — architecture.
- §9 — public tracking portal.
- §15 — security.
- §17 — privacy and masking.
- §18 — UX and design foundation.
- §24 — Roadmap; Step 7.
- §31 — Decision records.
- §32 — Changelog.
