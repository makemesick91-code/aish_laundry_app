# Ops Android UX

**Surface:** Aish Laundry Ops Android (Flutter)
**Roadmap steps:** Step 5 (POS, order, payment), Step 6 (production), Step 8 (pickup and delivery)
**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Flutter workspace:** ABSENT

> **Documentation is not implementation.** No screen, no queue, no printer integration exists.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. Design intent

This app runs on a cheap Android phone at a laundry counter with a customer standing in front of the
cashier, and on a motorbike in the rain. It is used under time pressure by people who did not choose
the software.

Design bias in priority order:

1. **Honesty about money and sync** — above everything.
2. **Speed on the common path.**
3. **One-handed operation.**
4. **Recoverability** — no action destroys work.
5. Feature completeness comes last.

---

## 2. Fast transactions

### The common path is the fast path

A kiloan order for a returning customer must be completable in a small, fixed number of taps, with no
optional step forced into the middle of it:

`Beranda → Pesanan baru → cari pelanggan → pilih layanan → berat → tinjau → bayar → cetak`

Rules:

1. **Cashier critical actions are never buried.** *Pesanan baru*, *Terima pembayaran*, and *Cetak
   ulang nota* are reachable within one tap of Beranda. They are never moved into *Lainnya* to make
   room for a new feature.
2. The running total is **always visible** during intake, in integer Rupiah.
3. Optional steps — discount, notes, condition evidence beyond policy minimum — are skippable in one
   tap and never block the path.
4. Numeric entry uses a large custom keypad, not the system keyboard, so weight and cash entry work
   with a thumb.
5. Stepping backwards never loses entered lines or captured photographs.

### Weight and money entry

- Weight is entered as `1,5 kg` with a comma decimal separator, in Indonesian convention.
- Money is **integer Rupiah**. There is no sub-Rupiah entry, no floating point, and no locale trick
  that could introduce one. Display is `Rp79.000`.
- Totals shown are computed server-side once the order is acknowledged. A locally computed total is
  labelled as provisional until then.
- The price captured is the price in force when the order was created. A later price-list change
  never alters this order, its invoice, or a reprint — and the review screen says so where a price
  has recently changed.

---

## 3. One-hand operation

| Requirement | Detail |
|---|---|
| Primary action position | Bottom of the screen, within thumb reach on a 360 px-wide device |
| Destructive action position | **Spatially separated** from routine actions — never adjacent to *Simpan* or *Bayar* |
| Tap target size | Meets the accessible minimum; courier and production screens use larger targets still |
| Scrolling | Vertical only. Horizontal scrolling appears only inside a table container, never on the page |
| Confirmation dialogs | Actions positioned consistently across the app so muscle memory does not misfire |
| Font scaling | Layouts survive the largest supported scale without hiding the total, the balance, or the sync state |

---

## 4. Offline-first and honest sync status

Full rules: [`./OFFLINE_AND_SYNC_UX.md`](./OFFLINE_AND_SYNC_UX.md). The experience obligations here:

1. The **sync chip is in the persistent context bar on every screen**, with an exact count.
2. The **Antrean destination is never hidden** while anything is unsynced.
3. `TERSINKRON` is the only state that means the server acknowledged. It is never shown on the
   strength of a local write.
4. A receipt printed before acknowledgement carries `BELUM DIKONFIRMASI SERVER`.
5. **No silent sync failure anywhere.** A failed item stays visible, stays actionable, and names its
   reason.
6. The shift-close screen shows unsynced operations **before** the variance calculation, because
   closing a shift against an incomplete picture produces a false variance.

---

## 5. Duplicate prevention

The single highest-risk behaviour in this app is a cashier taking a payment twice because the first
one looked lost.

| Control | Behaviour |
|---|---|
| `client_reference` | Generated once, before the first attempt, persisted with the queued operation |
| Retry | **Always reuses the original reference.** There is no affordance that resubmits with a fresh one |
| Already-applied response | Rendered as success: "Sudah tersinkron sebelumnya. Tidak ada pesanan ganda yang dibuat." |
| Submit button | Disabled immediately on tap and shows the captured state; it never remains tappable during submission |
| Draft resume | A draft interrupted by an app kill is offered for resume by customer name and running total, not silently re-created |
| Order search | Orders still in the queue are findable locally and marked with their sync state, so a cashier can confirm an order exists before re-entering it |
| Duplicate outcome | A duplicate order or duplicate payment produced by a retry is an automatic **NO-GO** |

---

## 6. Printer failure

The printer is the least reliable component in a laundry and it is **never** allowed to hold the
business hostage.

1. **A printer failure never blocks the order.** The order and the payment are already captured; the
   receipt is a side effect.
2. `SCR-OPS-019` states what failed — no paper, not connected, not found — and offers *Coba cetak
   lagi*, *Cetak nanti*, and *Kirim nota digital* where the tenant has messaging configured.
