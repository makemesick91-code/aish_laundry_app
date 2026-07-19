# Courier UX

**Surfaces:** Ops Android (Courier Internal, P-09) · Scoped guest job link (External Local Courier, P-10)
**Roadmap step:** Step 8 — Pickup and Delivery Operations
**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Backend runtime:** ABSENT

> **Documentation is not implementation.** No job screen, no proof capture, no guest link exists.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. Why couriers get their own document

Pickup and delivery introduces the two riskiest operational surfaces in the product: **physical
custody of a customer's belongings** and **cash held by a person on a motorbike**. Both are handled
by a user who is outdoors, in a hurry, wearing a helmet, on a cheap phone, with one hand.

Complexity here does not produce a slower workflow. It produces **skipped proofs and lost cash**.

---

## 2. Design bias

1. **Few steps.** A delivery is four taps: berangkat, tiba, bukti, selesai.
2. **Large targets.** Bigger than anywhere else in the product.
3. **One hand.** Everything reachable with a thumb; nothing in the top corners.
4. **Outdoors legible.** High contrast, large type, no thin strokes, no colour-only status.
5. **Offline by default.** Signal loss is the normal case, not an exception.

---

## 3. The job list and the job

### What a courier sees

| Shown | Not shown |
|---|---|
| Jobs assigned to **this courier**, for **today**, in the active tenant | Any job not assigned to them |
| Order reference `AL-2026-000123` | Customer order history |
| Recipient name, and phone **masked** until the courier marks `ARRIVED` | The tenant's customer database |
| Approximate area before arrival (`Jl. Melati`), full detail revealed on `ARRIVED` | Full address in a shareable or indexable form |
| Amount to collect, integer Rupiah, `Rp79.000` | Pricing structure, discounts, cost, or margin |
| Suggested stop order | Any claim of an optimal route |
| Job status from the canonical set | Internal notes, QC commentary, staff records |

> **A courier must never be able to browse the tenant's customer database.** There is no customer
> search on this surface, and there is no destination that lists customers. This is enforced
> server-side from Step 3; the missing menu is a reflection of that, not a substitute for it.

### Statuses

Only the canonical eleven: `REQUESTED`, `CONFIRMED`, `SCHEDULED`, `ASSIGNED`, `EN_ROUTE`, `ARRIVED`,
`PICKED_UP`, `DELIVERED`, `FAILED`, `RESCHEDULED`, `CANCELLED`. Each transition is requested by the
client and decided by the server.

---

## 4. Route ordering — a suggestion, and only ever a suggestion

The stop list is presented as **usulan rute**. Wherever it appears, on any surface, it is labelled:

> **USULAN RUTE — BUKAN RUTE OPTIMAL**

| Permitted | Forbidden |
|---|---|
| "Usulan urutan pemberhentian" | "Rute optimal" |
| "Perkiraan waktu, dapat berubah" | "Tiba pukul 14:30 dijamin" |
| A reorderable list the courier controls | Any implication the order was computed optimally |
| Time windows described as preferences | A delivery guarantee of any kind |

The courier may reorder stops freely. The system does not object, does not warn that the suggestion
was "better", and does not record a deviation as a fault. `DEL-010` requires stop ordering to be
labelled a suggestion wherever it is presented; this surface treats that as copy law.

---

## 5. Proof of custody

**Proof is mandatory for every custody transfer.** A parcel never silently changes hands.

| Method | Use |
|---|---|
| **OTP** | Strongest. A code the recipient reads out |
| **Foto** | Photograph of the handover |
| **Tanda tangan** | Signature on the device |
| **Nama penerima** | Recorded name of who received it |

Rules:

1. The methods accepted are **tenant policy**. Some proof is **always** required.
2. `DELIVERED` is **unreachable** without a captured `DeliveryProof` (`DEL-027`). The interface does
   not offer a path to it; the server refuses one.
3. Proof artifacts are **private data**. Photographs may show the inside of a customer's home; a
   signature is handwriting. They are stored in private object storage, served only through signed
   expiring URLs, tenant-scoped, and **never** exposed on the public tracking portal.
4. Proof capture works **offline** (`DEL-013`) and uploads later. The job shows the proof as captured
   locally and not yet uploaded, using the sync vocabulary.
5. Every courier-captured transition is **idempotent on `ClientReference`** (`DEL-034`). A retry
   never records a second handover.
6. A recipient who is not the customer is recorded as an **Authorized Recipient** with a name — the
   product does not pretend the customer received it.

---

## 6. Failed delivery is a first-class outcome

A failed attempt is not an exception path bolted on at the end. It is a normal thing that happens on
Indonesian streets several times a day.

1. `FAILED` requires a **reason code** plus free text, and records the actor (`DEL-023`).
2. Typical reasons are offered as large tappable options: penerima tidak ada, alamat tidak ditemukan,
   penerima menolak, cucian belum siap, cuaca.
