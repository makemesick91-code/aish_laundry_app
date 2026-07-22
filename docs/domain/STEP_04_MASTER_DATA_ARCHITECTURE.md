# Step 4 — Laundry Master Data: Architecture, Contracts, and Threat Model

**Step:** 4 — Laundry Master Data · **Status:** `IN PROGRESS`
**Authorized by:** [DEC-0028](../decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md) ·
**Runtime scope:** [DEC-0030](../decisions/DEC-0030-step-04-runtime-scope-transition.md) ·
**Traceability boundaries:** [DEC-0031](../decisions/DEC-0031-step-04-traceability-boundaries.md)
**Requirement matrix:** [`../quality/STEP_04_REQUIREMENT_MATRIX.md`](../quality/STEP_04_REQUIREMENT_MATRIX.md)

**CONCEPTUAL DOMAIN MODEL AND PHYSICAL SCHEMA PLAN.** Step 1's diagrams carried
`CONCEPTUAL DOMAIN MODEL — NOT DATABASE SCHEMA` because schema was forbidden then. Step 4 is authorised
to create schema, so this document is both: §3 is conceptual, §4 is the physical plan it produces.

---

## 1. Bounded contexts touched

Step 4 populates three of the twenty canonical bounded contexts (Rule 17) and touches no others:

| Context | Step 4 aggregates | Backend module |
|---|---|---|
| Customer Management | Customer, CustomerAddress, CustomerConsent | `App\Modules\CustomerManagement` |
| Service Catalog and Pricing | ServiceCategory, Service, ServicePackage, ServiceAddon, PriceList, PriceListItem | `App\Modules\ServiceCatalog` |
| Tenant and Organization | Outlet extensions, OutletServiceZone, OutletShift, OutletPrinter, staff assignment | `App\Modules\Organization` (existing) |

**No context reaches into another's tables** (Rule 06 hard rule 6, Rule 17 hard rule 6). Where the
service catalogue needs an outlet it goes through the Organization module's interface, not through a
join written in a controller.

Step 4 creates **no new bounded context** — that would require its own decision record (Rule 17 hard
rule 7).

## 2. What Step 4 does not model

Order, OrderLine, Payment, Nota, ProductionJob, QualityControl, Pickup, Delivery, TrackingToken,
Reminder, Receivable, Subscription. All remain `NOT IMPLEMENTED` and structurally forbidden by
DEC-0030. Where a Step 4 aggregate exists *so that* a later step can reference it, that is stated
explicitly and no forward reference is created.

---

## 3. Aggregates and invariants

### 3.1 Customer (root) — FR-021 … FR-030

**Entities:** Customer (root), CustomerAddress, CustomerConsent.

| # | Invariant | Enforced by |
|---|---|---|
| C1 | Every customer belongs to exactly one tenant | `tenant_id` NOT NULL + FK to `tenants` |
| C2 | The same phone in two tenants is two unrelated customers, never merged | Uniqueness is `(tenant_id, phone_normalized)`; **no global phone index exists**, so a cross-tenant lookup has no index to use and no code path to reach it |
| C3 | A customer code is unique within its tenant, never globally | `unique(tenant_id, code)` — a global collision would disclose that another tenant holds that code (Rule 32) |
| C4 | An address belongs to the same tenant as its customer | Composite FK `(tenant_id, customer_id)` → `customers(tenant_id, id)` |
| C5 | A consent record is append-only; withdrawal is a new record, never an update | No update path in the service; the model blocks `save()` on an existing consent row |
| C6 | The latest consent record for a (customer, type) governs | Query ordered by `recorded_at`, `id` — deterministic even at equal timestamps |
| C7 | An opt-out is never reset by import, bulk update, or migration | C5 makes reset structurally impossible: there is no row to overwrite |
| C8 | Internal notes never appear in a public projection | Public projection is an **allow-list**; a new column cannot leak by default (Rule 32 hard rule 7) |

**Phone normalisation.** Stored twice: `phone` as entered, `phone_normalized` as E.164-ish digits for
matching. Normalisation is deterministic and applied server-side; the client never supplies the
normalized form. **The normalized phone is never an authorization key** — it identifies a customer
within an already-authorised tenant scope and nothing more (Rule 02 hard rule 9).

**Duplicate policy.** Detection, never automatic merge. A create whose `(tenant_id, phone_normalized)`
already exists is **rejected** with a machine-readable code and the existing customer's id, so the
operator decides. Cross-tenant duplicate detection does not exist and must never be added (Rule 02 hard
rule 11, Rule 18 invariant 8).

**Archival, not deletion.** `SoftDeletes`, matching the Step 3 convention. A customer referenced by a
future order must remain resolvable, so hard deletion is not offered.

