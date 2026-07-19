# Usability Test Plan

**Step 2 status:** IN PROGRESS
**Usability testing status:** **NOT STARTED**
**UAT status:** **NOT STARTED**

---

## 0. What this document is, and what it is not

> **THIS IS A PLAN. NO USABILITY TESTING HAS BEEN CONDUCTED.**

Nothing in this document reports a result. There are no findings, no scores, no participant quotes,
and no conclusions — because no session has been run, and there is nothing yet to run one against.
The Flutter workspace is **ABSENT** and every screen is **NOT IMPLEMENTED**.

### This is not UAT

Usability testing and User Acceptance Testing are different activities with different owners,
different questions, and different consequences. Conflating them would let a designer's session be
reported as an owner's acceptance.

| | **Usability testing** (this document) | **User Acceptance Testing** |
|---|---|---|
| Question | Can a person accomplish the task without being confused or misled? | Does the delivered product meet the agreed requirements? |
| Owner | The design work of the relevant step | The repository owner |
| When | From Step 5 onward, per surface, as screens become real | **Step 14 — Pilot and Commercial Launch** |
| Output | Design findings and changes | An acceptance decision |
| Current status | **NOT STARTED** | **NOT STARTED** |
| Confers `GO`? | **No. Never.** | Contributes to the owner's decision; `GO` remains the owner's to confer |

A usability finding is never evidence that a requirement is met, and a usability session is never
reported as UAT.

---

## 1. Objectives

When testing eventually runs, it answers five questions:

1. Can a cashier complete a kiloan order and take a payment **faster than the manual process** they
   use today, without error?
2. Does a user always know **whether the server acknowledged** a financial operation?
3. Does a courier capture proof **every time**, without being tempted to skip it?
4. Can a customer answer "is it ready and what do I owe?" from the tracking portal **within a few
   seconds**, on a cheap phone?
5. Does an owner reading the unclaimed-laundry dashboard know **what to do next**?

---

## 2. Participants

| Segment | Persona | Target sessions per round | Recruitment note |
|---|---|---|---|
| Cashier at a small laundry | P-06 | 6 | Real counter staff, not proxies. Prior POS experience mixed |
| Production operator | P-07 | 4 | Shop-floor conditions, shared device |
| Quality control | P-08 | 3 | — |
| Internal courier | P-09 | 5 | Sessions conducted outdoors where safe to do so |
| External local courier (ojek) | P-10 | 4 | Guest link only; recruited via participating tenants |
| Outlet manager | P-05 | 4 | — |
| Tenant owner, multi-outlet | P-03 | 4 | At least two with more than one tenant, to test Portfolio Mode |
| Finance | P-11 | 3 | — |
| Customer | P-12 | 8 | Mix of ages; at least half who have never installed a laundry app |

Device mix must include **low-end Android phones on mobile data**, because that is the real
deployment target. Testing exclusively on a fast device would validate a product that does not exist
in the market.

### Participant data handling

- Participants are recruited with informed consent and may withdraw at any time.
- **No participant personal data enters this repository.** The repository is PUBLIC.
- Session notes committed here — if any ever are — are aggregated, anonymised, and contain no name,
  phone number, address, employer, recording, or screenshot containing real data.
- All examples in test scripts use fictional data: `Budi Santoso`, `0812-XXXX-1234`,
  `AL-2026-000123`, `Outlet Cempaka`.
- Recordings, if made, are stored outside this repository under the tenant's own consent terms and
  are never committed.

---

## 3. Test environment

| Aspect | Rule |
|---|---|
| Data | **Fictional seed data only.** No real customer, no real order, no real payment, no production database |
| Tenant | A dedicated test tenant, never a live tenant |
| Money | Test amounts only; no real payment gateway transaction |
| Messaging | Provider sandbox or disabled; **no message is ever sent to a real customer during a session** |
| Network | Deliberately degraded conditions are part of the protocol, not an accident |
| Credentials | Session credentials are issued per session and revoked afterwards; none are committed |

---

## 4. Method

- **Moderated task-based sessions**, one participant at a time, think-aloud protocol.
- Sessions run in Bahasa Indonesia, in the participant's own working environment where possible.
- The moderator does not assist until the participant asks twice or reaches a genuine dead end.
- Each round: run sessions, analyse, change the design, re-test the changed areas.
- Rounds are tied to roadmap steps; a surface is tested when it exists, not before.

### Network conditions (mandatory for Ops Android)

Every Ops Android round must include, at minimum:

1. Full connectivity.
2. Connectivity lost **mid-submit** on a payment.
3. Extended offline period followed by reconnection.
4. Application killed mid-submit and reopened.

These are the conditions that produce the failures the product exists to prevent.

---

## 5. Task scenarios

### Ops Android — cashier

| # | Task | Success looks like |
|---|---|---|
| 1 | Take a kiloan order for a returning customer: 1,5 kg, total `Rp79.000` | Completed without moderator help; running total noticed |
| 2 | Take a partial payment of `Rp25.000` | Remaining balance correctly understood as `Rp54.000` |
| 3 | Repeat task 2 **with the network disconnected mid-submit** | Participant states, unprompted, that the payment is **not yet confirmed by the server** |
| 4 | After task 3, determine whether the payment was recorded | Participant finds Antrean and reads the state correctly |
| 5 | The printer fails | Participant proceeds; does not believe the order was lost |
| 6 | Resolve a seeded sync conflict on an amount | Participant does **not** expect the app to choose; escalates or decides deliberately |
| 7 | Close a shift with a seeded cash variance | Participant sees and acknowledges the variance rather than looking for a way to clear it |