3. On failure, the laundry **returns to the outlet** and the order returns to a defined status with
   the recorded reason. Nothing is left in limbo.
4. `RESCHEDULED` is offered as the immediate next action where the customer agreed to a new time.
5. A failed attempt **never** silently marks the order complete, and never marks it paid.
6. Recording a failure works offline, like every other custody transition.

---

## 7. Courier cash — COD and reconciliation

Cash collected on delivery is a **financial transaction** and inherits every financial rule.

| Rule | Behaviour |
|---|---|
| Integer Rupiah | `Rp79.000`. No floating point anywhere in the path |
| Idempotent | Keyed on `ClientReference`; a retry never records a second collection |
| Offline | Recorded locally; the figure is marked **provisional** until acknowledged |
| Per courier, per shift | Tracked from collection through handover (`DEL-030`) |
| Reconciliation | Expected versus actual is compared **explicitly** at handover |
| Variance | **Recorded and acknowledged, never absorbed silently.** No auto-rounding, no write-off, no suppression from the report |
| No delete | There is no delete-collection action. Corrections are reversal or adjustment entries |
| Audit | Actor, tenant, outlet, timestamp, before and after amounts, and reason |

The handover screen shows: jobs completed, cash expected, cash counted, variance, and a mandatory
acknowledgement. **A visible discrepancy is a feature; a hidden one is fraud-shaped.**

---

## 8. The external courier guest link

An **External Local Courier (P-10, ojek lokal) never receives tenant navigation.** Not a reduced
menu, not a read-only console, not an Ops Android login. They receive a scoped guest job link.

| Property | Requirement |
|---|---|
| Token | High entropy, stored **hashed** server-side; the plaintext exists only in the link |
| Derivability | **Not** the order number and not derivable from it |
| Scope | Exactly one assigned job, in exactly one tenant |
| Lifetime | Expiring |
| Revocation | Revocable, and **revocation takes effect immediately** (`DEL-033`) |
| Reach | **No** customer history, **no** other orders, **no** pricing, **no** tenant data beyond the assignment |
| Address | Only what the delivery genuinely requires, and **never in a shareable or indexable form** |
| Multi-tenant | A courier working for two tenants gets **two unrelated links** and can never traverse from one to the other |
| Indexing | `noindex` on every response |

### What the guest link screen offers

A single job card and four large actions: *Terima tugas*, *Berangkat* (`EN_ROUTE`), *Tiba*
(`ARRIVED`), *Bukti serah terima* (proof, then `DELIVERED` or `FAILED` with a reason). Cash collected
is recorded where the job carries a COD amount.

Nothing else. No list, no search, no history, no navigation.

---

## 9. Notifications

Delivery notifications follow the messaging rules and are **decoupled from job state**:

- Quiet hours default to 20.00–08.00 outlet local time; a non-critical message queued in that window
  is deferred, not dropped and not sent anyway.
- Deduplication is mandatory across retries, queue replays, and scheduler restarts.
- Opt-out is honoured at send time.
- **No notification outcome ever changes job state** (`DEL-035`). If the provider is down, the
  delivery proceeds and the message is retried or flagged.

---

## 10. Accessibility

- Targets on this surface are the largest in the product; primary actions occupy the full width at
  the bottom of the screen.
- Status carries **text and icon**, never colour alone — a courier reads this in direct sunlight.
- The signature pad is not the only proof route; a courier who cannot use it has OTP, photo, and
  recipient name available, so proof is never blocked by one input modality.
- Layouts survive the largest supported font scale without hiding the amount to collect or the job
  status.
- All copy is Bahasa Indonesia, short, and imperative.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## 11. What this surface must never do

1. Claim an optimal route or a guaranteed arrival time.
2. Reach `DELIVERED` without captured proof.
3. Expose a full customer address in a shareable or indexable form.
4. Show a courier the tenant's customer database.
5. Let a guest link reach a second job, a second order, or a second tenant.
6. Absorb a cash variance silently.
7. Delete a cash collection through ordinary UI.
8. Change job state because a notification failed.
9. Record a second handover from a retry.
10. Publish a proof photograph or signature on the public tracking portal.

---

## 12. Related documents

- [`./OPS_ANDROID_UX.md`](./OPS_ANDROID_UX.md)
- [`./OFFLINE_AND_SYNC_UX.md`](./OFFLINE_AND_SYNC_UX.md)
- [`./information-architecture/ROLE_NAVIGATION_MATRIX.md`](./information-architecture/ROLE_NAVIGATION_MATRIX.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)

## 13. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Courier surfaces | **NOT IMPLEMENTED** |
| Proof capture | **NOT IMPLEMENTED** |
| Guest job link | **NOT IMPLEMENTED** |
| Courier cash reconciliation | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |

`GO` is conferred by the repository owner and is never self-declared.