### 3.2 Service catalogue — FR-031 … FR-033, FR-040

**Entities:** ServiceCategory, Service, ServicePackage + composition, ServiceAddon.

| # | Invariant | Enforced by |
|---|---|---|
| S1 | Every catalogue row is tenant-scoped | `tenant_id` NOT NULL + FK |
| S2 | A service is exactly one of `kiloan` or `satuan` | `unit_kind` enum, DB check constraint — no free-text unit |
| S3 | A kiloan service carries a minimum weight; a satuan service carries a minimum quantity | Check constraint per `unit_kind` |
| S4 | Service code unique within tenant | `unique(tenant_id, code)` |
| S5 | A package composes only services of the same tenant | Composite FK `(tenant_id, service_id)` |
| S6 | Availability at an outlet requires the outlet to be in the same tenant | Composite FK `(tenant_id, outlet_id)` |
| S7 | An inactive service cannot be added to a price list | Application invariant + test |

**Add-ons are catalogue entries only.** Applying an add-on to an order line is Step 5 (DEC-0031 B).

### 3.3 Price list — FR-034 … FR-039

**Entities:** PriceList (root), PriceListItem.

| # | Invariant | Enforced by |
|---|---|---|
| P1 | A price list belongs to a brand, and the brand's tenant is re-derived server-side | Composite FK `(tenant_id, laundry_brand_id)` → `laundry_brands(tenant_id, id)` — the Step 3 structural pattern |
| P2 | **All money is integer Rupiah.** No `float`, `double`, or `numeric` with a scale | `bigInteger` columns; verified against the **live PostgreSQL schema**, not the migration source |
| P3 | A price is non-negative | DB check constraint `amount_rupiah >= 0` |
| P4 | Two `active` price lists for the same brand may not overlap in effective period | PostgreSQL **exclusion constraint** over `(laundry_brand_id, daterange)` — enforced by the database, not by a read-then-write race |
| P5 | A published price list is immutable | Status transition to `published` freezes the row and its items; the service rejects mutation, and a DB trigger is not used because the application boundary is the only writer |
| P6 | Superseding creates a new version; the prior version stays byte-identical | Insert-only versioning; `supersedes_price_list_id` links them |
| P7 | Exactly one default price list per brand at a time | Partial unique index `where is_default and status = 'active'` |
| P8 | A price-list item references a service, package, or add-on of the same tenant | Composite FKs |

**Rounding (FR-038).** Every stored amount is already an integer Rupiah, so no rounding occurs on
storage. Rounding exists only where a computation could produce a fraction — percentage-based
adjustments, which Step 4 does not implement. **Step 4 therefore defines the rule and applies it in one
place; it does not claim to have exercised it**, because no fractional computation exists yet.

**P4 is the reason an exclusion constraint is used rather than a validation query.** A check that reads
existing rows and then inserts is a lost-update race: two concurrent publishes each see no overlap and
both commit. PostgreSQL's `EXCLUDE USING gist` rejects the second at the database.

### 3.4 Outlet master data — FR-041 … FR-047

Extends the existing `outlets` table rather than replacing it. New satellites:
`outlet_service_zones`, `outlet_shifts`, `outlet_printers`, plus columns for capacity and quiet hours,
and tenant-level `tenant_proof_policies`.

| # | Invariant | Enforced by |
|---|---|---|
| O1 | Operating hours and quiet hours are interpreted in the outlet's own timezone | `outlets.timezone` already exists (Step 3); hours stored as local wall-clock, converted at the application layer, never in the schema (Rule 43) |
| O2 | Quiet hours default to 20.00–08.00 outlet local time | Column default, asserted by test |
| O3 | Every satellite belongs to the outlet's tenant | Composite FK `(tenant_id, outlet_id)` |
| O4 | Zone and shift codes unique within outlet | `unique(tenant_id, outlet_id, code)` |

**`printers` is permitted; `nota` is not.** FR-045 authorises printer *configuration*; the receipt is
FR-052 in Step 5, and `receipt`/`nota`/`struk` remain forbidden tokens (DEC-0030).

### 3.5 Staff assignment — ROADMAP Step 4 scope + FR-018 (DEC-0031 A)

**No new authorization system.** Step 4 adds exactly one join table, `membership_outlet`, binding an
existing `Membership` to an existing `Outlet` within one tenant. Roles continue to come from
`PermissionRegistry` via `membership_role`.

