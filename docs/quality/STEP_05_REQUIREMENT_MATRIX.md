# Step 5 — POS, Order, and Payment Foundation: Requirement Matrix and Acceptance Criteria

**Step:** 5 — POS, Order, and Payment Foundation
**Status:** `IN PROGRESS`
**Authorized by:** the canonical roadmap (Master Source §24; [`ROADMAP.md`](../ROADMAP.md))
**Runtime scope opened by:** [DEC-0035](../decisions/DEC-0035-step-05-runtime-scope-transition.md)
**Master Source version:** 1.4.6
**Baseline SHA:** `d18602950034973b6f2bdeef107d146e940450e8` (post-Step-4 canonical `main`)
**Depends on (Step 4, delivered):** customers, service catalog, per-brand price lists (integer Rupiah,
immutable published versions), outlets, staff/role assignment.

---

## 1. How to read this document

This is the Phase 0/1 requirement matrix for Step 5. It maps every canonical Step 5 requirement to the
mechanism that will satisfy it, the verification that will prove it, and the evidence that must exist
before the claim may be made.

**Nothing in this document is evidence.** A row saying a requirement will be verified by a test is a
plan, not a result. Only captured output bound to an exact 40-character commit SHA proves anything
(Rule 01, DEC-0013).

**As of the baseline SHA, no Step 5 runtime exists.** The only thing delivered so far is the DEC-0035
guard transition that *permits* Step 5 runtime to be built — a permitted token is not an implemented
feature (DEC-0035, decision 9). Every functional requirement below is therefore `NOT IMPLEMENTED`
until its migration, service, endpoint, and tests exist and are evidenced. A migration is not a tested
schema, and a table is not a feature.

