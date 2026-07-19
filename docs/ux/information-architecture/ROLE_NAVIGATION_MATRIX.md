# Role Navigation Matrix

**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED
**Backend runtime:** ABSENT

---

## 0. The only statement that matters

> **CLIENT-SIDE MENU VISIBILITY IS NOT AUTHORIZATION.**

Everything in this document describes what a navigation surface **shows**. It describes nothing about
what a user is **permitted to do**. Those are different systems, and conflating them is the classic
way a multi-tenant product leaks.

1. Hiding a destination is a user-experience decision that reduces noise and error.
2. Permitting an operation is a **server-side decision**, taken against the authenticated identity
   and its `Membership`, and it becomes authoritative in **Step 3**.
3. A destination marked `HIDDEN` here that is nevertheless reached — by a crafted URL, a deep link, a
   stale bookmark, a modified client, or a bug — **must still be refused by the backend**.
4. A destination marked `READ-ONLY` here must be enforced as read-only by the backend, not merely
   rendered without buttons. A disabled button is a UX affordance, never an access control.
5. A client-supplied tenant identifier is **never** authorization proof. It is an untrusted hint
   validated against the authenticated user's memberships on every request.

Any future implementation that derives permission from this matrix has misread it.

---

## 1. Legend

| Marking | Meaning |
|---|---|
| **VISIBLE** | The destination appears in navigation and its actions are offered, subject to server-side authorisation |
| **READ-ONLY** | The destination appears; data may be read; write actions are not offered — and are refused server-side |
| **HIDDEN** | The destination does not appear in navigation for this role — and is refused server-side if reached anyway |
| **SCOPED** | Visible, but restricted to a narrower slice (own outlet, own jobs, own shift); the narrowing is enforced server-side |

Surfaces: **CUS** = Customer Android · **OPS** = Ops Android · **CON** = Console Web · **TRK** =
Public Tracking Portal.

---

## 2. The fourteen roles

| # | Role | Persona | Primary surface |
|---|---|---|---|
| 1 | Platform Super Admin | P-01 | Console Web |
| 2 | Platform Support | P-02 | Console Web (audited support access only) |
| 3 | Tenant Owner | P-03 | Console Web |
| 4 | Tenant Admin | P-04 | Console Web |
| 5 | Outlet Manager | P-05 | Console Web and Ops Android |
| 6 | Cashier | P-06 | Ops Android |
| 7 | Production Operator | P-07 | Ops Android |
| 8 | Quality Control | P-08 | Ops Android |
| 9 | Courier Internal | P-09 | Ops Android |
| 10 | External Courier | P-10 | **Scoped guest link only** |
| 11 | Finance | P-11 | Console Web |
| 12 | Customer | P-12 | Customer Android and Tracking Portal |
| 13 | Corporate Customer Contact | P-13 | Customer Android and Tracking Portal |
| 14 | Authorized Recipient | P-14 | Tracking Portal (and a single order in Customer Android) |

---

## 3. The External Courier exception — stated first because it is the highest risk

**An External Courier (ojek lokal, P-10) never receives tenant navigation.** Not a reduced menu, not
a read-only console, not a limited Ops Android login. There is no navigation for this role at all.

An external courier receives a **secure guest job link** with these properties:

| Property | Requirement |
|---|---|
| Token | High entropy, stored **hashed** server-side |
| Scope | Exactly one assigned job, in exactly one tenant |
| Lifetime | Expiring, and **revocable with immediate effect** (`DEL-033`) |
| Derivability | **Not** the order number and not derivable from it |
| Customer data | Only what the job genuinely requires; **never a full address in a shareable or indexable form** |
| Reach | **No** customer history, **no** other orders, **no** pricing, **no** tenant data beyond the assignment |
| Multi-tenant | A courier working for two tenants gets **two unrelated links** and can never traverse between them |
| Actions | Accept, mark `EN_ROUTE`, mark `ARRIVED`, capture proof, record `FAILED` with a reason, record cash collected |