| # | Invariant | Enforced by |
|---|---|---|
| A1 | A membership may only be assigned to an outlet in its own tenant | Composite FKs on both sides, both including `tenant_id` |
| A2 | Assignment never widens the assigner's own scope | Policy check: the caller must hold `MEMBERSHIP_ROLE_ASSIGN` **within the active tenant**, and cannot grant a role they do not themselves hold |
| A3 | A platform role is never assignable through a membership | `PermissionRegistry::assertAssignableToMembership()`, already enforced in Step 3 |
| A4 | Suspension and revocation take effect on the next request | Existing `Membership` status machinery; no new caching layer is introduced |

---

## 4. Physical schema plan

Eleven new tables plus additive columns on `outlets`. **Eleven, not "about fifteen"** — the count is
what the model above justifies.

| # | Table | Purpose | Tenant binding |
|---|---|---|---|
| 1 | `customers` | FR-021 … FR-030 | `tenant_id` + `unique(tenant_id, id)` for composite children |
| 2 | `customer_addresses` | FR-024, FR-025 | composite FK to `customers(tenant_id, id)` |
| 3 | `customer_consents` | FR-027, FR-028 — append-only | composite FK |
| 4 | `service_categories` | grouping | `tenant_id` |
| 5 | `service_catalog` | FR-031 kiloan + satuan | `tenant_id` + `unique(tenant_id, id)` |
| 6 | `service_packages` | FR-032 | `tenant_id` |
| 7 | `service_package_items` | package composition | composite FKs to package and service |
| 8 | `service_addons` | FR-033 | `tenant_id` |
| 9 | `price_lists` | FR-034 … FR-036 | composite FK to `laundry_brands(tenant_id, id)` |
| 10 | `price_list_items` | FR-037 integer Rupiah | composite FK to `price_lists(tenant_id, id)` |
| 11 | `outlet_service_zones`, `outlet_shifts`, `outlet_printers`, `membership_outlet`, `tenant_proof_policies` | FR-041 … FR-047, staff assignment | composite FK to `outlets(tenant_id, id)` / `memberships(tenant_id, id)` |

*(Row 11 groups the five small satellites; the physical count is 15 tables total, of which 11 are the
principal aggregates above.)*

**Every table follows the Step 3 structural pattern established by `outlets`:** UUID primary key,
`tenant_id` carried directly even when reachable through a parent, composite foreign keys so a
cross-tenant pairing is rejected by PostgreSQL rather than remembered by a developer, tenant-scoped
unique indexes never global, and `unique(tenant_id, id)` on any row that will be a composite FK target.

**Migration safety.** All migrations are additive: no existing table is altered destructively, no column
is dropped, no data is rewritten. `outlets` gains nullable columns with defaults. Rollback drops only
Step 4 tables and columns. Fresh apply, rollback, and re-apply are tested and captured (Rule 43 hard
rule 3, AC-G2).

---

## 5. Authorization matrix

New permissions, following the existing `resource.action` convention and registered in
`PermissionRegistry` — **the existing registry, extended; not a second one** (DEC-0031 A2):

| Permission | Granted to |
|---|---|
| `customer.view`, `customer.manage` | owner, admin, outlet manager, cashier (view), + manage per role |
| `customer.consent.manage` | owner, admin |
| `service.view`, `service.manage` | view widely; manage owner/admin |
| `price_list.view` | owner, admin, outlet manager, cashier, finance |
| `price_list.manage`, `price_list.publish` | owner, admin — publishing is a commercial act |
| `price.override` | owner, admin — **contract only; the override flow is Step 5** (DEC-0031 B) |
| `outlet.master_data.manage` | owner, admin, outlet manager (own outlet) |
| `staff.assignment.manage` | owner, admin |

Every check is server-side at the API boundary through a Policy using the existing
`InteractsWithTenantContext` trait (Rule 40 hard rule 2). **Client-side hiding is never the control.**

---

## 6. Threat model

STRIDE-classified. `CRITICAL`/`HIGH` findings each carry a control and a test (Rule 21 hard rule 3).

