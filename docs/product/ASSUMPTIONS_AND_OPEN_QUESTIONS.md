# Aish Laundry App — Assumptions and Open Questions

**Document version: 1.0.0** · **Step: 1 — Product Requirement and Domain Model**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md). Repository-level assumptions and
amendments live in [`../ASSUMPTIONS.md`](../ASSUMPTIONS.md); this document holds only the product-level
assumptions and open questions raised by Step 1.

Related: [`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) · [`PERSONAS.md`](PERSONAS.md) ·
[`MVP_SCOPE.md`](MVP_SCOPE.md)

---

## 0. The rule this document exists to enforce

**An agent never invents a product decision** ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §27.4 rule
14, §1.3 rule 7). When a question is not answered by the Master Source, by an accepted decision record,
or by `CLAUDE.md`, it is an **open question for the repository owner** — not a licence to choose.

Two consequences:

1. **An assumption is not a decision.** Nothing in §2 may be cited as authority. An assumption that later
   proves wrong invalidates the requirements built on it, and those requirements must be revised, not
   defended.
2. **An open question is not a placeholder to be quietly filled.** Placeholders get read as decisions. No
   requirement in [`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) assumes an answer to any open
   question in §3.

Resolving an open question requires an owner decision and, where it changes a product decision, a
decision record under [`../decisions/`](../decisions/DEC-0001-official-product-name.md) plus a Master
Source version bump and checksum refresh (§1.2).

---

## 1. What is already decided and is NOT an open question

Recorded here so that no future reader mistakes settled ground for an open gap. Each of these is locked
and may be changed only by the owner through a decision record.

| Settled | Authority |
| --- | --- |
| Product name **Aish Laundry App**; owner Aish Tech Solution | [DEC-0001](../decisions/DEC-0001-official-product-name.md) |
| Multi-tenant architecture and the thirteen tenancy hard rules | [DEC-0002](../decisions/DEC-0002-multi-tenant-architecture.md), [DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) |
| One owner may own or manage multiple tenants | [DEC-0003](../decisions/DEC-0003-multi-laundry-owner-model.md) |
| Flutter clients and Flutter Web console | [DEC-0004](../decisions/DEC-0004-flutter-client-and-web-console.md) |
| Laravel API-first modular monolith, PostgreSQL, Redis, S3-compatible storage, `/api/v1` | [DEC-0005](../decisions/DEC-0005-api-first-modular-monolith-backend.md) |
| Public tracking without app installation | [DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md) |
| Pickup and delivery as a core product | [DEC-0007](../decisions/DEC-0007-pickup-and-delivery-as-core-product.md) |
| The H+1 / H+3 / H+7 / H+14 ladder as a core product | [DEC-0008](../decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md) |
| The pricing table, character for character | [DEC-0009](../decisions/DEC-0009-initial-commercial-pricing.md) |
| No lifetime cloud subscription | [DEC-0010](../decisions/DEC-0010-no-lifetime-cloud-subscription.md) |
| Transparent third-party messaging costs | [DEC-0011](../decisions/DEC-0011-transparent-third-party-messaging-costs.md) |
| Exact-SHA evidence before GO | [DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md) |
| The Customer Android app never replaces the public tracking portal | [DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md) |
| The MVP focuses on laundry operations | [DEC-0015](../decisions/DEC-0015-mvp-focuses-on-laundry-operations.md) |
| Repository visibility is PUBLIC as an accepted deviation from a canonical desired PRIVATE | [DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md) |
| The fifteen canonical order statuses | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §8, §11.1 |
| Quiet hours default 20.00–08.00 outlet local time | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §14.1 rule 6 |
| Aging anchored to the **first** `READY_FOR_PICKUP` and never restarting | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.1 |
| The absolute prohibition on automatic disposal of laundry | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.4, §23 |
| The locked roadmap, Steps 0–14 | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §24 |

---

## 2. Product assumptions

Format: `PA-###`. **An assumption is not a decision.** Each carries the risk if it is wrong and the Step
at which it will be validated.

