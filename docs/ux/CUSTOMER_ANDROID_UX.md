# Customer Android UX

**Surface:** Aish Laundry Customer Android (Flutter)
**Roadmap step:** Step 11 — Customer Android Experience
**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Flutter workspace:** ABSENT

> **Documentation is not implementation.** No screen exists. Nothing below has been built or tested.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. Design intent

The customer app serves **P-12 Customer** — someone who dropped off cucian on the way to work and
wants to know when it will be ready and how much they owe. They are not a power user, they will open
the app perhaps twice per order, and they will judge it in four seconds.

Design bias: **answer the question before it is asked.**

The visual foundation is white, soft blue, dark blue, and a restrained gold accent. The tone is
professional and light, relevant to Indonesian UMKM — not futuristic. Motion serves comprehension;
there is no decorative animation.

---

## 2. The four-second rule

On opening Beranda, without scrolling and without tapping, the customer must be able to read:

1. **The status of their most recent active order**, in words, with an icon.
2. **When it is expected to be ready**, labelled as an estimate.
3. **What they owe**, in integer Rupiah — `Rp79.000` — or that nothing is owed.
4. **The single next action**, if there is one.

Everything else is below that.

---

## 3. Screen-by-screen intent

### Onboarding and authentication — `SCR-CUS-001`, `002`, `003`

- Onboarding is **one screen**, three sentences, one button. It explains what the app does, not what
  the company believes.
- Phone entry accepts the way Indonesians actually type numbers and normalises silently.
- The OTP screen states which number the code went to, **masked**: `0812-XXXX-1234`.
- Resend is available with a visible timer. Repeated attempts reach `UXS-017 Rate Limited`, which
  states when to retry and offers *Hubungi outlet* — there is always a human path.
- The app **never** asks for a password, and never asks the customer to install anything to track an
  order. Tracking in a browser remains available and is never degraded into "install the app first".

### Home — `SCR-CUS-004`

The next-action card, the active order summary, the unpaid balance, and a shortcut to *Ajukan
penjemputan*. Orders are grouped by **laundry brand**, each card carrying brand and outlet, because a
customer may use more than one laundry and those are separate businesses.

**No cross-tenant total appears anywhere.** There is no combined spend figure, no combined loyalty
balance, no combined order count.

### Active Orders and Order Detail — `SCR-CUS-005`, `006`

- Status is shown from the canonical set — `WASHING`, `READY_FOR_PICKUP`, `OUT_FOR_DELIVERY` — with
  Bahasa Indonesia labels and an icon. **Never colour alone.**
- Order detail shows the order reference `AL-2026-000123`, the outlet, the service lines with weight
  (`1,5 kg`) and price, the total, and the balance.
- Cost price, margin, internal notes, and staff identity beyond operational necessity never appear.

### Timeline — `SCR-CUS-007`

A vertical list of statuses reached with timestamps in **outlet local time**, 24-hour (`14:30`).
Statuses not yet reached are shown as pending, not as failures. A `REWORK` entry is described
honestly and neutrally — the laundry is being redone — without internal QC commentary.

### Payment Summary — `SCR-CUS-008`

- Integer Rupiah throughout. Total, paid, balance.
- **This screen never marks anything paid.** An order is never marked paid on a client claim
  (`FIN-005`). It displays what the server says.
- A cached balance is shown only with a `UXS-020 Stale Data` freshness label and never presented as
  settled.

### Pickup Request — `SCR-CUS-009`, Address — `010`, Delivery Schedule — `011`

- The request captures address, preferred window, and notes. The window is a **preference**, and the
  copy says so. There is no guaranteed arrival time and no claim of route optimisation anywhere in
  this surface.
- Confirmation shows the pickup status from the canonical set: `REQUESTED` until the outlet confirms.
- Offline, the request is **blocked** with `UXS-004 Offline`; the draft is retained and offered again.
- Addresses are stored per tenant. An address given to one laundry is not shared with another.

### History, Loyalty, Membership — `SCR-CUS-012`, `013`, `014`

Loyalty, membership, and deposit balances are **per tenant**, always labelled with the tenant that
owns them. A cached balance is never used to imply spendable credit.

