# Aish Laundry App — Success Metrics

**Document version: 1.0.0** · **Step: 1 — Product Requirement and Domain Model**
**Measurement status: NOT STARTED. No baseline exists. No target exists. No metric has been measured.**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §29. Subordinate to the Master Source.

Related: [`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) · [`MVP_SCOPE.md`](MVP_SCOPE.md) ·
[`../ROADMAP.md`](../ROADMAP.md)

---

## 1. The rule that governs this entire document

**Metrics are stated as what WILL be measured, never as targets invented before any measurement exists.**

- **Baselines and targets are set at Step 14 — Pilot and Commercial Launch, with real pilot data.**
- No number in this document is an achieved result.
- No number in this document is a committed target.
- The "Target" column is deliberately absent from every table below. Adding one before Step 14 would be
  inventing a figure, which is forbidden ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §3.1, §19.3, §29).
- A metric that cannot be computed for a period is reported as **unavailable**, never as zero
  (RPT-003).
- An estimated figure is labelled an estimate (RPT-002).
- Every metric is **tenant-scoped**. Cross-tenant aggregation for platform reporting uses identifiers and
  counts, never tenant business content, and never weakens isolation.
- Every monetary metric is **integer Rupiah**, read from the authoritative financial records, never
  recomputed independently by the reporting layer.

**Verification status of every metric below: NOT STARTED.**

---

## 2. Metric record format

| Field | Meaning |
| --- | --- |
| ID | `M-###`, stable and never reused |
| Metric | What will be measured |
| Why it matters | The decision the metric informs |
| Definition | How it will be computed, precisely enough to be implementable |
| Source | Where the data comes from |
| Grain | The unit of aggregation |
| Instrumented in | The canonical Step that makes the measurement possible |
| Baseline | **Set at Step 14** |

---

## 3. Product health metrics

Derived from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §29.1.

| ID | Metric | Why it matters | Definition | Grain | Instrumented in |
| --- | --- | --- | --- | --- | --- |
| M-001 | Share of orders whose tracking link is opened by the customer | Whether the tracking differentiator actually lands | Orders with at least one portal open, divided by orders for which a tracking link was issued | Outlet, day | Step 7 |
| M-002 | Status enquiries handled per outlet per day | Whether tracking reduces manual work for kasir | Count of manually handled status enquiries recorded by staff, per outlet per day | Outlet, day | Step 7 |
| M-003 | Median age of laundry at collection | The core unclaimed-laundry outcome | Median elapsed time from the first `READY_FOR_PICKUP` timestamp to `COMPLETED` | Outlet, month | Step 9 |
| M-004 | Volume of laundry older than H+7 | Shelf space consumed by uncollected work | Count of orders whose aging exceeds 7 days and which have not reached `COMPLETED` | Outlet, day | Step 9 |
| M-005 | Value of laundry older than H+7 | Cash trapped on shelves | Sum of unpaid balance in integer Rupiah for the M-004 population, read from the authoritative financial records | Outlet, day | Step 9 |
| M-006 | Volume and value of laundry older than H+14 | The escalation population | As M-004 and M-005 with a 14-day threshold | Outlet, day | Step 9 |
| M-007 | Recovery rate after the H+1 reminder | Whether the first rung works | Orders reaching `COMPLETED` within a defined window after the H+1 send, divided by orders that received H+1 | Outlet, month | Step 9 |
| M-008 | Recovery rate after the H+3 reminder | Whether the second rung adds value | As M-007 for H+3 | Outlet, month | Step 9 |
| M-009 | Recovery rate after the H+7 reminder and follow-up task | Whether human follow-up beats messaging alone | As M-007 for H+7, reported alongside follow-up task closure rate | Outlet, month | Step 9 |
| M-010 | H+14 escalation resolution rate | Whether escalation reaches an accountable human who acts | Escalations resolved, divided by escalations raised | Outlet, month | Step 9 |
| M-011 | Distribution of recorded reasons not collected | The data that actually reduces the pile | Frequency distribution of the reason-not-collected field | Outlet, month | Step 9 |
| M-012 | Pickup and delivery time-window adherence | Whether the delivery promise is kept | Jobs completed inside the stated time window, divided by jobs with a stated window | Outlet, courier, month | Step 8 |
| M-013 | Proof completeness on custody transfer | Whether the mandatory-proof rule holds in practice | Custody transfers with valid recorded proof, divided by all custody transfers | Outlet, courier, month | Step 8 |
| M-014 | Failed delivery rate and reason distribution | Whether delivery failures are a routing problem, a scheduling problem, or a customer problem | Failed deliveries divided by attempted deliveries, with the reason distribution | Outlet, month | Step 8 |
| M-015 | Rework rate after quality control | Production quality | Orders entering `REWORK`, divided by orders entering `QUALITY_CONTROL` | Outlet, stage, month | Step 6 |

**Honesty note on M-012.** Time-window adherence is measured and reported. It is **not** presented as
evidence of route optimisation, and the product never claims an optimal route or a guaranteed arrival
time ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §10.2 rule 4, §23 non-goal 7).