Every row for External Courier below therefore reads `HIDDEN` on every tenant destination. That is
not an oversight; it is the design.

---

## 4. Master matrix — all fourteen roles against every top-level destination

### 4.1 Customer Android destinations

| Destination | Platform Super Admin | Platform Support | Tenant Owner | Tenant Admin | Outlet Manager | Cashier | Production Operator | Quality Control | Courier Internal | External Courier | Finance | Customer | Corporate Customer Contact | Authorized Recipient |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| CUS Beranda | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | VISIBLE | VISIBLE |
| CUS Pesanan | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (one order) |
| CUS Jemput | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | VISIBLE | HIDDEN |
| CUS Loyalitas | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | HIDDEN | HIDDEN |
| CUS Profil | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY |
| CUS Notifikasi | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY |

A staff member who is *also* a customer of the same laundry uses a **customer identity**, not a staff
identity. The two are never blended, and staff navigation never appears in the customer app.

### 4.2 Ops Android destinations

| Destination | Platform Super Admin | Platform Support | Tenant Owner | Tenant Admin | Outlet Manager | Cashier | Production Operator | Quality Control | Courier Internal | External Courier | Finance | Customer | Corporate Customer Contact | Authorized Recipient |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| OPS Beranda | HIDDEN | HIDDEN | VISIBLE | VISIBLE | VISIBLE | VISIBLE | VISIBLE | VISIBLE | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Kasir (POS) | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Pembayaran | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| OPS Diskon | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | SCOPED (approval required) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Produksi | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | READ-ONLY | VISIBLE | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Kendali Mutu | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | HIDDEN | READ-ONLY | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Tugas (jobs) | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | HIDDEN | HIDDEN | HIDDEN | SCOPED (own jobs) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Bukti serah terima | HIDDEN | HIDDEN | READ-ONLY | READ-ONLY | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | SCOPED (own jobs) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Kas kurir | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | HIDDEN | HIDDEN | HIDDEN | SCOPED (own cash) | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| OPS Antrean (queue) | HIDDEN | HIDDEN | VISIBLE | VISIBLE | VISIBLE | VISIBLE | VISIBLE | VISIBLE | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Shift | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | VISIBLE | SCOPED (own shift) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| OPS Pencarian pelanggan | HIDDEN | HIDDEN | VISIBLE | VISIBLE | VISIBLE | VISIBLE | HIDDEN | HIDDEN | **HIDDEN** | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| OPS Pengaturan | HIDDEN | HIDDEN | VISIBLE | VISIBLE | VISIBLE | READ-ONLY | READ-ONLY | READ-ONLY | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |

Two deliberate refusals, restated because they are easy to erode:

- **A Production Operator sees no financial destination.** No POS, no payment, no discount, no shift
  cash, no courier cash. Production work does not require money, and exposing it widens the blast
  radius of a shared shop-floor device.
- **A Courier Internal never gets customer search.** A courier sees the jobs assigned to them and the
  minimum recipient detail those jobs require. **A courier must never be able to browse the tenant's
  customer database.**

### 4.3 Console Web destinations

| Destination | Platform Super Admin | Platform Support | Tenant Owner | Tenant Admin | Outlet Manager | Cashier | Production Operator | Quality Control | Courier Internal | External Courier | Finance | Customer | Corporate Customer Contact | Authorized Recipient |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| CON Portfolio Dashboard | HIDDEN | HIDDEN | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Tenant Dashboard | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Outlet Dashboard | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Orders | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Production Board | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | READ-ONLY | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Customers | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Services | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Pricing | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Employees | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Roles | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Pickup and Delivery Planning | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | READ-ONLY (own jobs) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Courier Assignment | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Route Ordering (usulan rute) | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | READ-ONLY (own route) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Unclaimed Laundry | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Receivables | HIDDEN | HIDDEN | VISIBLE | READ-ONLY | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | HIDDEN | HIDDEN | HIDDEN |
| CON Cashier Shifts | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | READ-ONLY (own shift) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | HIDDEN | HIDDEN | HIDDEN |
| CON Courier Settlement | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | READ-ONLY (own settlement) | HIDDEN | VISIBLE | HIDDEN | HIDDEN | HIDDEN |
| CON Reports | HIDDEN | HIDDEN | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | VISIBLE | HIDDEN | HIDDEN | HIDDEN |
| CON Subscription | VISIBLE | READ-ONLY | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Audit | VISIBLE | READ-ONLY | VISIBLE | VISIBLE | SCOPED (own outlet) | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | READ-ONLY | HIDDEN | HIDDEN | HIDDEN |
| CON Support Access | VISIBLE | VISIBLE | READ-ONLY (sees sessions on own tenant) | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Integrations | READ-ONLY | HIDDEN | VISIBLE | VISIBLE | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |
| CON Settings | HIDDEN | HIDDEN | VISIBLE | VISIBLE | READ-ONLY | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN | HIDDEN |