### Feedback — `SCR-CUS-015`

Held as a local draft and submitted on reconnect. Submitting feedback never changes order state.

### Notifications — `SCR-CUS-016`

- Reached from a header affordance on every top-level destination.
- Notification **preferences and opt-out** live in Profil. Opting out of marketing is honoured
  permanently across all outlets of the tenant, and is never reset by a data import (`NOT-013`).
- Transactional and marketing messages are separated. A marketing message is never delivered through
  a transactional path to evade opt-out.
- Quiet hours default to **20.00–08.00 outlet local time** (`NOT-003`); a message queued in that
  window is deferred, not dropped and not sent anyway.
- If the provider is degraded, `UXS-016 Provider Degraded` explains that message delivery may be
  delayed **and that the order is unaffected**. A messaging failure never changes business state.

### Profile — `SCR-CUS-017`

Personal data, addresses, notification preferences, language, sign out. Sign out is separated
spatially from routine actions.

### Error and Recovery — `SCR-CUS-018`

The last-resort destination. Reachable from any unrecoverable navigation failure. Always offers
*Kembali ke Beranda* and *Hubungi outlet*. A customer is never stranded on a screen whose only exit is
killing the app.

---

## 4. Privacy on this surface

| Rule | Detail |
|---|---|
| The customer sees their own data only | Never another customer, never staff records, never tenant internals |
| Phone shown masked in lists | `0812-XXXX-1234`; the full number appears only on the customer's own profile |
| Address shown only to its owner | Never in a shared or forwardable view |
| Photographs | Condition and proof photographs are `RESTRICTED`; where a customer may see their own, it is via a signed expiring URL, never a public link |
| Authorized Recipient | Sees **one order**, never a customer's order list, addresses, or payment history |
| Tracking tokens | Never held in authenticated app state; a `track/{token}` link is handed to the browser |
| Analytics | Records screen and action shape only — never a name, phone, address, order contents, or token |

---

## 5. Accessibility

- Every status carries **text and an icon**, never colour alone.
- Layouts survive the largest supported system font scale without truncating status, amount, or
  balance. Cards grow; information is not dropped.
- Tap targets meet the minimum size on the compact breakpoint; primary actions are comfortably
  reachable one-handed.
- Contrast meets accessible ratios in both the light surface and on the gold accent, which is used as
  an accent and never as a text background.
- Every interactive element has a meaningful accessible name in Bahasa Indonesia.
- State changes are announced through a live region.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.** No accessibility testing has
occurred; none can occur until Step 11 produces something to test.

---

## 6. Responsive behaviour

| Breakpoint | Layout |
|---|---|
| compact `<600px` | The design target. Single column, bottom navigation, cards full width |
| medium `600–1023px` | Two-column card grid on Beranda and Pesanan; navigation remains at the bottom |
| expanded `1024–1439px` | List and detail side by side; navigation moves to a rail |
| wide `>=1440px` | As expanded, width-capped so text lines stay readable |

---

## 7. What this surface must never do

1. Require an app install to track an order (`TRK-025`, DEC-0014).
2. Show any aggregate that crosses tenants.
3. Merge two customer profiles because a phone number matches across tenants.
4. Present a cached money figure without a freshness marker.
5. Mark an order paid on a client claim.
6. Claim a guaranteed delivery time or an optimised route.
7. Promise unlimited WhatsApp messaging.
8. Send a marketing message to a customer who opted out.
9. Show an internal note, a cost price, or a margin.
10. Leave the customer on a screen with no exit.

---

## 8. Related documents

- [`./information-architecture/CUSTOMER_ANDROID_IA.md`](./information-architecture/CUSTOMER_ANDROID_IA.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_STATE_MODEL.md`](./UX_STATE_MODEL.md)
- [`./TRACKING_PORTAL_UX.md`](./TRACKING_PORTAL_UX.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)

## 9. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Customer Android | **NOT IMPLEMENTED** |
| Flutter workspace | **ABSENT** |
| Accessibility runtime testing | **NOT STARTED** |
| Usability testing | **NOT STARTED** |

`GO` is conferred by the repository owner and is never self-declared.
