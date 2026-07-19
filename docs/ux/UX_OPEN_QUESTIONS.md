# UX Open Questions

**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED

---

## 0. How to use this document

These are **genuine gaps**, not placeholders. Every item below is a question the Master Source and the
accepted decision records do not answer, raised here rather than closed by invention.

> **Never close a gap by inventing a product decision.** A placeholder that looks like a decision will
> be read as a decision. Every question below is escalated to the repository owner and remains open
> until the owner answers it — and where the answer changes a product decision, until a decision
> record is accepted.

Nothing in this document is a design that has been adopted. Where an option is listed, it is listed as
an option under consideration, not as a chosen direction.

### Status vocabulary used here

| Status | Meaning |
|---|---|
| **OPEN** | Awaiting an owner decision |
| **BLOCKING** | Awaiting an owner decision **and** blocking a later step's design work |

---

## 1. Question index

| ID | Question | Affects step | Status |
|---|---|---|---|
| `UXQ-001` | Component vocabulary is not yet governed | 2 | **BLOCKING** |
| `UXQ-002` | Ops Android bottom navigation exceeds five destinations for a multi-role user | 5 | OPEN |
| `UXQ-003` | Proof method policy defaults per tenant | 8 | **BLOCKING** |
| `UXQ-004` | Quiet-hours exception path for critical operational messages | 7 | **BLOCKING** |
| `UXQ-005` | Discount approval when the device is offline | 5 | **BLOCKING** |
| `UXQ-006` | Non-financial conflict resolution rule | 5 | OPEN |
| `UXQ-007` | Customer visibility of condition photographs | 11 | OPEN |
| `UXQ-008` | Authorized Recipient identity verification on the portal | 7 | **BLOCKING** |
| `UXQ-009` | Portfolio Mode threshold and behaviour for a single-tenant owner | 10 | OPEN |
| `UXQ-010` | Reason-not-collected taxonomy | 9 | OPEN |
| `UXQ-011` | Storage-fee configuration surface and copy | 9 | OPEN |
| `UXQ-012` | Estimated-readiness calculation and how it is worded | 7 | OPEN |
| `UXQ-013` | Language beyond Bahasa Indonesia | 2 | OPEN |
| `UXQ-014` | Handover of unsynced work after device revocation | 3 | **BLOCKING** |
| `UXQ-015` | Corporate Customer Contact scope in the Customer app | 11 | OPEN |

---

## 2. The questions

### UXQ-001 — Component vocabulary is not yet governed · **BLOCKING** · Step 2

**Context.** The Step 2 journey documentation names design-system components — `StatusChip`,
`SyncBadge`, `MoneyField`, `TenantSwitcher`, `ConflictResolutionPanel`, `ProofCaptureSheet`,
`AgingBucketTable`, `OfflineQueueList`, and roughly two dozen more introduced while writing the
journeys. There is currently **no component inventory** in this repository against which those names
can be reconciled.

**Why it matters.** The domain rules require one concept, one term, with a glossary entry in the same
pull request that introduces a term. Component names that proliferate without governance are exactly
the synonym drift that forks a design system — `SyncBadge` and `SyncChip` will both appear, and then
both will be implemented.

**Question for the owner.** Should Step 2 produce a governed component inventory with a naming rule
and a glossary linkage, and should the names already used in the journey documents be treated as
provisional until that inventory exists?

**Not decided.** No component inventory has been created, and none of these names is canonical.

---

### UXQ-002 — Ops Android navigation exceeds five destinations · OPEN · Step 5

**Context.** The Ops Android bottom bar is capped at five destinations. An Outlet Manager who also
works the counter legitimately needs Beranda, Kasir, Produksi, Tugas, Antrean, **and** Lainnya — six.

**Options under consideration.** Collapse Produksi and Tugas into a single "Operasi" destination for
multi-role users; or promote Antrean to a persistent header affordance rather than a bar destination;
or accept six for this role only.

**Constraint that cannot move.** Antrean is never hidden while anything is unsynced, and cashier
critical actions are never buried.

**Question for the owner.** Which trade-off is preferred, and is a role-dependent bar length
acceptable?

---

### UXQ-003 — Proof method policy defaults · **BLOCKING** · Step 8