**Honesty note on M-003 through M-011.** These metrics inform reminding, escalating, delivering, and
recording. They never inform, justify, or trigger disposal, sale, auction, donation, or transfer of a
customer's laundry, which is absolutely prohibited (§11.4).

---

## 4. Operational health metrics

Derived from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §29.2.

| ID | Metric | Why it matters | Definition | Grain | Instrumented in |
| --- | --- | --- | --- | --- | --- |
| M-016 | Offline queue backlog | Ops app reliability | Count and age of pending operations in the device queue, reported per device and aggregated per outlet | Outlet, day | Step 6 |
| M-017 | Sync failure rate | Whether offline work reaches the server | Failed sync attempts divided by sync attempts | Outlet, day | Step 6 |
| M-018 | Payment conflict count | How often local and server payment state disagree | Count of surfaced payment conflicts and their resolution latency | Outlet, month | Step 6 |
| M-019 | Duplicate payment suppression count | Financial integrity working as designed | Count of repeat submissions recognised by `client_reference` and answered with the original result | Tenant, month | Step 5 |
| M-020 | Duplicate payments created | The failure this product must never have | Count of distinct payments created from one logical payment. **Any non-zero value is a financial integrity failure and an automatic NO-GO.** | Tenant, month | Step 5 |
| M-021 | Shift cash variance distribution | Cash handling discipline | Distribution of expected minus actual cash at shift closing, in integer Rupiah, with reasons | Outlet, shift | Step 10 |
| M-022 | Courier cash outstanding | Money in transit | Sum of cash collected and not yet handed over, in integer Rupiah | Courier, day | Step 8 |
| M-023 | Courier cash reconciliation lag | How long money stays untraceable | Elapsed time from collection to reconciled handover | Courier, month | Step 8 |
| M-024 | Notification delivery success by provider | Channel reliability and cost | Successful sends divided by attempted sends, per provider | Tenant, provider, month | Step 7 |
| M-025 | Notification deduplication suppression count | Whether the dedup key holds under replay | Count of sends suppressed as duplicates | Tenant, month | Step 7 |
| M-026 | Quiet-hours deferral count | Whether quiet hours are enforced rather than assumed | Count of messages deferred out of the 20.00–08.00 outlet-local window | Outlet, month | Step 7 |
| M-027 | API error rate and latency percentiles | Platform health | Error rate and latency percentiles per endpoint class | Endpoint, day | Step 13 |
| M-028 | Authentication failure and lockout rates | Abuse and brute force | Failed authentications and lockouts, per endpoint | Day | Step 13 |
| M-029 | Queue depth and job failure rate | Notifications and reminders silently stopping | Depth and failure rate of background queues | Day | Step 13 |
| M-030 | Cross-tenant access denials | Whether isolation is being probed and holding | Count of requests denied because the target record belonged to another tenant | Day | Step 3 |

**M-020 and M-030 are not ordinary metrics.** M-020 must be zero; a non-zero value stops work and
triggers the financial integrity hard gate. M-030 is a **detection** signal, not a tolerance: a denial is
the system working, while an actual cross-tenant exposure is an automatic NO-GO regardless of count
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15.7, §16.6).

---

## 5. Commercial health metrics

Derived from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §29.3.