**No requirement is invented here.** Step 5's requirement set is **FR-048 … FR-070**, fixed in
[`PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md) and its traceability table. Step 5 also
carries the **end-to-end proof of FR-036** (historical price immutability): Step 4 built the immutable,
addressable price-list version; proving an *order* honours it needs an order, and that is Step 5's
obligation (DEC-0031 B). A requirement that appears only in this matrix does not exist (Rule 16).

**The two hard gates do not relax.** Every order/payment table carries `tenant_id` and is tenant-scoped
server-side, proven by negative tests across every access path — direct ID, list, filter, search,
export, file URL (Rule 02, Rule 48). All money is integer Rupiah; floating point is forbidden in every
price, total, discount, deposit, payment, refund, and reconciliation path (Rule 04, Rule 18). The
financial-integrity test suite is **mandatory from this step onward** (ROADMAP Step 5).

---

## 2. Non-goals (Step 6 and later — must NOT be built under Step 5)

These remain `NOT IMPLEMENTED` and structurally forbidden by `validate-runtime-scope.py`
(`STEP6_PLUS_FEATURE_TOKENS`) regardless of how complete the order/payment foundation becomes:

production/washing/drying/finishing · quality control · rework · the order status lifecycle *in
operation* past intake (Step 6) · customer tracking portal · WhatsApp/notification sending · pickup
scheduling · delivery routing · courier assignment/settlement · unclaimed-laundry aging and the
H+1/H+3/H+7/H+14 reminder ladder · finance reports and owner portfolio · loyalty · subscription
billing · deployment.

An order **status field and its DRAFT/RECEIVED intake transitions** are Step 5; the production stages
(`SORTING … READY_FOR_PICKUP`) and the first-`READY_FOR_PICKUP` aging anchor are Step 6 (Rule 19,
Rule 10). Payment *at the counter* is Step 5; *courier cash* reconciliation is Step 8.

---

## 3. Architecture summary (Phase 1)

Follows the Step 3/4 conventions exactly (no new framework, database, or pattern):

- **Modules:** `backend/app/Modules/Ordering/` (order aggregate, POS intake, nota) and
  `backend/app/Modules/Payments/` (payment, refund/void, reversal ledger). Each with
  `Http/Controllers`, `Models`, `Services` (`*Registry` writers), `Http/*Projection`, and a
  `ServiceProvider`. Policies live centrally in `app/Modules/Authorization/Policies`.
- **Money:** plain integer Rupiah (`bigInteger('*_rupiah')`, cast `integer`, validation
  `['integer','min:0']`), the single rounding point via `SharedKernel/Money/RupiahRounding`. No
  `decimal`/`float`/`double` in any money path. Server-authoritative totals (FR-051); client totals are
  display-only.
- **Tenant isolation:** every table carries `tenant_id`; composite foreign keys
  `(tenant_id, x_id) REFERENCES parent (tenant_id, id)` bind every child to its parent's tenant at the
  engine (the Step 4 structural pattern); every query uses `scopeForTenant($context->tenantId())`;
  `tenant_id` is not mass-assignable and is set explicitly by the `*Registry`.
- **Idempotency:** a `client_reference` column with a `UNIQUE (tenant_id, client_reference)` constraint
  on both order-intake and payment writes, so a retry returns the original row rather than creating a
  second (FR-059, FR-062). The uniqueness is enforced by the database, not by a check-then-insert race.
- **Append-only financial records:** payments and reversals are never hard-deleted; an
  `ENABLE ALWAYS` engine trigger refuses deletes, mirroring `customer_consents` and the published
  price list (FR-066, FR-067; Rule 04). Corrections are reversal/adjustment rows that preserve the
  original.
- **Historical price capture:** an order line snapshots the resolved `price_list_id`,
  `price_list_item_id`, and `amount_rupiah` at creation; a later price-list supersession never mutates
  it (FR-036, invariant 11).
- **Concurrency:** operations that must not interleave — applying a payment, transitioning order
  status — are serialised by a database transaction / row lock (or a tenant-scoped Redis lock)
  (FR-068; Rule 44).
- **Audit:** every state change records actor/tenant/outlet/timestamp/reason via `AuditRecorder`, in an
  append-only financial audit trail separate from application logs (FR-060, FR-069).

An ADR will be authored for any material, irreversible decision (e.g. the order/payment table
boundary, the reversal-ledger shape).

---

## 4. Requirement matrix — FR-048 … FR-070

Status vocabulary here: `NOT IMPLEMENTED` (no runtime yet) · `IN PROGRESS` · `COMPLETE_AND_VERIFIED`
(finished AND evidence bound to an exact SHA). At the baseline SHA every row is `NOT IMPLEMENTED`.

| ID | Requirement (MUST unless noted) | Primary actor | Scope | Acceptance criterion (happy + negative) | Component | Hard gate | Status |
|----|----|----|----|----|----|----|----|
| FR-048 | Order creation `DRAFT → RECEIVED` | Kasir | tenant + outlet | Kasir creates an order for a tenant customer; a member of tenant B receives 404 for it by any path | `Ordering` migration/model/`OrderRegistry`/controller | TEN | NOT IMPLEMENTED |
| FR-049 | Shortest-path intake | Kasir | outlet | Intake is the fewest-step primary action; verified by the Ops flow | Ops UI (later surface) | — | NOT IMPLEMENTED |
| FR-050 | Order lines: kiloan weight, satuan, packages, add-ons | Kasir | tenant | A line references a Step 4 service/package/addon in the same tenant (composite FK); a cross-tenant reference is rejected at the engine | `order_lines` migration | TEN | NOT IMPLEMENTED |
| FR-051 | Server-authoritative totals | System | tenant | Client-supplied total is ignored; server recomputes from captured line prices; integer Rupiah | `OrderRegistry` + `RupiahRounding` | FIN | NOT IMPLEMENTED |
| FR-052 | Nota generation (reprintable, original prices) | Kasir | tenant | Reprint shows the price captured at order time even after a later price-list change | nota `Projection` | FIN | NOT IMPLEMENTED |
| FR-053 | Human-usable order number, grants no access | System | tenant | Order number is not the tenant/customer key and confers no authorization; guessing another number yields 404 | `OrderRegistry` | TEN/SEC | NOT IMPLEMENTED |
| FR-054 (SHOULD) | Per-item tracking readiness | System | tenant | Model can identify an individual garment/line; no production runtime | `order_lines` | — | NOT IMPLEMENTED |
| FR-055 | Special handling instructions | Kasir | tenant | A per-order/line instruction is stored and surfaced before a production stage | `orders`/`order_lines` | — | NOT IMPLEMENTED |
| FR-056 | Deposits; balance to settlement | Kasir | tenant | A deposit is recorded (integer Rupiah); balance = total − paid; never negative by construction | `Payments` deposit path | FIN | NOT IMPLEMENTED |
| FR-057 | Order search/listing (number/customer/status/outlet/date), paginated | Kasir/Manager | tenant + outlet | List/filter/search are tenant-scoped and paginated; tenant B rows never appear via any filter | `OrderController` index | TEN | NOT IMPLEMENTED |
| FR-058 | Order cancellation `→ CANCELLED` with reason + actor | Manager | tenant | Cancel requires permission + non-empty reason; recorded with actor/timestamp; a rejected transition changes nothing | `OrderRegistry` + policy | SEC/audit | NOT IMPLEMENTED |
| FR-059 | Offline order intake; stable `client_reference` on retry | Kasir | tenant | Same `client_reference` retried → exactly one order (UNIQUE constraint) | `orders.client_reference` unique | OFF/idemp | NOT IMPLEMENTED |
| FR-060 | Order audit trail | System | tenant | Every state change records actor/tenant/outlet/timestamp/reason | `AuditRecorder` | audit | NOT IMPLEMENTED |
| FR-061 | Payment methods: cash, transfer, gateway | Kasir | tenant | A payment is recorded against an order in integer Rupiah by an authorised actor | `PaymentRegistry` | FIN | NOT IMPLEMENTED |
| FR-062 | Payment idempotency | System | tenant | Same `client_reference` retried → exactly one payment (UNIQUE constraint), original result returned | `payments.client_reference` unique | FIN/idemp | NOT IMPLEMENTED |
| FR-063 | Server-verified gateway callbacks | System | tenant | Callback signature + amount + status verified server-side; a replayed callback is rejected, not reprocessed | callback handler | FIN/SEC | NOT IMPLEMENTED |
| FR-064 | No client-claimed payment | System | tenant | Paid state originates only from a verified server event or an authenticated staff action — never a client claim | `PaymentRegistry` | FIN | NOT IMPLEMENTED |
| FR-065 | Refund and void with permission + reason | Manager/Finance | tenant | Refund/void requires permission + non-empty reason; records actor/timestamp/amount | `PaymentRegistry` + policy | FIN | NOT IMPLEMENTED |
| FR-066 | No hard delete of financial records | System | tenant | No ordinary path deletes a payment; an engine `ENABLE ALWAYS` trigger refuses deletes | trigger migration | FIN | NOT IMPLEMENTED |
| FR-067 | Corrections by reversal/adjustment | Finance | tenant | A correction is a reversal/adjustment row; the original is preserved byte-identical | reversal ledger | FIN | NOT IMPLEMENTED |
| FR-068 | Serialised money operations | System | tenant | Concurrent payments on one order are serialised (transaction/lock); no double payment | lock around apply | FIN | NOT IMPLEMENTED |
| FR-069 | Financial audit trail (append-only, separate from app logs) | System | tenant | Append-only, actor/tenant/outlet/timestamp/before-after/reason; never carries a token/OTP | audit sink | FIN/audit | NOT IMPLEMENTED |
| FR-070 | Receivable tracking (derived) | Manager/Finance | tenant | Unpaid balance per order/customer in integer Rupiah, read from authoritative records (no separate table) | derived query | FIN | NOT IMPLEMENTED |
| FR-036 (E2E) | Historical price immutability, proven against an order | System | tenant | An order created against price list v1 keeps its captured prices after v1 is superseded by v2 | order-line snapshot | FIN | NOT IMPLEMENTED |

---

## 5. Financial-integrity test suite (mandatory from Step 5)

Definition-of-Done gate (Rule 04, Rule 13). Each must be a captured, exact-SHA result before any claim:

1. **Retry idempotency** — same `client_reference` → exactly one order and exactly one payment.
2. **Duplicate gateway callback replay** — a replayed callback is rejected, not reprocessed.
3. **Refund/void permission + reason** — enforced server-side; missing reason rejected.
4. **No hard delete** — a query-builder delete of a payment is refused at the engine (the Step 4
   `customer_consents`/price-list lesson: a model event is not enough).
5. **Historical price immutability** — FR-036 proven against a real order across a supersession.
6. **Integer Rupiah everywhere** — a live-schema assertion that no money column is `decimal`/`float`.
7. **Server-authoritative totals** — a client-supplied total is ignored.
8. **Concurrency** — two concurrent payment applies produce one payment, not two.

## 6. Tenant-isolation negative tests (mandatory, every access path — Rule 48)

For orders and payments, a member of tenant A must be proven unable to reach a tenant B record via:
direct ID · list · filter · free-text search · export · file URL. Each denial (404, indistinguishable
from "does not exist") is paired with the positive same-fixture case, against PostgreSQL — mirroring
`Step04IsolationMatrixTest`.

---

## 7. Verification & evidence plan (Phase 5)

- `scripts/verify-step-05.sh` (modelled on `verify-step-04.sh`, delegating Step 0–4 regression to
  `verify-step-04.sh`), running: the runtime-scope guard, `validate-dec-0035-labels.py`, the Step 5
  adversarial harness (`test-step-05-validators.sh`), the backend PHPUnit suite (financial-integrity +
  isolation), migration fresh/rollback/re-apply, and a live-schema money-type assertion.
- `evidence/step-05/` mirroring the `evidence/step-04/` file set, sanitised, bound to the final
  candidate SHA.
- Authoritative CI (`runtime-foundation.yml` + `classify`) green at the exact candidate SHA.

`GO` is owner-conferred against this evidence; it is never self-declared by an agent (Rule 01).