### Ops Android — production and QC

| # | Task | Success looks like |
|---|---|---|
| 8 | Move an order from `WASHING` to `DRYING` | Only legal transitions offered; no confusion about a "set status" control |
| 9 | Fail QC and create a `REWORK` with a reason | Reason captured; participant understands rework is not a punishment record |
| 10 | Find the original first-ready date of a reworked order | Participant reads the **original** anchor, not the later one |

### Courier

| # | Task | Success looks like |
|---|---|---|
| 11 | Complete a delivery with photo proof | Proof captured; participant does not look for a way to skip it |
| 12 | Record a failed attempt with a reason | Reason selected; participant understands the laundry returns to the outlet |
| 13 | Reorder the suggested stops | Participant describes the list as a **suggestion**, unprompted |
| 14 | Hand over collected cash with a seeded variance | Variance acknowledged, not hidden |
| 15 | External courier: complete a job from the guest link | Participant cannot reach anything beyond the one job, and does not expect to |

### Console Web

| # | Task | Success looks like |
|---|---|---|
| 16 | Multi-tenant owner: identify which of two tenants is under-performing | Participant stays in Portfolio Mode; does not expect customer records there |
| 17 | Enter one tenant, then return to portfolio | Participant recognises the mode change without being told |
| 18 | Read the unclaimed-laundry dashboard and decide the next action for the H+7 bucket | Participant assigns a follow-up owner or proposes delivery |
| 19 | Change a service price | Participant states, unprompted, that past orders are unaffected |
| 20 | Perform a bulk action | Participant reads the count and scope before confirming; supplies a reason without irritation |

### Customer Android and tracking portal

| # | Task | Success looks like |
|---|---|---|
| 21 | From a WhatsApp link, determine whether the laundry is ready and what is owed | Answered in a few seconds without scrolling |
| 22 | Open an expired link | Participant finds the recovery path without frustration |
| 23 | Request a pickup | Participant understands the window is a preference, not a guarantee |
| 24 | Opt out of marketing messages | Participant finds the control and trusts it |

---

## 6. Measures

| Measure | Type | Note |
|---|---|---|
| Task completion without assistance | Quantitative | Primary |
| Time on task | Quantitative | Compared against the participant's current manual process where one exists |
| Error rate, and error **severity** | Quantitative | A financial misunderstanding is severity 1 regardless of frequency |
| **Sync-state comprehension** | Quantitative | Did the participant correctly state whether the server acknowledged? This is the highest-weighted measure on Ops Android |
| **Duplicate-submission attempts** | Quantitative | Any attempt to resubmit out of uncertainty is a design failure, even where the system prevents the duplicate |
| Proof-capture skip attempts | Quantitative | Courier rounds |
| Route-suggestion misinterpretation | Quantitative | Any participant who describes the suggestion as optimal or guaranteed is a copy failure |
| Confidence and trust | Qualitative | Especially around money |
| Accessibility observations | Qualitative | Font scaling, contrast in sunlight, one-handed reach |

### Severity scale

| Severity | Definition |
|---|---|
| **1 — Critical** | The participant is misled about money, sync state, custody, or tenant context. Blocks the step |
| **2 — Major** | The task cannot be completed without assistance |
| **3 — Moderate** | The task is completed slowly or with avoidable errors |
| **4 — Minor** | Cosmetic or preference |

A single severity 1 finding blocks the Definition of Done for the step that produced it.

---

## 7. Schedule (planned, not scheduled)

| Round | Surface | Runs alongside | Status |
|---|---|---|---|
| R1 | Ops Android POS and payment | Step 5 | **NOT STARTED** |
| R2 | Ops Android production and QC | Step 6 | **NOT STARTED** |
| R3 | Tracking portal | Step 7 | **NOT STARTED** |
| R4 | Courier and guest link | Step 8 | **NOT STARTED** |
| R5 | Unclaimed laundry dashboard | Step 9 | **NOT STARTED** |
| R6 | Console Web reporting and portfolio | Step 10 | **NOT STARTED** |
| R7 | Customer Android | Step 11 | **NOT STARTED** |
| R8 | Accessibility verification across all surfaces | Step 13 | **NOT STARTED** |

Round 8 is where the accessibility claim is finally tested. Until then, and in every document in this
repository, the posture is stated as: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
RUNTIME-TESTED.**

---

## 8. Reporting rules

1. A finding is reported with its severity, the task, the number of participants affected, and the
   proposed change. Nothing is rounded up to sound better.
2. **A usability session is never reported as a passed test, as UAT, or as evidence that a
   requirement is met.**
3. Any result committed to this repository is bound to an **exact commit SHA** of the artefact tested,
   with the command or protocol, the date in Asia/Jakarta, and the environment.
4. Findings are sanitised before commit and say that sanitisation occurred.
5. `GO` is never written by a designer, a researcher, or an agent. `GO` is conferred by the repository
   owner.

---

## 9. Related documents

- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)
- [`./CRITICAL_JOURNEYS.md`](./CRITICAL_JOURNEYS.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_OPEN_QUESTIONS.md`](./UX_OPEN_QUESTIONS.md)

## 10. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| This test plan | **IN PROGRESS** (a plan, not a result) |
| Usability testing | **NOT STARTED** |
| Accessibility runtime testing | **NOT STARTED** |
| UAT | **NOT STARTED** |
| Application CI | **NOT APPLICABLE** |

`GO` is conferred by the repository owner and is never self-declared.