| ID | Assumption | Basis | Risk if wrong | Validated at |
| --- | --- | --- | --- | --- |
| PA-001 | The baseline device is a low-end to mid-range Android phone on a congested mobile network, and this is the normal case rather than an edge case. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §19.1 | Performance budgets set in Step 13 would be calibrated against the wrong device class. | Step 13, Step 14 |
| PA-002 | WhatsApp remains the channel Indonesian laundry customers actually read, for the life of the current roadmap. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §14 | Notification reach falls; the provider abstraction (FR-093) limits the blast radius to an adapter change. | Step 14 |
| PA-003 | Bahasa Indonesia alone is sufficient as the product's UI language for the current market. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §1.6 | A multilingual requirement would arrive after copy is written across every surface. | Step 14 |
| PA-004 | Tenants are willing to configure their own proof requirements for pickup and delivery rather than accept a single imposed policy. | Rule derived from §10.1 proof mechanisms being plural | Configuration effort becomes an onboarding obstacle; a sensible default would be needed. | Step 8 |
| PA-005 | Kiloan and satuan, with packages and add-ons, cover the service shapes Indonesian laundry UMKM actually sell. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §8 | The service catalog model (FR-031 … FR-033) would need extension before Step 5 depends on it. | Step 4 |
| PA-006 | A staff member has one phone and one account, and shared counter devices are used by several accounts in sequence rather than concurrently. | Derived from FR-002 and §4.1 | Device-level session handling on shared hardware becomes more complex than FR-004 anticipates. | Step 3 |
| PA-007 | Outlet local time is a sufficient basis for quiet hours and business-day logic, and no tenant operates across time zones within one outlet. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §6.5, §14.1 | Aging and quiet-hours computation would need a richer time model. | Step 7, Step 9 |
| PA-008 | Couriers will accept capturing proof on every custody transfer, given a sufficiently simple interface. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §18.2 rule 7 | Proof capture is skipped in practice, defeating FR-104; the interface, not the rule, would need to change. | Step 8, Step 14 |
| PA-009 | External local couriers will open a browser link for a single job and will not require an application. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §10.1 | The external-ojek capability underperforms; the rule that they never receive an account does not change. | Step 8 |
| PA-010 | Repository facts hold: remote repository `aish_laundry_app`, default branch `main`, local monorepo root `aish_laundry`, visibility **PUBLIC**. | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15.8, [`../ASSUMPTIONS.md`](../ASSUMPTIONS.md) AMENDMENT-0001 | Governance and CI configuration would be misdirected. | Continuously |

---

## 3. Open questions for the repository owner

Format: `OQ-###`. Each is an **owner decision**. **No requirement in Step 1 assumes an answer to any of
these**, and none has been closed by invention.

| ID | Open question | Why it matters | Blocks | Constraint on any answer |
| --- | --- | --- | --- | --- |
| OQ-001 | Does the product support **partial payment beyond a single deposit** — that is, multiple part-payments against one order before completion? | FR-056 records a deposit and FR-070 tracks the balance, but a multi-instalment model would change the payment and receivable design. | Step 5 detail | Any answer must preserve integer Rupiah, idempotency, and no-hard-delete (§16). |
| OQ-002 | Is there a **credit or invoice-terms** relationship for high-volume customers, where laundry is released before payment against agreed terms? | Changes the receivables model and the collection ladder. | Step 5, Step 10 | Any answer must keep unpaid balance read from the authoritative financial records, never recomputed. |
| OQ-003 | May a tenant **configure a shorter production stage sequence** — for example skipping `SORTING` for a satuan order — or is the stage sequence uniform for every order? | FR-071 fixes the fifteen statuses, but whether every order traverses every production stage is not stated canonically. | Step 6 | The fifteen canonical statuses are locked; no answer may add, remove, or rename one. |
| OQ-004 | Is **quality control mandatory for every order**, or is it a tenant-configurable gate? | FR-081 currently says "where the tenant's policy requires it", which presumes configurability that is not canonically stated. | Step 6 | Any answer must preserve the immutability of the first `READY_FOR_PICKUP` timestamp. |
| OQ-005 | Is a **Corporate Customer Contact** a distinct entity with consolidated invoicing, or a Customer profile with additional attributes? | Persona P-13 and journey UJ-007 exist; the underlying data model does not. | Step 4, Step 11 | Any answer must keep customer profiles tenant-scoped and never merged across tenants (FR-022). |
| OQ-006 | Must an **Authorized Order Recipient** be pre-nominated by the customer, may they be recorded at the counter at handover, or both? | Persona P-14 and journeys UJ-003, UJ-004 depend on it. | Step 5, Step 8 | Any answer must record who received the goods; an unrecorded handover is not acceptable. |
| OQ-007 | What is the **default expiry period** for a tracking token, and for a guest job link? | FR-088 and FR-109 require expiry; the duration is not canonically stated. | Step 7, Step 8 | Any answer must keep tokens revocable and stored hashed; "never expires" is not a permissible answer. |
| OQ-008 | What is the **cash variance threshold** above which a shift-closing reason is mandatory, and is it tenant-configurable? | §16.5 rule 10 requires a "configured threshold" without naming a value. | Step 10 | Any variance is recorded regardless; the threshold governs only when a reason becomes mandatory. |
| OQ-009 | Are **transactional messages subject to quiet hours without exception**, or is there a defined critical-message exception path? | §14.1 rule 6 defers non-urgent messages; §11.2 says reminders respect quiet hours; whether any message class is exempt is not stated. | Step 7, Step 9 | Absent an explicit decision record granting an exception, quiet hours apply to everything. |
| OQ-010 | What **retention period** applies to proof artefacts — delivery photographs and signatures — before they are purged? | §17.3 ties retention to tenant legal and tax obligations without naming a period for proof media. | Step 8, Step 13 | Any answer must keep artefacts private, signed-URL only, and tenant-scoped for their whole life. |
| OQ-011 | Does an **Enterprise plan** have defined limits, or are limits negotiated per contract? | SUB-003 and SUB-004 enforce limits server-side; Enterprise is canonically "mulai Rp999.000/bulan — negotiated". | Step 12 | No negotiated arrangement may breach the pricing guardrails; Enterprise is not a guardrail exemption. |
| OQ-012 | What **grace period** applies between a subscription lapsing and feature restriction taking effect? | SUB-015 requires lapse and grace behaviour to be defined before billing ships. | Step 12 | Export of the tenant's own data per policy must survive any answer (SUB-014). |
| OQ-013 | Is **loyalty** a points model, a Rupiah-denominated deposit or credit model, or both? | FR-120 is a `COULD` and deliberately vague because the model is undecided. | Step 11 | Any monetary component is integer Rupiah and inherits every rule in §16. |
| OQ-014 | Which **web stack** is chosen for the Portal Tracking Publik, given that Flutter is explicitly not mandatory there? | §5.4 permits a lighter stack if it performs materially better on low-end Android browsers. | Step 7 | The choice is recorded in a decision record in the Step that builds it; performance for this surface outranks stack uniformity. |
| OQ-015 | Which **payment gateway** or gateways are integrated? | FR-063 requires server-side callback verification against a specific gateway's contract. | Step 5 | Introducing any third-party provider requires owner approval; none is introduced in Step 1. |
| OQ-016 | Which **official WhatsApp Business API provider** is used as the automated path? | FR-094 requires an official provider; FR-093 keeps the choice behind an abstraction. | Step 7 | The abstraction must hold regardless of the answer; no vendor specifics may leak into business logic. |
| OQ-017 | Which **Rupiah rounding mode** applies when an order line produces a fractional Rupiah (a per-kilogram price × a fractional weight)? | FR-038 requires the rounding rule to be explicit and applied at one defined point; neither the Master Source nor FR-038 names a canonical mode. | Step 5 | The mode is a single, named, changeable constant (`OrderPricing::ROUNDING_MODE`), applied only through `RupiahRounding`; changing it is a one-line edit plus a decision record. **Step 5 foundation provisionally uses `HALF_UP`** (conventional Indonesian retail rounding), flagged pending owner ratification — it is not presented as settled. |

