# Aish Laundry App — Jobs To Be Done

**Document version: 1.0.0** · **Step: 1 — Product Requirement and Domain Model**
**Status of every capability described here: NOT IMPLEMENTED**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §2 (Vision), §8 (Product modules),
§22 (MVP). Subordinate to the Master Source.

Related: [`PERSONAS.md`](PERSONAS.md) · [`USER_JOURNEYS.md`](USER_JOURNEYS.md) ·
[`OPERATIONAL_JOURNEYS.md`](OPERATIONAL_JOURNEYS.md) ·
[`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md)

---

## 0. Method and notation

A job is written as: **When** [situation], **I want to** [motivation], **so I can** [expected outcome].

Each job carries:

| Field | Meaning |
| --- | --- |
| ID | `JTBD-###`, stable and never reused |
| Persona | The primary persona from [`PERSONAS.md`](PERSONAS.md) |
| Current workaround | What the person does today without the product |
| Job statement | The when / want / so-that formulation |
| Success looks like | The observable outcome |
| Failure looks like | The observable failure the product must prevent |
| Requirements | The FR / RPT / SUB identifiers that serve this job |
| Canonical Step | The locked roadmap Step that first delivers it |

Jobs are **not** requirements. They motivate requirements. The authoritative requirement text lives in
[`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md), and the full mapping lives in
[`REQUIREMENT_TRACEABILITY.md`](REQUIREMENT_TRACEABILITY.md).

Requirement identifiers in the `SEC`, `NFR`, `TEN`, `FIN`, `OFF`, `TRK`, `DEL`, `UCL`, and `NOT` series
are referenced by series name only in this document; they are defined in the security, quality, and
domain documents produced elsewhere in Step 1.

---

## 1. Customer jobs

| ID | Persona | Job statement |
| --- | --- | --- |
| JTBD-001 | Customer | **When** I have handed over my laundry, **I want to** see its current status from a link in my phone's browser, **so I can** stop wondering and stop phoning the outlet. |
| JTBD-002 | Customer | **When** I want to know what I owe, **I want to** see the amount due and the payment state on the same screen as the status, **so I can** bring the right money or pay before I arrive. |
| JTBD-003 | Customer | **When** I cannot collect my laundry myself, **I want to** forward the tracking link to a family member, **so I can** let them collect on my behalf without a dispute at the counter. |
| JTBD-004 | Customer | **When** I do not want to travel to the outlet, **I want to** request a pickup with a time window, **so I can** hand over laundry without changing my day. |
| JTBD-005 | Customer | **When** my laundry is ready and I have forgotten it, **I want to** receive a useful reminder at a civilised hour, **so I can** collect it before it becomes a problem for both of us. |
| JTBD-006 | Customer | **When** I no longer want promotional messages, **I want to** opt out once and permanently, **so I can** keep receiving order updates without being marketed to. |
| JTBD-007 | Customer | **When** I collect or receive my laundry, **I want to** have a record that the handover happened, **so I can** resolve any later dispute with evidence rather than memory. |
| JTBD-008 | Corporate Customer Contact | **When** my organisation has several orders in flight, **I want to** see them together and reconcile them against one invoice, **so I can** close the month without chasing individual receipts. |
| JTBD-009 | Authorized Order Recipient | **When** I arrive to collect someone else's laundry, **I want to** be identified and recorded as the recipient, **so I can** collect without an argument and without the customer being exposed to risk. |

### Detail — JTBD-001, the founding job

| Field | Detail |
| --- | --- |
| Current workaround | The customer sends a WhatsApp message to the outlet, or telephones, and waits for a kasir who is serving another customer to answer. |
| Success looks like | The customer opens a link received over WhatsApp, in a browser, on a low-end phone, over a congested network, and sees the order number, brand and outlet, service type, current status and status history, estimated completion, amount due, and payment state. No account, no password, no installation. |
| Failure looks like | The link demands an application install; the page is slow on a cold cache; the page shows a full address; the link is guessable from the order number; the link works forever; a search engine indexes it. |
| Requirements | FR-086 … FR-092; the `TRK` series; the `SEC` series |
| Canonical Step | Step 7 |

This is the product's most visible differentiator
([DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md)) and its most exposed
attack surface ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §9). The Customer Android application
enhances this job; it never becomes a prerequisite for it
([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).

### Detail — JTBD-005, the reminder job

| Field | Detail |
| --- | --- |
| Current workaround | Nothing, or an ad-hoc WhatsApp message from a kasir who happened to notice the shelf filling up. |
| Success looks like | A friendly reminder at H+1, a second at H+3, a priority reminder at H+7, each sent exactly once, never inside quiet hours of 20.00–08.00 outlet local time, and never to a customer who opted out of marketing where the message is marketing. |
| Failure looks like | The same reminder arriving twice after a queue replay; a reminder at 23.00; the aging clock restarting because the order went back through rework; any automated suggestion to dispose of the laundry. |
| Requirements | FR-112 … FR-117; the `UCL` series; the `NOT` series |
| Canonical Step | Step 9 |

**Aging starts when an order FIRST reaches `READY_FOR_PICKUP` and never restarts**
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.1). The product never automatically discards, sells, or
transfers ownership of laundry, at any age, for any balance, under any configuration (§11.4).

---

## 2. Shop-floor jobs

| ID | Persona | Job statement |
| --- | --- | --- |
| JTBD-010 | Cashier | **When** a customer is at the counter during the evening rush, **I want to** create a priced order in the fewest possible taps, **so I can** keep the queue moving. |
| JTBD-011 | Cashier | **When** the network drops mid-transaction, **I want to** keep taking orders and payments with a visible pending state, **so I can** serve customers without pretending money is settled when it is not. |
| JTBD-012 | Cashier | **When** a customer asks the price of an unusual item, **I want to** find it in the price list immediately, **so I can** quote without guessing. |
| JTBD-013 | Cashier | **When** a customer arrives to collect, **I want to** find their order by number, phone, or name and record who received it, **so I can** hand over confidently. |
| JTBD-014 | Cashier | **When** I finish an order intake, **I want to** send the tracking link automatically, **so I can** stop answering status questions later. |
| JTBD-015 | Production Operator | **When** I finish a stage on a batch, **I want to** record it at the machine in one action, **so I can** keep the queue honest without carrying paper. |
| JTBD-016 | Production Operator | **When** an item is damaged or missing, **I want to** flag it against the specific order immediately, **so I can** stop the problem reaching the customer unannounced. |
| JTBD-017 | Quality Control | **When** finished work fails inspection, **I want to** send it to `REWORK` with a recorded reason, **so I can** fix it without the aging clock resetting and without hiding the defect. |
| JTBD-018 | Outlet Manager | **When** my shift ends, **I want to** compare expected cash against actual cash and record any variance with a reason, **so I can** close honestly rather than absorb the difference. |
| JTBD-019 | Outlet Manager | **When** a refund or void is genuinely necessary, **I want to** perform it under an explicit permission with a recorded reason, **so I can** correct the record without destroying it. |
| JTBD-020 | Outlet Manager | **When** a sync conflict appears on a payment, **I want to** see both values and decide, **so I can** resolve money questions as a human rather than letting software pick a winner. |

### Detail — JTBD-010, the order-intake job

| Field | Detail |
| --- | --- |
| Current workaround | A handwritten nota, a price recalled from memory, a carbon copy that fades. |
| Success looks like | The kasir selects a customer or creates one, adds kiloan or satuan lines, sees the total computed server-side in integer Rupiah, takes payment or records a deposit, produces a nota, and the tracking link is sent — with the primary action always the shortest path on the screen. |
| Failure looks like | More taps than the paper nota required; a total computed only on the client and trusted; a floating-point value anywhere in the money path; a duplicate order created by a double tap. |
| Requirements | FR-048 … FR-060; FR-061 … FR-070; the `FIN` series; the `OFF` series |
| Canonical Step | Step 5 |

### Detail — JTBD-011, the offline job

| Field | Detail |
| --- | --- |
| Current workaround | The shop stops using software and reverts to paper, then never reconciles the paper. |
| Success looks like | Every important operation carries a `client_reference` generated once and reused on every retry; the queue survives app kill and device reboot; pending versus synced state is visible at all times; a retry produces exactly one order and exactly one payment. |
| Failure looks like | A duplicate payment created by a retry — an automatic NO-GO under §16.6; a queued financial operation cleared by a "clear cache" action; a payment conflict resolved silently; cached data from the previous tenant visible after a tenant switch. |
| Requirements | FR-048 … FR-070 in their offline aspects; the `OFF` series; the `FIN` series |
| Canonical Step | Step 5 for the contract, Step 6 for the Ops Android offline implementation |

Payment gateway confirmation and OTP verification require the network and are honestly excluded from
offline capability ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §13.3).

---

## 3. Delivery and courier jobs

| ID | Persona | Job statement |
| --- | --- | --- |
| JTBD-021 | Courier Internal | **When** I start my route, **I want to** see my assigned jobs in a sensible order, one at a time, **so I can** work through them without reading a dashboard on a motorcycle. |
| JTBD-022 | Courier Internal | **When** I hand over or take custody of laundry, **I want to** capture the proof the tenant requires — OTP, photo, signature, or recipient name — **so I can** protect myself and the customer from a later dispute. |
| JTBD-023 | Courier Internal | **When** I collect cash at the door, **I want to** record it immediately even with no signal, **so I can** hand over an amount that matches what the system expects. |
| JTBD-024 | Courier Internal | **When** a delivery fails because nobody is home, **I want to** record the failure with a reason and return the laundry to a defined status, **so I can** treat it as an outcome rather than leave the order in limbo. |
| JTBD-025 | Outlet Manager | **When** demand exceeds my own couriers, **I want to** send an external ojek a link that covers exactly one job, **so I can** use outside capacity without exposing my customers or my business. |
| JTBD-026 | External Local Courier | **When** I am given one delivery job, **I want to** open a link in my browser, see the job, and capture proof, **so I can** complete it without installing anything or creating an account. |
| JTBD-027 | Finance | **When** a courier's route ends, **I want to** reconcile cash collected against cash handed over, **so I can** see any variance explicitly instead of discovering it a week later. |

### Detail — JTBD-021, honest routing

The product provides **simple route ordering** and **route suggestion**. It is forbidden to present a
suggestion as a mathematically optimal route — the product never claims one — and it is forbidden to
promise a guaranteed arrival time, which the product never gives
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §10.1, §10.2 rule 4, §23 non-goal 7). Copy in every surface
uses *usulan rute* semantics. The customer is given a **time window**, never a fictitious exact minute
(§10.1). Missing a window is measurable and is measured (§29.1).

| Field | Detail |
| --- | --- |
| Requirements | FR-100 … FR-111; the `DEL` series |
| Canonical Step | Step 8 |

### Detail — JTBD-025 and JTBD-026, the guest link

The guest job link is a temporary, minimum-privilege credential: high-entropy, stored hashed, expiring,
revocable, tenant-scoped, and scoped to exactly one job. It is never the order number and is never
derivable from it. A rider working for two tenants receives two unrelated links with no path between
them ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §10.2 rule 3, §4.2). The detailed security properties
belong to the `DEL` and `SEC` series.

---

## 4. Owner and finance jobs

| ID | Persona | Job statement |
| --- | --- | --- |
| JTBD-028 | Tenant Owner | **When** I open the product in the morning, **I want to** see yesterday's revenue, orders, cash position, and receivables across every brand and outlet in this tenant, **so I can** decide where to spend my attention today. |
| JTBD-029 | Tenant Owner | **When** a consolidated number surprises me, **I want to** drill from the aggregate to the underlying records, **so I can** verify rather than trust. |
| JTBD-030 | Tenant Owner | **When** laundry has been sitting unclaimed for two weeks, **I want to** be escalated to personally, **so I can** act while the money is still recoverable. |
| JTBD-031 | Tenant Owner | **When** I open a second outlet or a second brand, **I want to** add it inside the same tenant without re-implementing anything, **so I can** grow without switching software. |
| JTBD-032 | Finance | **When** I close a period, **I want to** reconcile every payment channel — cash, transfer, gateway, courier cash — against one authoritative record, **so I can** produce a number I can defend. |
| JTBD-033 | Finance | **When** a figure was wrong, **I want to** post a reversal or adjustment that preserves the original, **so I can** correct the books without rewriting history. |
| JTBD-034 | Tenant Admin | **When** I change a price, **I want to** be certain that past orders and past nota are unaffected, **so I can** update the price list without corrupting the record. |
| JTBD-035 | Tenant Admin | **When** a staff member leaves, **I want to** revoke their access and their device immediately, **so I can** close the exposure the same day. |

### Detail — JTBD-028, the portfolio job

Consolidation happens **within a single tenant**, across that tenant's brands and outlets. An owner who
owns multiple tenants switches tenants; consolidation is never achieved by weakening isolation
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §12.2, hard rule 13). Every figure derives from the same
system of record operations use; an estimate is labelled an estimate; a figure that cannot be computed is
shown as unavailable, never as zero (§12.4).

| Field | Detail |
| --- | --- |
| Requirements | RPT-001 … RPT-020 |
| Canonical Step | Step 10 |

---

## 5. Platform and commercial jobs

| ID | Persona | Job statement |
| --- | --- | --- |
| JTBD-036 | Tenant Owner | **When** I am evaluating the product, **I want to** run a real working day for 14 hari gratis, **so I can** decide with evidence rather than a demo. |
| JTBD-037 | Tenant Owner | **When** my business grows past a plan's limits, **I want to** be told honestly and offered the right plan, **so I can** upgrade deliberately rather than be cut off mid-shift. |
| JTBD-038 | Tenant Owner | **When** my subscription lapses, **I want to** still be able to export my own business data per policy, **so I can** leave without losing my records. |
| JTBD-039 | Tenant Owner | **When** I use WhatsApp heavily, **I want to** see what the messaging actually costs, **so I can** budget instead of being surprised. |
| JTBD-040 | Platform Super Admin | **When** I onboard or suspend a tenant, **I want to** do it through an audited platform surface, **so I can** operate the platform without ever touching tenant business data silently. |
| JTBD-041 | Platform Support | **When** a tenant reports a problem I cannot reproduce, **I want to** enter a time-bound, audited impersonation session with a recorded reason, **so I can** help without silent access. |

### Detail — JTBD-037 and JTBD-038, commercial honesty

Fair-use ceilings trigger a conversation and a plan recommendation. Exceeding a ceiling does **not**
silently degrade the service, does **not** delete data, and does **not** stop a laundry operating
mid-shift ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §21.5). A lapsed subscription restricts features;
it never holds a tenant's business records hostage (§17.3, §21.4 guardrail 9). There is no lifetime cloud
plan ([DEC-0010](../decisions/DEC-0010-no-lifetime-cloud-subscription.md)) and there is no promise of
"unlimited WhatsApp" (§14.1 rule 10).

| Field | Detail |
| --- | --- |
| Requirements | SUB-001 … SUB-020 |
| Canonical Step | Step 12 |

---

## 6. Job-to-Step summary

| Job range | Theme | Canonical Steps |
| --- | --- | --- |
| JTBD-001 … JTBD-009 | Customer visibility, collection, and consent | Steps 7, 8, 9, 11 |
| JTBD-010 … JTBD-020 | Shop floor: intake, production, quality, cash | Steps 5, 6 |
| JTBD-021 … JTBD-027 | Pickup, delivery, proof, courier cash | Step 8 |
| JTBD-028 … JTBD-035 | Owner portfolio, finance, master data control | Steps 3, 4, 10 |
| JTBD-036 … JTBD-041 | Subscription, fair use, platform operation | Step 12 |

Jobs that the product deliberately does **not** serve are recorded as non-goals in
[`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) §8 and in
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §23. In particular the product never serves any job whose
outcome is the automatic disposal, sale, auction, donation, or transfer of a customer's laundry.

---

## 7. Status

Every job above is **NOT IMPLEMENTED**. No job has been validated with real users; user validation
belongs to **Step 14 — Pilot and Commercial Launch**. Nothing in this document may be read as evidence
that a job has been solved.
