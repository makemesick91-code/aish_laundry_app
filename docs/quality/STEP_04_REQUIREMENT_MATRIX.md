# Step 4 — Laundry Master Data: Requirement Matrix and Acceptance Criteria

**Step:** 4 — Laundry Master Data
**Status:** `IN PROGRESS` — merge-ready handoff; `GO` is the owner's to confer
**Authorized by:** [DEC-0028](../decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md)
**Runtime scope opened by:** [DEC-0030](../decisions/DEC-0030-step-04-runtime-scope-transition.md)
**Master Source version:** 1.4.3
**Baseline SHA:** `1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`
**Closure evidence:** [`evidence/step-04/`](../../evidence/step-04/) — bound to the final candidate SHA recorded in that pack's README.
**Independent review closure:** [DEC-0033](../decisions/DEC-0033-step-04-independent-review-closure.md)

---

## 1. How to read this document

This is the Phase 0 requirement matrix for Step 4. It maps every canonical Step 4 requirement to the
mechanism that will satisfy it, the verification that will prove it, and the evidence that must exist
before the claim may be made.

**Nothing in this document is evidence.** A row saying a requirement will be verified by a test is a
plan, not a result. Only captured output bound to an exact 40-character commit SHA proves anything
(Rule 01, DEC-0013). Every `Status` entry below now cites the executed verification that moved it,
and the evidence path is in [`evidence/step-04/`](../../evidence/step-04/).

**The status vocabulary here is deliberately narrow.** `COMPLETE_AND_VERIFIED` means Step 4's
obligation for that requirement is finished AND evidenced. `PARTIAL_STEP_4_FOUNDATION_COMPLETE /
STEP_5_E2E_PENDING` means Step 4 built everything it was asked to and the requirement's END-TO-END
proof needs a Step 5 surface that does not exist yet. **A generic "complete" is never used for the
second case**, because a reader skimming for what is finished would take it as finished — and for
FR-036 that reader would be wrong about a financial-integrity obligation.

