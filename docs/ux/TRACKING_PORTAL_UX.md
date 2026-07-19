# Public Tracking Portal UX

**Surface:** Portal Tracking Publik (browser, **no app installation required**)
**Roadmap step:** Step 7 — Customer Tracking and WhatsApp
**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Backend runtime:** ABSENT

> **Documentation is not implementation.** No portal, token issuance, or rate limiting exists.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. Design intent

A customer receives a WhatsApp message with a link. They tap it on a cheap Android phone, on mobile
data, possibly on a busy street. Within a second or two they must know: **is my laundry ready, and
what do I owe?**

This is the most exposed surface in the product and simultaneously the one that most needs to be
effortless. Those two facts pull in opposite directions, and every rule below is the resolution of
that tension.

**This portal is never degraded into "install the app first"** (`TRK-025`). The Customer Android app
does not replace it (DEC-0014). No login is required for safe information (`TRK-013`).

---

## 2. Light and fast

| Requirement | Detail |
|---|---|
| Payload | Text-first. No heavy framework required for the status page. Flutter is **not** mandatory here; a lighter web stack is permitted if it performs better |
| Images | None required to convey status. No image is needed to read whether the laundry is ready |
| Fonts | System fonts. No blocking web-font fetch on the critical path |
| First meaningful paint | The primary status renders before anything else |
| Motion | None decorative. Nothing animates the answer away from the reader |

---

## 3. Above the fold

On a 360 × 640 viewport, without scrolling, the customer sees:

1. **Primary status**, in words, large, with an icon — `SIAP DIAMBIL`, `SEDANG DICUCI`, `DALAM PENGANTARAN`.
2. The **order reference**, shown safely: `AL-2026-000123`.
3. The **masked customer name**: `Budi S.`
4. The **outlet**: `Outlet Cempaka`.

Below the fold, in this order: timeline, estimate, payment balance, pickup or delivery schedule,
contact button, privacy notice.

---

## 4. Content rules

### Status

Drawn from the canonical fifteen, presented in Bahasa Indonesia with an icon. **Never colour alone.**
Internal-only nuance is not exposed: a customer sees that their laundry is being redone, not the QC
reason code that caused it.

### Order number, shown safely

The order reference is displayed because the customer needs it to talk to the outlet. It is **not**
the access mechanism — the token is, and **the token is never the order number and is not derivable
from it**. Knowing an order number grants nothing.

### Customer name, masked

`Budi S.` — partially masked (`TRK-011`). Never the full name.

### Timeline

Statuses reached, with timestamps in **outlet local time**, 24-hour (`14:30`). Statuses not yet
reached are shown as pending, never as failures.

### Estimate

Labelled an estimate every time it appears: "Perkiraan siap: 20 Juli 2026". **Never a guarantee.**
Never a promised arrival time for a delivery.

### Payment balance, safely

Total, paid, and remaining balance in integer Rupiah — `Rp79.000`, `Rp25.000`. Enough for the
customer to bring the right money. **No** pricing structure, **no** discount rationale, **no** cost,
**no** margin.

If the page was served from cache, the balance section collapses to "belum dapat dimuat" rather than
showing a stale number a customer might act on.

### Pickup and delivery schedule

The scheduled window, described as a **preference or an estimate**, never as a guarantee. Pickup and
delivery statuses come from the canonical eleven.

### Contact outlet

A single, obvious button. It is the recovery path from **every** failure state on this surface.

---

## 5. What this surface must NEVER show

This list is absolute.

| Never | Why |
|---|---|
| A **full phone number** | `CONFIDENTIAL`; masked everywhere on this surface |
| A **full address** | `TRK-010`. `RESTRICTED`. Not partially, not on request, not behind an expander |
| An **internal note** | Written for staff, not for customers |
| A **margin or cost price** | Tenant financial internal |
| **Unnecessary employee data** | An outlet name, never a personal phone number |
| **Audit data** | Not a customer-facing surface |
| **Sensitive photographs** | Condition photographs, proof-of-delivery photographs, and signatures are `RESTRICTED`, served only via signed expiring URLs to authorised staff |
| **Any other order of the same customer** | `TRK-015` |
| **Any data from another tenant** | A token is scoped to one order in one tenant |
| **The token value in analytics** | Tokens are `SECRET`. Never in telemetry, logs, referrers, or any event payload |

### Token handling