Platform notes:

- **Platform Super Admin and Platform Support hold no ordinary tenant navigation.** Platform
  administration is a distinct, audited path. It is never implemented by relaxing tenant scoping for
  an ordinary role.
- **Support Access is time-bound, reason-carrying, and audited.** There is no silent platform access
  to tenant data. A Tenant Owner can see that a support session happened, when, for how long, and why.

### 4.4 Public Tracking Portal

| Destination | Any staff role | Customer | Corporate Customer Contact | Authorized Recipient | External Courier |
|---|---|---|---|---|---|
| TRK Status page | HIDDEN (staff use Ops or Console) | SCOPED (valid token, one order) | SCOPED (valid token, one order) | SCOPED (valid token, one order) | HIDDEN — an external courier uses the separate guest job link, never the customer portal |
| TRK Contact outlet | HIDDEN | VISIBLE | VISIBLE | VISIBLE | HIDDEN |
| TRK Payment balance | HIDDEN | READ-ONLY | READ-ONLY | HIDDEN | HIDDEN |
| TRK OTP step-up | HIDDEN | VISIBLE | VISIBLE | VISIBLE | HIDDEN |

The portal has no role model of its own. Possession of a valid, unexpired, unrevoked token grants
the safe projection of exactly one order — nothing else, in any tenant.

---

## 5. Cross-cutting refusals

| Refusal | Applies to | Why |
|---|---|---|
| No cross-tenant list, search, count, export, or file access | Every role, every surface | Cross-tenant exposure is an automatic **NO-GO** |
| No destination aggregates across tenants except Portfolio Mode for an owner's own memberships | Tenant Owner | Aggregation must not widen the query surface |
| No hard-delete destination for a financial record | Every role, every surface | Corrections are reversal or adjustment entries |
| No destination offering automatic disposal, sale, auction, donation, or ownership transfer of customer laundry | Every role, every surface | Absolutely prohibited; the product's role ends at reminding, escalating, and reporting |
| No destination claiming route optimisation or a guaranteed arrival time | Every role, every surface | Route ordering is a suggestion — "usulan rute" |
| No destination presenting "unlimited WhatsApp" or an equivalent claim | Every role, every surface | Messaging has a real third-party cost |

---

## 6. Related documents

- [`./CUSTOMER_ANDROID_IA.md`](./CUSTOMER_ANDROID_IA.md)
- [`./OPS_ANDROID_IA.md`](./OPS_ANDROID_IA.md)
- [`./CONSOLE_WEB_IA.md`](./CONSOLE_WEB_IA.md)
- [`./TRACKING_PORTAL_IA.md`](./TRACKING_PORTAL_IA.md)
- [`./TENANT_OUTLET_CONTEXT_MODEL.md`](./TENANT_OUTLET_CONTEXT_MODEL.md)
- [`../SCREEN_INVENTORY.md`](../SCREEN_INVENTORY.md)

## 7. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Navigation implementation | **NOT IMPLEMENTED** |
| Role-based access control | **NOT IMPLEMENTED** (delivered in Step 3) |
| Backend runtime | **ABSENT** |

`GO` is conferred by the repository owner and is never self-declared.