| ID | Threat | Sev | Control | Test |
|---|---|---|---|---|
| T-01 | Cross-tenant customer read/write | CRITICAL | Tenant-scoped queries + composite FKs + policy `sameTenant()` | 6-path isolation matrix |
| T-02 | Cross-tenant search leakage | CRITICAL | Search filters on `tenant_id` before any user term | Isolation test on the search path specifically |
| T-03 | Contact/address enumeration | HIGH | Bounded pagination; no sequential ids (UUID); identical 404 for absent and foreign | Enumeration test |
| T-04 | PII in logs or error bodies | HIGH | Existing `Redactor` + masked serializers; error bodies carry codes, never records | Log-capture test asserting no phone/address |
| T-05 | Mass assignment (`tenant_id`, `status`, `id`) | HIGH | Explicit `$fillable`; `tenant_id` set from `TenantContext`, never from input | Test posting a foreign `tenant_id` |
| T-06 | IDOR on any master-data id | CRITICAL | Tenant-scoped lookup; foreign id resolves to 404 | Direct-ID isolation test |
| T-07 | Consent tampering / fabricated timestamps | HIGH | Append-only; `recorded_at` set server-side; actor recorded | Test attempting update and forged timestamp |
| T-08 | Duplicate-merge abuse across tenants | CRITICAL | No cross-tenant lookup exists; detection is intra-tenant only | Same-phone-two-tenants test |
| T-09 | Unauthorized price change | CRITICAL | `price_list.manage` / `.publish`; published rows immutable | Permission + immutability tests |
| T-10 | Overlapping active price lists | HIGH | PostgreSQL exclusion constraint | Concurrent-publish test |
| T-11 | Floating-point money corruption | CRITICAL | Integer columns; no float in any path | Live-schema column-type assertion |
| T-12 | Lost update / stale write | MEDIUM | Optimistic concurrency via `updated_at` precondition on mutating endpoints | Stale-write test |
| T-13 | Cross-tenant outlet or staff assignment | CRITICAL | Composite FKs on both sides | Assignment isolation test |
| T-14 | Privilege escalation via assignment | HIGH | Caller cannot grant a role they do not hold; platform roles unassignable | Escalation test |
| T-15 | Client-controlled tenant or role scope | CRITICAL | `TenantContext` is server-derived and immutable (Step 3) | Existing Step 3 tests + Step 4 regression |
| T-16 | Cache-key leakage | HIGH | Existing `TenantCacheKey`; no Step 4 key without a tenant dimension | Unit test on every new key |
| T-17 | Unbounded filter / sort injection | MEDIUM | Sort and filter fields are allow-listed enums, never raw column names | Test posting an arbitrary sort field |
| T-18 | Deletion of data a future order will reference | HIGH | Soft delete only; no hard-delete path offered | Archive-then-resolve test |
| T-19 | Bulk-operation abuse | MEDIUM | No bulk mutation endpoint is offered in Step 4 | Absence asserted by route test |
| T-20 | Export leakage | — | **No export exists in Step 4.** The isolation matrix records export as `NOT APPLICABLE — no export path exists`, not as a pass | Route absence test |

**T-20 is recorded rather than silently skipped.** Rule 48 requires the export path to be tested; where
no export path exists the honest answer is that there is nothing to test, and the evidence pack says so
instead of counting it toward a pass.

---

## 7. Concurrency

| Operation | Strategy |
|---|---|
| Publishing a price list | PostgreSQL exclusion constraint (P4) — correctness does not depend on application timing |
| Customer create with duplicate phone | Unique index `(tenant_id, phone_normalized)` — the DB rejects the second writer |
| Any mutating master-data update | Optimistic concurrency on an explicit `version` counter; a stale write is surfaced, never silently applied (Rule 07 hard rule 5's principle, applied to master data) |
| Staff assignment | Unique index `(tenant_id, membership_id, outlet_id)` |

**No distributed lock is introduced.** Every case above is resolvable by a database constraint, and a
lock that is not needed is a failure mode that is not needed (Rule 44).

**Correction — the concurrency token is a counter, not `updated_at`.** This table previously specified
optimistic concurrency "on `updated_at`". That was implemented, tested, and found **wrong**: Laravel's
`timestamps()` produces a **second-precision** column in PostgreSQL, so two edits inside the same second
carry an identical `updated_at`. A stale-write detector blind precisely when two writers collide is not
a detector, and the failure was silent — a test passed whenever the two writes happened to straddle a
second boundary.

The mechanism is therefore an explicit, server-owned `version` counter incremented on every save
(`SharedKernel\Concerns\HasOptimisticVersion`), carried on every Step 4 master-data table. It cannot
collide and does not depend on clock precision. This is recorded here rather than silently corrected in
code, because the original statement was published and was wrong (Rule 01).

---

## 8. Test and evidence plan

Per the requirement matrix §4–§5. The binding structure:

- **Isolation matrix**: 6 access paths × every aggregate. Direct ID, list, filter, search, export
  (`NOT APPLICABLE` where absent, recorded as such), file URL (`NOT APPLICABLE` — Step 4 stores no
  files).
- **Money**: live-schema column-type assertion, not a source read.
- **Migrations**: fresh / rollback / re-apply, captured.
- **Every new validator**: adversarially tested before it is relied upon (Rule 47).

## 9. Rollback

All changes additive. Rollback is `git revert` of the Step 4 commits plus `migrate:rollback` of the Step
4 batch, which AC-G2 requires to be tested before the migrations are trusted. Nothing is deployed —
deployment is `ABSENT` — so there is no environment to roll back beyond local development and CI.