**Context.** Proof of pickup and delivery is mandatory, and the accepted methods — OTP, photo,
signature, recipient name — may vary by tenant policy. The **default** for a new tenant is not
specified anywhere.

**Why it matters.** The default becomes the policy for most tenants, because most tenants never change
a default. A weak default weakens custody evidence across the whole customer base; a heavy default
produces skipped proofs and workarounds on a motorbike in the rain.

**Question for the owner.** What is the default proof method for a new tenant, and may a tenant reduce
it below a floor — and if so, what is the floor?

---

### UXQ-004 — Quiet-hours exception path · **BLOCKING** · Step 7

**Context.** Quiet hours default to 20.00–08.00 outlet local time and no non-critical message is sent
inside the window. A **critical operational message** may have a defined exception path only if the
Master Source or an accepted decision record explicitly grants it. No such record exists.

**Consequence today.** Absent that record, quiet hours apply to everything — including, for example,
a courier arriving at 20.15 for a scheduled delivery.

**Question for the owner.** Should a narrow critical-message exception exist, and if so, exactly which
message types qualify? This requires a decision record, not a design choice.

---

### UXQ-005 — Discount approval offline · **BLOCKING** · Step 5

**Context.** A discount above a threshold requires an approval that only the server can grant. A
cashier offline cannot obtain it.

**Options under consideration.** Refuse the discount offline; or capture it as a **requested** discount
that applies only after server approval, with the customer charged the undiscounted amount until then;
or permit an outlet-manager PIN override captured locally and audited on sync.

**Constraint that cannot move.** Nothing may create a financial effect that the server did not
authorise, and the customer must never be told a price that then changes.

**Question for the owner.** Which behaviour is intended? This is a pricing and authorisation decision,
not a UX preference.

---

### UXQ-006 — Non-financial conflict resolution · OPEN · Step 5

**Context.** Conflicts affecting money escalate to a human. Conflicts affecting non-financial metadata
may use a documented last-write rule — **but only if that rule is written down**. It is not yet
written down.

**Question for the owner.** For fields such as a customer note, a preferred pickup window, or a
condition remark, is a last-write-wins rule acceptable, and must the interface always disclose which
value won?

---

### UXQ-007 — Customer visibility of condition photographs · OPEN · Step 11

**Context.** Condition photographs taken at intake are `RESTRICTED` data. Whether a customer may view
photographs of **their own** items in the Customer Android app is unspecified.

**Tension.** Showing them reduces disputes. Not showing them reduces the exposure surface for images
that may include a customer's home or personal garments.

**Question for the owner.** May a customer view their own condition photographs, and if so, through
what access mechanism and for how long?

---

### UXQ-008 — Authorized Recipient verification · **BLOCKING** · Step 7

**Context.** Persona P-14 (Authorized Order Recipient) may collect an order on the customer's behalf.
How the portal or the counter verifies that person is unspecified.

**Why it matters.** This is the point where laundry is handed to someone who is not the customer.
Getting it wrong means handing a stranger someone's belongings; getting it heavy-handed means a
family member is turned away.

**Question for the owner.** What verification is required — an OTP forwarded by the customer, a named
authorisation recorded in advance, or counter discretion — and is the answer the same for a counter
collection and a courier delivery?

---

### UXQ-009 — Portfolio Mode threshold · OPEN · Step 10

**Context.** Portfolio Mode appears for an owner with two or more active memberships. What a
single-tenant owner sees, and what happens when an owner drops from two tenants to one, is
unspecified.

**Question for the owner.** Should Portfolio Mode be hidden entirely below two tenants, or present as
a single-row view so the interface does not change shape when a second tenant is added?

---

### UXQ-010 — Reason-not-collected taxonomy · OPEN · Step 9

**Context.** "Reason not collected" is a first-class field and is the data that actually reduces the
pile. The permitted reason codes are not defined.

**Why it matters.** A free-text-only field produces unanalysable data; an invented code list produces
a product decision made by a designer.

**Question for the owner.** Should Step 9 propose a reason-code list for owner approval, and should
the field be code plus free text rather than either alone?

---

### UXQ-011 — Storage-fee configuration surface · OPEN · Step 9

**Context.** Storage fee is optional, tenant-configured, subject to policy, and not assumed active.
Where a tenant configures it, what the interface must warn them about, and what the customer is told
are all unspecified.