3. A queued reprint is retained and visible; it is not silently dropped.
4. A reprint of a **confirmed** receipt is visually distinct from the unconfirmed version. The
   unconfirmed receipt is never retroactively described as confirmed.
5. A messaging failure while sending a digital receipt never changes order state and never blocks the
   printer path.

---

## 7. Payment safety

| Rule | Behaviour |
|---|---|
| Integer Rupiah | Every amount, everywhere. No floating point in any financial path |
| Partial payment | First-class. Amount paid and remaining balance are both shown, both in integer Rupiah |
| Never paid on a client claim | An order is never marked paid because the device says so (`FIN-005`) |
| Offline payment | Recorded, receipted with `BELUM DIKONFIRMASI SERVER`, and never described as "berhasil" |
| Refund and void | Require a permission and a **reason**, recorded with actor, timestamp, amount, and reason text |
| No delete | There is **no delete-payment action** for ordinary roles anywhere in this app. Corrections are reversal or adjustment entries |
| Discount | Above a threshold, requires approval; the approval is server-granted and therefore unavailable offline, which is stated plainly rather than silently permitted |
| Confirmation | Tenant, outlet, customer, and amount appear inside the confirmation dialog |
| Conflict | A payment conflict escalates to a human. It is never resolved by a last-write rule |
| Shift close | Expected versus actual cash is compared explicitly; the variance is recorded and must be acknowledged, never absorbed silently |

---

## 8. Scan and production flow

### Label scan — `SCR-OPS-024`

- Scanning resolves **within the active tenant only**. A label from another tenant does not resolve
  and does not reveal that it exists.
- A scan that resolves to an order outside the operator's outlet scope shows `UXS-010 Permission
  Denied` without disclosing the order's contents.
- Scanning works offline against the local tenant-scoped cache, with results marked as possibly
  incomplete.

### Production queue and transitions — `SCR-OPS-022`, `023`, `025`, `026`, `027`, `028`

1. Transitions offered are **only** those the state machine enumerates from the current status. There
   is no free-text status and no generic "set status" control.
2. The canonical statuses are used verbatim: `AWAITING_PROCESS`, `SORTING`, `WASHING`, `DRYING`,
   `FINISHING`, `QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`.
3. A transition is **requested** by the client and **decided** by the server. A rejected transition
   changes nothing and says why.
4. Quality control uses the canonical four: `PENDING`, `PASSED`, `FAILED_REWORK_REQUIRED`,
   `WAIVED_WITH_AUTHORIZATION`. A waiver requires an explicit permission, a recorded reason, and an
   audit entry — there is no silent waiver control.
5. `REWORK` requires a reason code and, per tenant policy, evidence.
6. Reaching `READY_FOR_PICKUP` a second time after rework **does not restart the aging clock**. The
   first-ready timestamp is written once and is immutable. The interface shows the original
   first-ready time, not the most recent one, and labels it as such.
7. Offline transitions carry a `client_reference` and replay idempotently. A replayed transition that
   already applied is a no-op, shown as success.
8. `ISSUE` is a real state with a reason, an owner, and documented exits — not an error screen.

### What the Production Operator does not see

**No financial destination appears for this role.** No POS, no payment, no discount, no shift cash,
no courier cash, no customer balance. Production work does not require money, and a shared shop-floor
device should not carry it. This is enforced server-side from Step 3; the navigation merely reflects
it.

---

## 9. Accessibility

- Status is conveyed by **text and icon**, never colour alone — critical on a bright counter and for
  colour-blind staff.
- Layouts survive the largest supported system font scale without truncating the total, the balance,
  the sync count, or the tenant name.
- Tap targets exceed the minimum on all transactional and courier screens.
- Errors state what happened and what to do, in Bahasa Indonesia.
- Every state change — pending, syncing, failed, conflict — is announced through a live region.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## 10. Responsive behaviour

| Breakpoint | Layout |
|---|---|
| compact `<600px` | The design target. Single column, bottom navigation, large keypad |
| medium `600–1023px` | Two panes on POS: line items and keypad side by side; production queue two columns |
| expanded `1024–1439px` | Tablet counter layout: customer, lines, and totals visible simultaneously |
| wide `>=1440px` | Not a target for this surface; layout is capped rather than stretched |

---

## 11. Related documents

- [`./information-architecture/OPS_ANDROID_IA.md`](./information-architecture/OPS_ANDROID_IA.md)
- [`./OFFLINE_AND_SYNC_UX.md`](./OFFLINE_AND_SYNC_UX.md)
- [`./COURIER_UX.md`](./COURIER_UX.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_STATE_MODEL.md`](./UX_STATE_MODEL.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)

## 12. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Ops Android | **NOT IMPLEMENTED** |
| Offline queue | **NOT IMPLEMENTED** |
| Printer integration | **NOT IMPLEMENTED** |
| Flutter workspace | **ABSENT** |
| Usability testing | **NOT STARTED** |

`GO` is conferred by the repository owner and is never self-declared.