1. High entropy; **only the hash is stored** server-side. The plaintext exists only in the link.
2. Revocable, with **immediate effect** (`TRK-004`). Revocation records actor, timestamp, and reason
   (`TRK-022`).
3. Re-issuing access revokes the prior token and records why (`TRK-023`).
4. Lookup is **rate-limited and enumeration-protected** (`TRK-007`).
5. Outbound links carry `noreferrer` so the token never leaves in a referrer header.
6. There is **no lookup form** on this surface. A search box would be an enumeration surface.

### `noindex`

**Every portal response carries `noindex, nofollow`.** A tokenised status page must never appear in a
search engine. This is a hard requirement, not a preference, and it applies to failure pages as much
as to the status page.

---

## 6. Failure states — each with a recovery path

| Screen | State | Copy intent | Recovery |
|---|---|---|---|
| `SCR-TRK-005` Expired Token | `UXS-003` variant | "Tautan ini sudah kedaluwarsa." | *Minta tautan baru* through the outlet; *Hubungi Outlet* |
| `SCR-TRK-006` Revoked Token | `UXS-003` variant | "Akses untuk tautan ini telah dicabut." No reason that could leak | *Hubungi Outlet* |
| `SCR-TRK-007` Invalid Token | `UXS-003` variant | Generic, and **identical in shape to an unknown token**, so existence is never confirmed | *Hubungi Outlet* |
| `SCR-TRK-008` Rate Limited | `UXS-017` | "Terlalu banyak percobaan. Coba lagi dalam 15 menit." | Wait, or *Hubungi Outlet* |
| Lookup failed | `UXS-003` | Names the failure, offers retry | Retry; *Hubungi Outlet* |
| Offline | `UXS-020` | Cached page with an explicit fetch time; balance suppressed | Reconnect and refresh |
| Maintenance | `UXS-018` | Window stated in outlet local time | Wait; *Hubungi Outlet* |

**Every failure state offers a human path.** A customer is never left on a page whose only content is
a refusal.

### OTP step-up — `SCR-TRK-009`

Required only for a **sensitive action**, never for reading safe information. The OTP is never echoed
back, never logged, never placed in analytics, and never sent alongside the tracking link in the same
message (`NOT-014`). Repeated failures reach `UXS-017 Rate Limited`, which offers the human path.

---

## 7. Privacy copy

A short, plain notice on the status page, in Bahasa Indonesia, stating:

- what this page shows and that it shows one order only;
- that the personal data on it is deliberately limited and masked;
- that the link is private and should not be shared publicly;
- that the outlet can revoke the link and issue a new one;
- how to contact the outlet.

Written for a customer, not for a lawyer. It is real text, not an image, and it is reachable by
screen reader.

---

## 8. Accessibility

- The primary status is a heading, in text, with an icon — **never colour alone**.
- Timeline entries are a real list with real timestamps, not a picture.
- Contrast is high enough to read outdoors in sunlight.
- The page works at large browser zoom and large system font sizes without losing the primary status.
- The contact button is a large, unambiguous target.
- The page is navigable and readable by screen reader in Bahasa Indonesia.
- No content requires hover, and no information is conveyed by position alone.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## 9. Responsive behaviour

| Breakpoint | Layout |
|---|---|
| compact `<600px` | **The design target.** Single column; primary status above the fold at 360 × 640 |
| medium `600–1023px` | Single centred column, width-capped |
| expanded `1024–1439px` | Timeline and payment summary side by side |
| wide `>=1440px` | As expanded; the page never stretches to full width |

---

## 10. Analytics intent

Records **shape, not content**: page reached, status class displayed, whether the contact button was
used, failure-state frequency, and time to first paint.

**Never** the token, the order reference, the customer name, the phone number, the address, or the
balance.

---

## 11. Related documents

- [`./information-architecture/TRACKING_PORTAL_IA.md`](./information-architecture/TRACKING_PORTAL_IA.md)
- [`./CUSTOMER_ANDROID_UX.md`](./CUSTOMER_ANDROID_UX.md)
- [`./UX_STATE_MODEL.md`](./UX_STATE_MODEL.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)

## 12. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Public tracking portal | **NOT IMPLEMENTED** |
| Token issuance, revocation, and rate limiting | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Accessibility runtime testing | **NOT STARTED** |

`GO` is conferred by the repository owner and is never self-declared.