**Constraint that cannot move.** Whether a storage fee is lawful and enforceable is the tenant's
question. The product does not advise on it.

**Question for the owner.** Should the configuration surface carry an explicit statement that legality
is the tenant's responsibility, and must a fee be disclosed to a customer before it can accrue?

---

### UXQ-012 — Estimated readiness · OPEN · Step 7

**Context.** The tracking portal and the Customer app show an estimated readiness date, labelled an
estimate. How the estimate is derived — service-level default, outlet configuration, historical
throughput — is unspecified.

**Constraint that cannot move.** No estimate may be worded as a guarantee, anywhere.

**Question for the owner.** What derives the estimate, and should it be suppressed entirely where the
derivation is not confident, rather than shown with a wide margin?

---

### UXQ-013 — Language beyond Bahasa Indonesia · OPEN · Step 2

**Context.** The primary language is Bahasa Indonesia and all UI copy is written in it. Whether any
surface must also support another language — English for a corporate customer contact, or a regional
language — is unspecified.

**Question for the owner.** Is Bahasa Indonesia the only supported language for the entire roadmap,
and should the design nonetheless avoid layouts that would break under longer translated strings?

---

### UXQ-014 — Handover of unsynced work after device revocation · **BLOCKING** · Step 3

**Context.** When a device is revoked while holding unsynced financial operations, those operations
must not be silently discarded and must not sync under the revoked credential. The **handover
mechanism** is unspecified.

**Options under consideration.** Display the list for manual re-entry by an authorised user under an
audited process; or permit a one-time, permissioned, audited drain under a manager's credential; or
require the manager to re-enter each operation from the printed unconfirmed receipts.

**Why it matters.** This is real money on a device that is no longer trusted. Every option has a
different fraud surface.

**Question for the owner.** Which mechanism is intended? This is a financial-integrity decision.

---

### UXQ-015 — Corporate Customer Contact scope · OPEN · Step 11

**Context.** Persona P-13 (Corporate Customer Contact) manages orders for an organisation. Whether
that contact sees every order placed by every employee of the organisation, and whether individual
employees can see each other's orders, is unspecified.

**Question for the owner.** What is the visibility model within a corporate account, and does it
require a distinct account structure rather than a role on an ordinary customer account?

---

## 3. What is explicitly **not** an open question

Recorded so that these are never reopened as design discussions:

| Settled | Where |
|---|---|
| Money is integer Rupiah; floating point is forbidden in financial paths | Financial integrity hard gate |
| Aging anchors to the **first** `READY_FOR_PICKUP` and never restarts | `UCL-001` |
| The ladder is exactly H+1, H+3, H+7 (plus follow-up task), H+14 (escalation) | DEC-0008 |
| No automatic disposal, sale, auction, donation, or ownership transfer — ever | Absolute prohibition |
| Route ordering is a suggestion; no optimisation or delivery guarantee is claimed | `DEL-010`, DEC-0007 |
| The tracking portal never requires an app install and is never replaced by the app | `TRK-025`, DEC-0006, DEC-0014 |
| Client-side visibility is not authorization | Security baseline |
| A retry reuses the original `client_reference` | Offline design |
| Cross-tenant exposure is an automatic NO-GO | Tenant isolation hard gate |
| Pricing figures are canonical and are never restated from memory | Commercial guardrails |

---

## 4. Escalation

Every question above is escalated to the repository owner. Questions marked **BLOCKING** should be
resolved before the design work of the step they affect begins, because designing around an unmade
decision produces work that must be discarded.

Where an answer changes a product decision, it requires a decision record under `docs/decisions/`,
a Master Source version bump, and a refreshed checksum — not an edit to this file.

## 5. Related documents

- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./CRITICAL_JOURNEYS.md`](./CRITICAL_JOURNEYS.md)
- [`./USABILITY_TEST_PLAN.md`](./USABILITY_TEST_PLAN.md)
- [`./UNCLAIMED_LAUNDRY_UX.md`](./UNCLAIMED_LAUNDRY_UX.md)

## 6. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Open questions | **OPEN** — awaiting owner decisions |
| Blocking questions | 5 of 15 |
| Product decisions invented to close a gap | **NONE** |

`GO` is conferred by the repository owner and is never self-declared.