| ID | Metric | Why it matters | Definition | Grain | Instrumented in |
| --- | --- | --- | --- | --- | --- |
| M-031 | Trial-to-paid conversion after the 14-day trial | Whether the product proves itself in two weeks | Tenants converting to a paid plan, divided by tenants starting a trial of 14 hari gratis | Cohort, month | Step 12 |
| M-032 | Plan distribution across Starter, Growth, Scale, Enterprise | Whether the pricing ladder matches reality | Count of active tenants per plan | Month | Step 12 |
| M-033 | Tenant retention and voluntary churn reasons | Whether the product keeps earning its price | Retention by cohort, with recorded voluntary churn reasons | Cohort, month | Step 12 |
| M-034 | Outlets per tenant over time | Whether tenants grow inside the product | Mean and distribution of active outlets per tenant | Month | Step 12 |
| M-035 | Fair-use ceiling approach rate | Whether the plan ladder is priced against real volume | Tenants exceeding a defined proportion of their fair-use order ceiling | Month | Step 12 |
| M-036 | WhatsApp cost per active outlet | Whether transparent messaging cost stays sustainable | Third-party messaging cost in integer Rupiah divided by active outlets, reported separately from subscription revenue | Outlet, month | Step 12 |
| M-037 | Data export requests after lapse | Whether the no-hostage commitment is exercised and honoured | Count of export requests from lapsed tenants and their fulfilment rate | Month | Step 12 |

**Constraint on M-035 and M-036.** Fair-use ceilings trigger a conversation and a plan recommendation.
They never silently degrade the service, delete data, or stop a laundry operating mid-shift (SUB-006).
Messaging cost is billed and reported separately from the subscription plan, and no surface claims
unlimited messaging of any kind (SUB-019, SUB-020).

---

## 6. Non-metrics

The product deliberately does **not** optimise for, and does not report as success
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §29.4):

1. **Time in application.** Time spent in the Ops app is a cost to a busy kasir, not a benefit.
2. **Notification volume.** More messages is not better; customers own their attention.
3. **Customer application installs.** Installing the Customer Android app is **never** a success metric
   that could justify degrading the Portal Tracking Publik
   ([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).
4. **Raw order count as a proxy for tenant health.** Volume without collection and without payment is not
   health; that is precisely what the unclaimed-laundry metrics exist to expose.

Any proposal to add one of these as a success metric requires an owner decision record.

---

## 7. Measurement governance

1. **Every metric is derived from the same system of record that operations use.** There is no separate
   reporting truth (RPT-001).
2. **Telemetry carries tenant context as an identifier, never as personal data**
   ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §20.2 rule 3). Telemetry is not a bypass around tenant
   isolation or privacy.
3. **Metric definitions change only through a documented change**, because a silently redefined metric is
   a falsified trend.
4. **A metric reported to a tenant carries the same access rules and masking as the underlying records.**
5. **No metric value may be published, reported, or claimed without exact-SHA evidence** of the run that
   produced it ([DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md)).
6. **Alerting is on symptoms customers feel** — failed payments, undelivered notifications, queue
   backlog, error-rate spikes — not on every internal fluctuation. An alert nobody acts on is deleted
   (§20.2 rules 7 and 8).

---

## 8. Baseline and target schedule

| Activity | Canonical Step | Status |
| --- | --- | --- |
| Metric definitions recorded | Step 1 | This document |
| Instrumentation for tenancy and access metrics | Step 3 | PLANNED |
| Instrumentation for financial metrics | Step 5 | PLANNED |
| Instrumentation for production and offline metrics | Step 6 | PLANNED |
| Instrumentation for tracking and notification metrics | Step 7 | PLANNED |
| Instrumentation for delivery and courier metrics | Step 8 | PLANNED |
| Instrumentation for unclaimed-laundry metrics | Step 9 | PLANNED |
| Instrumentation for finance and portfolio reporting | Step 10 | PLANNED |
| Instrumentation for commercial metrics | Step 12 | PLANNED |
| Instrumentation for platform health metrics | Step 13 | PLANNED |
| **Baselines and targets set from real pilot data** | **Step 14** | **NOT STARTED** |

---

## 9. Status

| Item | Status |
| --- | --- |
| Metric definitions | Recorded in Step 1 |
| Instrumentation | **NOT IMPLEMENTED** |
| Measurement | **NOT STARTED** |
| Baselines | **NOT STARTED** — set at Step 14 |
| Targets | **NOT STARTED** — set at Step 14 |
| UAT | **NOT STARTED** |

No metric in this document has been measured. No baseline exists. No target exists. Nothing here is a
claim that any outcome has been achieved.