**No requirement is invented here.** Step 4's requirement set is **FR-021 … FR-047**, fixed in
[`PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md) §15.3–§15.5 and confirmed by its own
traceability table (`Customers, services, price lists, outlet master data | Step 4 | FR-021 … FR-047`).
A requirement that appears only in this matrix does not exist (Rule 16).

### A note on "staff and role assignment"

[`ROADMAP.md`](../ROADMAP.md) lists "Staff and role assignment within a tenant" in its Step 4 scope
summary, but the PRD assigns no FR- identifier in the FR-021 … FR-047 range to it. The PRD is the
requirement baseline (Rule 16), and it places role scoping at **FR-018 (Step 3)**, which Step 3 already
delivered as membership, role, permission, and brand/outlet scoping runtime.

What remains for Step 4 is therefore **assignment of staff to the outlet master data Step 4 creates** —
it is delivered as part of FR-041 … FR-047 outlet master data, not as new authorization machinery. Step
4 introduces **no second authorization system** and no new role or permission model (Rule 40). This is
recorded here rather than resolved silently, and it is not a licence to invent an FR- identifier.

---

## 2. Requirement matrix

Legend — **Pri**: MUST / SHOULD, as the PRD states it. **Status**: one of
`COMPLETE_AND_VERIFIED`, `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING`,
`NOT_APPLICABLE`, `NOT_PERFORMED`, `FAILED` — never a generic "complete".

### 2.1 Customer master data — FR-021 … FR-030

| ID | Requirement | Pri | Mechanism | Verification | Status |
|---|---|---|---|---|---|
| FR-021 | Customer profile, tenant-scoped | MUST | `customers` table with `tenant_id` from its introducing migration; Eloquent model with default tenant scope | Feature test: create, read, update; migration asserts `tenant_id` NOT NULL | `COMPLETE_AND_VERIFIED` |
| FR-022 | Same phone in two tenants = two unrelated profiles | MUST | Uniqueness is `(tenant_id, phone_normalized)`, never global; no cross-tenant lookup path exists | Negative test: identical phone seeded in tenant A and B; assert two distinct rows, never merged or cross-referenced | `COMPLETE_AND_VERIFIED` |
| FR-023 | Customer search by phone, name, or order number | MUST | Tenant-scoped, permission-gated, bounded query | Feature test + **tenant-isolation negative test on the search path specifically** (Rule 48 hard rule 3) | `COMPLETE_AND_VERIFIED` |
| FR-024 | Multiple saved addresses per customer | MUST | `customer_addresses` table, tenant-scoped, activate/deactivate | Feature test: multiple addresses, activation state, tenant isolation | `COMPLETE_AND_VERIFIED` |
| FR-025 | Address masking by context; never full on public portal | MUST | Masking applied at the serializer boundary, driven by viewer context | Unit test per masking level; assertion that no Step 4 response shape can emit a full address to an unauthenticated context | `COMPLETE_AND_VERIFIED` |
| FR-026 | Phone masking by context | MUST | Same serializer boundary; country code + last four by default | Unit test per masking level | `COMPLETE_AND_VERIFIED` |
| FR-027 | Consent state per customer per tenant, with timestamp and source | MUST | `customer_consents` append-only records: type, state, source, actor, timestamp | Feature test: grant, withdraw, history; assert no update-in-place | `COMPLETE_AND_VERIFIED` |
| FR-028 | Opt-out never reset by import, bulk update, or migration | MUST | Opt-out is a recorded event, not a mutable flag; no code path rewrites a withdrawal | Negative test: run a bulk update and a re-seed; assert the recorded opt-out still governs | `COMPLETE_AND_VERIFIED` |
| FR-029 | Customer order history, never cross-tenant | MUST | **Deferred surface, not deferred rule.** Orders are Step 5 (FR-048+); Step 4 builds the tenant-scoped customer anchor the history will hang from and asserts no cross-tenant read path exists | Tenant-isolation tests on the customer aggregate. **The history view itself is Step 5 and is not claimed here** | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |
| FR-030 | Internal customer notes, never on public portal | SHOULD | `customers.internal_notes`, excluded from every public projection by allow-list | Test asserting the public projection is an allow-list, so an added field cannot leak by default (Rule 32 hard rule 7) | `COMPLETE_AND_VERIFIED` |

### 2.2 Service master data — FR-031 … FR-033, FR-040

| ID | Requirement | Pri | Mechanism | Verification | Status |
|---|---|---|---|---|---|
| FR-031 | Kiloan (by weight) and satuan (per item) services | MUST | `service_catalog` with an explicit unit-of-measure enum; no free-text unit | Feature test per service shape; invalid-unit rejection test | `COMPLETE_AND_VERIFIED` |
| FR-032 | Packages combining services at a defined price | SHOULD | `service_packages` + package-item join, tenant-scoped | Feature test: composition, activation, tenant isolation | `COMPLETE_AND_VERIFIED` |
| FR-033 | Add-ons applied to an order or order line | SHOULD | `service_addons` as master data only. **The application of an add-on to an order is Step 5** and is not built here | Feature test on the catalogue entry; explicit absence of any order linkage | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |
| FR-040 | Single canonical price source, never hard-coded in clients | MUST | Prices read from tenant configuration through one server-side source; no price literal in any client | Test asserting no client-side price literal; scope guard already forbids scattered price strings | `COMPLETE_AND_VERIFIED` |

### 2.3 Per-brand price lists — FR-034 … FR-039

| ID | Requirement | Pri | Mechanism | Verification | Status |
|---|---|---|---|---|---|
| FR-034 | Price list belongs to a brand | MUST | `price_lists.laundry_brand_id`, with the brand's tenant re-derived server-side, never client-supplied | Feature test + negative test: brand from another tenant is rejected (Rule 39 hard rule 5) | `COMPLETE_AND_VERIFIED` |
| FR-035 | Versioned with an effective period; publishing never alters a published version | MUST | `effective_from` / `effective_until`; published versions immutable; supersede by insert | Test: publish v2, assert v1 byte-identical; **overlap-prevention test for the same brand** | `COMPLETE_AND_VERIFIED` |
| FR-036 | Order captures the price that applied, immune to later change | MUST | **Step 4 prepares the capture contract; Step 5 proves it against a real order.** Step 4 delivers the immutable, addressable price version the snapshot will reference | Immutability test on published versions. **Step 4 does not claim FR-036 satisfied** — proving it needs an order, which is FR-048 (Step 5) | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |
| FR-037 | Integer Rupiah everywhere; no floating point in any money path | MUST | Money columns are integer types from their first migration; no `float`/`double`/`decimal`-as-float | Migration-level assertion over the live PostgreSQL schema that no money column is a floating type; unit tests on arithmetic | `COMPLETE_AND_VERIFIED` |
| FR-038 | Explicit rounding at a defined point | MUST | Rounding rule stated and applied in one place, never left to language defaults | Unit tests over the rounding boundary cases | `COMPLETE_AND_VERIFIED` |
| FR-039 | Price override requires permission and a recorded reason | MUST | **Override applies to an order, which is Step 5.** Step 4 defines the permission and the reason-capture contract; it builds no override path | Permission registered and tested. **The override flow itself is Step 5 and is not claimed here** | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |

### 2.4 Outlet master data — FR-041 … FR-047

| ID | Requirement | Pri | Mechanism | Verification | Status |
|---|---|---|---|---|---|
| FR-041 | Operating hours in outlet local time | MUST | Outlet timezone stored explicitly; hours stored per outlet; UTC storage, outlet-local presentation (Rule 43) | Feature test across at least two distinct outlet timezones — a single-timezone test proves nothing about the rule | `COMPLETE_AND_VERIFIED` |
| FR-042 | Production capacity definition | SHOULD | Capacity fields on outlet master data | Feature test | `COMPLETE_AND_VERIFIED` |
| FR-043 | Service zones for pickup and delivery coverage | MUST | `outlet_service_zones`, tenant-scoped. **Coverage definition only** — routing is Step 8 | Feature test + tenant isolation | `COMPLETE_AND_VERIFIED` |
| FR-044 | Shift definitions anchoring shift closing | MUST | `outlet_shifts`. **Definitions only** — shift closing and cash reconciliation are Step 5 | Feature test + tenant isolation | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |
| FR-045 | Printer configuration for nota output | SHOULD | `printers` / outlet printer configuration. **Configuration only** — the nota is FR-052 (Step 5), and `receipt` remains a forbidden token (DEC-0030) | Feature test; scope-guard fixture already proves `nota` is still rejected | `COMPLETE_AND_VERIFIED` |
| FR-046 | Tenant configures required proof mechanisms | MUST | Tenant-level proof policy configuration. **Configuration only** — proof capture is Step 8 | Feature test; assert some proof is always required by the policy shape | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |
| FR-047 | Quiet hours per outlet, default 20.00–08.00 outlet local time | MUST | Outlet quiet-hours configuration with the canonical default | Feature test asserting the default is exactly 20.00–08.00 outlet local time. **Quiet-hours enforcement is Step 7** | `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` |

---

## 3. Requirements this Step deliberately does not close

Six requirements in the FR-021 … FR-047 range **cannot be fully satisfied by Step 4**, because proving
them requires an order, and orders are FR-048+ in Step 5. Step 4 builds the master data they depend on
and states plainly that it has not closed them:

| ID | What Step 4 delivers | What remains, and where |
|---|---|---|
| FR-029 | Tenant-scoped customer anchor; no cross-tenant read path | The order-history surface — Step 5 |
| FR-033 | The add-on catalogue entry | Applying an add-on to an order line — Step 5 |
| FR-036 | An immutable, addressable published price version | Proving a real order's price survives a price-list change — Step 5 |
| FR-039 | The permission and reason-capture contract | The override flow on an order — Step 5 |
| FR-044 | Shift definitions | Shift closing and cash variance — Step 5 |
| FR-046 | Proof policy configuration | Proof capture at custody transfer — Step 8 |
| FR-047 | Quiet-hours configuration | Quiet-hours enforcement in messaging — Step 7 |

**Recording this is not scope reduction.** These requirements were always multi-step; the PRD assigns
them to Step 4 because Step 4 owns the master data they configure. Claiming Step 4 "delivered FR-036"
because a price table exists would be a false claim under Rule 01, and this table exists so that claim
is never made by accident.

---

## 4. Hard-gate acceptance criteria

These are not per-requirement criteria; they gate the Step regardless of feature completeness.

### 4.1 Tenant isolation (Rule 02, Rule 39, Rule 48) — automatic `NO-GO` on failure

For **every** Step 4 aggregate — customer, customer address, customer consent, service, package,
add-on, price list, outlet service zone, outlet shift, printer configuration — a member of tenant A must
be proven unable to reach a tenant B record through **each** of these paths independently:

1. direct ID lookup
2. list endpoint
3. filter parameter
4. free-text search
5. export
6. signed or unsigned file URL

**A test covering only direct ID does not satisfy this gate** (Rule 48 hard rule 3). Each path is a
distinct, independently exploitable surface.

Additional criteria:

- **AC-T1** — A `404`/`DENIED` response is byte-identical whether the record belongs to another tenant
  or does not exist (Rule 48 hard rule 5).
- **AC-T2** — A client-supplied `tenant_id` in a header, body, or query parameter is never authorization
  proof; the server re-derives scope from verified membership on every request (Rule 39 hard rule 1).
- **AC-T3** — A `laundry_brand_id` or `outlet_id` referencing another tenant is rejected, not silently
  scoped away (Rule 39 hard rule 5).
- **AC-T4** — All isolation evidence is produced against **PostgreSQL**, never SQLite or any substitute
  engine (Rule 43 hard rules 1–2).

### 4.2 Financial integrity (Rule 04, Rule 18) — automatic `NO-GO` on failure

- **AC-F1** — Every money column in every Step 4 migration is an integer type. Verified by querying the
  **live PostgreSQL schema**, not by reading the migration source — a migration can say one thing and
  the resulting column be another.
- **AC-F2** — No `float`, `double`, or binary floating-point arithmetic appears in any price path.
- **AC-F3** — A published price-list version is immutable: republishing produces a new version and
  leaves every prior version byte-identical.
- **AC-F4** — Two active price lists for the same brand may not overlap in their effective periods.
- **AC-F5** — The rounding rule is explicit, applied at one defined point, and unit-tested at its
  boundaries.

### 4.3 Security, privacy, and public-repository safety

- **AC-S1** — Every permission check is server-side at the API boundary (Rule 40 hard rule 2).
- **AC-S2** — Removing a role takes effect on the very next request (Rule 40 hard rule 3).
- **AC-S3** — No customer name, phone number, or address appears in any log, audit payload, or error
  response beyond its masked form (Rule 46 hard rule 2).
- **AC-S4** — Every seed, fixture, and factory value is fictional **and recognisably so** — a
  sequential or obviously placeholder pattern, not a plausible-looking fake (Rule 45). Master data is
  precisely where a real phone number slips in.
- **AC-S5** — The public projection is an allow-list, so a newly added field cannot leak by default
  (Rule 32 hard rule 7).

### 4.4 Governance

- **AC-G1** — `bash scripts/verify-step-03.sh` regression passes; no Step 0–3 gate weakened.
- **AC-G2** — Migrations tested for fresh apply, rollback, and re-apply, with output captured
  (Rule 43 hard rule 3).
- **AC-G3** — Every validator introduced or changed is adversarially tested against deliberately broken
  input and shown to fail before it is relied upon (Rule 47, Rule 33).
- **AC-G4** — No Step 5+ feature is detected by the scope guard, under any name (Rule 36 hard rule 4).
- **AC-G5** — Authoritative CI green at the exact final candidate SHA; PR head SHA equals the CI-tested
  SHA (Rule 11, Rule 47).
- **AC-G6** — Every claim in the evidence pack carries its full 40-character SHA, exact command, and
  captured output (Rule 01, DEC-0013).

---

## 5. Evidence plan

Evidence lands under `evidence/step-04/`, sanitised, each artefact bound to the exact SHA it was
produced from. Required before any Step 4 `GO` claim:

| Evidence | Proves |
|---|---|
| Migration fresh / rollback / re-apply output | AC-G2, Rule 43 |
| Live PostgreSQL money-column type dump | AC-F1, AC-F2 |
| Tenant-isolation matrix: 6 access paths × every aggregate | §4.1, Rule 48 |
| Price-list immutability and overlap-prevention output | AC-F3, AC-F4 |
| Permission and role-revocation test output | AC-S1, AC-S2 |
| Adversarial validator run for every new or changed validator | AC-G3 |
| `verify-step-03.sh` regression output | AC-G1 |
| Authoritative CI run ID at the final candidate SHA | AC-G5 |

**Skips are recorded as skips.** A gate that could not run has verified nothing, and the evidence pack
names it rather than folding it into a pass count. The Flutter gates are currently skipped in the
maintainer environment because Flutter is not on `PATH`; that is an unverified gate, not a passing one.

---

## 6. Non-goals

Step 4 builds master data, not the workflows that consume it. Explicitly out of scope, and
`NOT IMPLEMENTED` throughout: order creation and lifecycle, POS and cashier flows, production stages,
quality control, packaging, the nota, invoices, payments, QRIS, refunds, promotions, discount engines,
pickup scheduling, delivery routing, courier applications, the public tracking portal runtime, WhatsApp
sending, the H+1/H+3/H+7/H+14 ladder, unclaimed-laundry automation, billing, subscription payments,
reporting, and deployment.

The withdrawn *"Domain, Branding, Environment, and SaaS Planning Foundation"* brief is
`UNSCHEDULED / REQUIRES SEPARATE CANONICAL DECISION` and must not be built under Step 4's authorization
(DEC-0028).

---

## 7. Rollback

Every Step 4 change is additive: new tables, new modules, new tests. No Step 0–3 table is altered or
dropped, and no Step 3 authorization behaviour is modified.

Rollback is therefore `git revert` of the Step 4 commits plus `php artisan migrate:rollback` for the
Step 4 migration batch, which AC-G2 requires to be tested before any migration is trusted. Because
nothing is deployed — deployment is `ABSENT` — there is no environment to roll back beyond local
development and CI.