---

## 4. Questions that are deliberately NOT open

Recorded so that they are never re-litigated as "gaps".

| Non-question | Why it is closed |
| --- | --- |
| Whether laundry may be automatically discarded, sold, auctioned, donated, or transferred after some age or balance | **Absolutely prohibited.** Not behind a flag, not as a prototype, not as a TODO ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.4, §23 non-goal 9). |
| Whether the product may claim an optimal route or a guaranteed arrival time | Forbidden. Route *suggestion* only — *usulan rute* (§10.2 rule 4, §23 non-goal 7). |
| Whether "unlimited WhatsApp" may be offered | Forbidden as a false claim (§14.1 rule 10, SUB-020). |
| Whether a lifetime cloud plan may be offered | Forbidden ([DEC-0010](../decisions/DEC-0010-no-lifetime-cloud-subscription.md)). |
| Whether tenant isolation, security controls, or backup may sit behind a paid tier | Forbidden (§21.4 guardrails 4, 5, 6; SUB-018). |
| Whether the Customer Android app may become required for tracking | Forbidden ([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)). |
| Whether cross-tenant consolidation may be built by widening the query surface | Forbidden; hard rule 13 (§12.2). |
| Whether platform support may access tenant data without an audit record | Forbidden; automatic NO-GO (§15.5). |
| Whether floating point may be used "just for display" in a money path | Forbidden; no "it works in practice" exemption (§16.1). |
| Whether pricing figures may be rounded, reformatted, or restated from memory | Forbidden; reproduce exactly ([DEC-0009](../decisions/DEC-0009-initial-commercial-pricing.md), §33.2 rule 12). |
| Whether the roadmap may be renumbered, merged, or split without a decision record | Forbidden (§1.4, §24). |

---

## 5. Escalation protocol

When an unanswered question is encountered during a later Step:

1. **Stop.** Do not choose an answer to keep moving.
2. **Record it here** as a new `OQ-###`, with why it matters, what it blocks, and the constraints any
   answer must satisfy.
3. **Report it to the repository owner**, plainly, in a place the owner cannot miss
   ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §33.3 rule 17).
4. **Wait for the owner's decision.** If it changes a product decision, it becomes a decision record.
5. **Never** leave an invented answer in place "as a placeholder".

---

## 6. Status

| Item | Status |
| --- | --- |
| Product assumptions recorded | PA-001 … PA-010 |
| Open questions recorded | OQ-001 … OQ-017 |
| Open questions resolved | **0** — all seventeen await an owner decision |
| Requirements depending on an unresolved open question | **0 hard** — none assumes a settled answer. **1 provisional**: the Step 5 order-total rounding (FR-038/FR-051) uses a flagged, single-point `HALF_UP` default under OQ-017, changeable at one line pending owner ratification. |

No assumption in this document is a decision. No open question in this document has been closed by
invention. OQ-017's provisional `HALF_UP` is an explicitly-flagged foundation default, isolated to one
named constant, not a settled product decision — it is surfaced for the owner to ratify or change.
