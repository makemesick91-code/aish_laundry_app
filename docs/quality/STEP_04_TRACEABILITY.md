# Step 4 — Final Traceability, Findings Closure, and Step 5 Handoff

**Step:** 4 — Laundry Master Data
**Status:** `IN PROGRESS` — merge-ready handoff. `GO` is the repository owner's to confer (Rule 01).
**Master Source version:** 1.4.3
**Independent review closure:** [DEC-0033](../decisions/DEC-0033-step-04-independent-review-closure.md)
**Evidence:** [`evidence/step-04/`](../../evidence/step-04/), bound to the final candidate SHA
recorded in that pack's README.

---

## 1. What this document is, and what it is not

It maps each Step 4 obligation to the artefact that satisfies it and the executed verification that
proves it. **A row here is not evidence.** It is a pointer to evidence, and the evidence is captured
output bound to an exact 40-character commit SHA (Rule 01, DEC-0013).

It also records the twelve independent-review findings and how each closed — including the two that
closed only after a first attempt proved nothing.

**Status vocabulary**, used exactly:

| Value | Meaning |
|---|---|
| `COMPLETE_AND_VERIFIED` | Step 4's obligation is finished and evidenced. |
| `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING` | Step 4 built everything asked of it; the end-to-end proof needs a Step 5 surface that does not exist. |
| `NOT_APPLICABLE` | Genuinely does not apply at this step. |
| `NOT_PERFORMED` | Should have run and did not. |
| `FAILED` | Ran and failed. |

**A generic "complete" is never used for the second case.** A reader skimming for what is finished
would take it as finished, and for FR-036 that reader would be wrong about a financial-integrity
obligation.

---

## 2. FR-024 — saved customer addresses

**Statement (PRD §15.3):** *A customer shall be able to hold multiple saved addresses, each usable
for pickup and delivery.* **MUST.** Aggregate: Customer Management. Surfaces: Ops Android, Console Web.

| Obligation | Artefact | Evidence |
|---|---|---|
| Schema | `customer_addresses` (2026_07_21_010100), `version` added by 2026_07_21_030200 | `evidence/step-04/schema-invariants.txt` |
| Writer | `app/Modules/CustomerManagement/Services/CustomerAddressRegistry.php` — create, update, archive, reactivate | `evidence/step-04/backend-suite.txt` |
| HTTP surface | `CustomerAddressController`, six routes, archive/reactivate as POST (never DELETE) | route inventory in `Step04AuditCoverageTest` |
| Authorization | `Gate::authorize('view'/'update', $customer)`; tenant re-derived server-side | `CustomerAddressTest` |
| Primary-address invariant | Partial unique index `customer_addresses_one_primary`, demote-before-save ordering | `CustomerAddressTest` |
| Version-counter writes | `HasOptimisticVersion` on the model; `If-Unmodified-Since-Version` on every write path | `CustomerAddressTest`, `api_client_test.dart` |
| Stale-write behaviour | HTTP 409 → `EditConflict` → reload/review, never a resend | Ops + Console widget tests; runtime proof |
| Audit | four actions, field names only, never the address | `Step04AuditCoverageTest`, `CustomerAddressTest` |
| Ops Android UI | `customer_address_section.dart` (20 tests) | `apps/ops_android/test/customer_address_test.dart` |
| Console Web UI | `customer_address_panel.dart` (16 tests) | `apps/admin_web/test/customer_address_test.dart` |
| Cross-tenant concealment | six access paths; denial and absence byte-identical | `CustomerAddressTest`, runtime proof |
| Runtime 18-step proof | emulator against a live backend, production composition | `evidence/step-04/address-runtime.txt` |
| Adversarial mutations | 4 on Ops, 3 on Console, 2 on the backend projection | recorded per commit |

**Status: `COMPLETE_AND_VERIFIED`.**

**Residual boundary.** Addresses are stored and managed. Scheduling a pickup against one, assigning a
courier, sequencing a route and recording a delivery attempt are **Step 8** and are not built.
`is_pickup_suitable` and `is_delivery_suitable` are master-data flags describing the place, not
decisions about a parcel.

---

## 3. FR-025 — address masking by context

**Statement (PRD §15.3):** *Customer addresses shall be masked according to the viewing context, and
the full address shall never be exposed on the public tracking portal.* **MUST.**

| Obligation | Artefact | Evidence |
|---|---|---|
| Server-side masking | `app/Modules/CustomerManagement/Http/AddressProjection.php` | `CustomerAddressTest` |
| Allow-list, not a filter | `area()` assembles three named fields; the street is never read | mutation: rewriting it as a filtered copy leaks `notes` and fails |
| Context derivation | `customer.manage` → FULL, `customer.view` → AREA, neither → NONE, re-read per request | `CustomerAddressTest` |
| Default projection NONE | `CustomerProjection::detail()` fails closed when no context is passed | `test_the_detail_projection_defaults_to_disclosing_no_address` |
| Relationship path | customer detail routes through the same projection | `CustomerAddressTest` |
| List rows carry no location | `AddressProjection::listRow()` at every permission level | `CustomerAddressTest` |
| Client renders, never masks | `AddressPrecision` marker carried explicitly; parse fails closed | Ops + Console tests |
| No hidden full value | offstage-inclusive assertions on both surfaces | Ops + Console tests |
| No address in a URL | route-shape test | `CustomerAddressTest` |
| No address in logs or errors | audit scan; validation-error test | `CustomerAddressTest` |
| Restricted-context refusal | runtime proof, `refused=FORBIDDEN` | `evidence/step-04/address-runtime.txt` |

**Status: `COMPLETE_AND_VERIFIED`.**

**Residual boundary — the AREA context is `DORMANT_GOVERNED_CONTEXT`** (DEC-0033 §4). No shipped role
holds `customer.view` without `customer.manage`, so AREA is not reachable over HTTP today. It is
tested directly against the projection, and `test_no_shipped_role_reaches_the_area_context_yet` fails
the moment a role gains view-without-manage — so the branch cannot go live untested. **No permission
was invented to manufacture runtime evidence for it.**

**The public tracking portal is Step 7 and does not exist.** FR-025's portal clause is therefore
satisfied structurally, not behaviourally: no Step 4 projection can emit a full address to an
unauthenticated caller because no unauthenticated Step 4 route exists. Step 7 must prove the portal
clause against a real portal.

---

## 4. Staff and role assignment — FR-018 and roadmap Step 4 scope

| Obligation | Artefact | Evidence |
|---|---|---|
| Roadmap scope | Step 4 "staff and role assignment within a tenant" | Master Source §24 |
| Requirement | FR-018 (Tenant and Organization) | `REQUIREMENT_TRACEABILITY.md` |
| Boundary | [DEC-0031](../decisions/DEC-0031-step-04-traceability-boundaries.md) A2 — Step 4 introduces no new role or permission model | DEC-0031 |
| Implementation | `StaffAssignmentRegistry`, `StaffAssignmentController`, four routes | `StaffAssignmentTest` |
| Escalation guard | `assertNoEscalation` — a grantor may not grant beyond their own permissions | harness 7/7, SEC-06 |
| Suspension lifecycle | `assertAssignable` — suspended and revoked memberships receive no new assignment; invited may; revocation always permitted | `StaffAssignmentTest`, SEC-08 |
| Audit | `MEMBERSHIP_ROLE_ASSIGNED/REMOVED`, `STAFF_OUTLET_ASSIGNED/REVOKED` | `Step04AuditCoverageTest` |
| UI | Ops roster; grant and revoke gated separately | `master_data_test.dart` |

**Status: `COMPLETE_AND_VERIFIED`.**

**Recorded gap, not a claim.** Membership **suspension itself has no command surface**:
`AuditAction::MEMBERSHIP_SUSPENDED` and `MembershipPolicy::suspend()` exist and nothing writes either,
because no endpoint suspends a membership. `test_no_membership_suspension_endpoint_exists_yet` asserts
this so it cannot be assumed either way. Adding the lifecycle command and auditing it is future scope.

---

## 5. Step 5 handoff — the seven requirements Step 4 cannot close

Each is `PARTIAL_STEP_4_FOUNDATION_COMPLETE / STEP_5_E2E_PENDING`. **None may be waived.**

### FR-029 — customer order history, never cross-tenant
- **Step 4 completed:** the tenant-scoped customer aggregate the history hangs from; isolation proven across six access paths.
- **Unproven:** that a history VIEW never returns another tenant's order, because no order and no history view exists.
- **Step 5 proof required:** a history endpoint exercised by a member of tenant A against tenant B's orders, on every access path.
- **Non-waivable criterion:** a member of tenant A receives 404 for tenant B's order history, indistinguishable from absence.
- **Evidence Step 5 must produce:** isolation matrix rows for the history path, at an exact SHA, against PostgreSQL.

### FR-033 — add-ons applied to an order or order line
- **Step 4 completed:** `service_addons` as master data, CRUD, tenant-scoped, audited.
- **Unproven:** application of an add-on to an order line, and its effect on a total.
- **Step 5 proof required:** an order line carrying an add-on, priced from the captured price list.
- **Non-waivable criterion:** an add-on's price at order time is the captured one, not the live one.
- **Evidence Step 5 must produce:** an order-line test asserting the captured amount.

### FR-036 — order captures the price that applied, immune to later change
- **MANDATORY FINANCIAL-INTEGRITY OBLIGATION (Rule 04, invariant 11).** This is the highest-consequence entry in this table.
- **Step 4 completed:** published price lists are immutable except for lifecycle fields; the permitted set is now genuinely exercised (SEC-04); the concurrency token advances on supersede.
- **Unproven:** that a real order's total is unaffected by a later price-list change, because no order exists.
- **Step 5 proof required:** create an order, publish a superseding price list, reprint and re-read the order — the total and the invoice must be byte-identical.
- **Non-waivable criterion:** a price-list change never alters a historical order, invoice, or reprint. Failure is an automatic `NO-GO`.
- **Evidence Step 5 must produce:** captured before/after totals at an exact SHA against PostgreSQL.

### FR-039 — price override requires permission and a recorded reason
- **Step 4 completed:** the `price.override` permission exists in the registry and is granted to no Step 4 surface.
- **Unproven:** that an override is refused without the permission and rejects an empty reason, because there is no order to override.
- **Step 5 proof required:** an override attempt without the permission; an override with whitespace-only reason.
- **Non-waivable criterion:** the reason is mandatory, never pre-filled, and rejects whitespace-only input (Rule 32 hard rule 16).
- **Evidence Step 5 must produce:** both negative tests plus the audit row for a permitted override.

### FR-044 — shift definitions anchoring shift closing
- **Step 4 completed:** `outlet_shifts` with codes, times, midnight-crossing, audited.
- **Unproven:** that shift closing computes and surfaces a cash variance.
- **Step 5 proof required:** a shift close comparing expected against actual cash, with the variance recorded and acknowledged.
- **Non-waivable criterion:** a variance is never absorbed silently (Rule 04 hard rule 10).
- **Evidence Step 5 must produce:** a shift-closing test with a deliberate variance.

### FR-046 — tenant configures required proof mechanisms
- **Step 4 completed:** `tenant_proof_policies`, read-only GET (SEC-11), canonical defaults on the model, audited.
- **Unproven:** that a custody transfer is actually refused without the configured proof.
- **Step 8 proof required** (routed through Step 5's foundation): a pickup or delivery attempted without the configured proof and refused.
- **Non-waivable criterion:** no custody transfer is recorded without proof appropriate to the tenant's policy (Rule 09 hard rule 2).
- **Evidence required:** a negative custody-transfer test per configured proof mechanism.

### FR-047 — quiet hours per outlet, default 20.00–08.00 outlet local time
- **Step 4 completed:** the canonical default as a column default; timezone-correct evaluation including the midnight-spanning window; a discriminating test using an instant that differs between WIB and WIT.
- **Unproven:** that a message is actually DEFERRED during quiet hours, because no messaging exists.
- **Step 7 proof required:** a notification queued inside the window and shown to be deferred to the next permitted window, not dropped and not sent anyway.
- **Non-waivable criterion:** no non-critical message is sent inside the quiet window (Rule 08 hard rule 6).
- **Evidence required:** a scheduler test across the window boundary in a non-Jakarta outlet timezone.

---

## 6. Independent review findings — SEC-01 … SEC-12

| ID | Severity | Original defect | Root cause | Fix | Positive test | Adversarial mutation | Status | Residual boundary |
|---|---|---|---|---|---|---|---|---|
| SEC-01 | HIGH | STATUS.md claimed no concrete `AuthService` after PR #19 merged | prose with no relationship to the tree | `6107ba2` | validator 5/5 | 6 mutations + 3 legitimate cases, 10/10 | `FIXED_AND_VERIFIED` | validator reads STATUS.md; a claim made only elsewhere is out of scope |
| SEC-02 | HIGH | evidence pack bound to a stale SHA | evidence not rebuilt after remediation | Phase H | evidence validator | — | **`OPEN`** | closes only when the rebuilt pack and its validator pass |
| SEC-03 | HIGH | composition guard blind to inferred-type providers | pattern required an explicit annotation | earlier | guard 12/12 | harness 9/9 incl. mutation 5b | `FIXED_AND_VERIFIED` | structural signals only |
| SEC-04 | HIGH | published-price allow-list refused **everything** | trait boot order made `version` dirty before the check | `9143620` | per-field positive controls | 2 mutations | `FIXED_AND_VERIFIED` | no HTTP route exposes post-publish mutation |
| SEC-05 | HIGH | FR-024/FR-025 had no writer and no enforced masking | table mistaken for feature | `d049421`, `202a3ad`, `93d8f8b`, `7001cf3` | 24 backend + 20 Ops + 16 Console + runtime | 9 mutations | `FIXED_AND_VERIFIED` | AREA dormant; portal clause is Step 7 |
| SEC-06 | HIGH | escalation test never reached the guard | wrong actor chosen | earlier | 7/7 | positive control | `FIXED_AND_VERIFIED` | — |
| SEC-07 | MEDIUM | `ApiClient.put()` dropped `expectedVersion` | only `patch` was ever tested | `29262e1` | 3 verbs × 2 cases | forwarding removed → fails | `FIXED_AND_VERIFIED` | no caller exists yet; live the moment one is built |
| SEC-08 | HIGH | suspended membership could receive a role | `assignRole` checked no lifecycle state | `78ab450` | 8 tests | registry + UI mutations | `FIXED_AND_VERIFIED` | suspension has no command surface (§4) |
| SEC-09 | MEDIUM | printer sort produced HTTP 500 | one allow-list across three tables | `d64835d` | instance + live-schema class test | widened list → fails | `FIXED_AND_VERIFIED` | printer CONFIG only; no nota runtime |
| SEC-10 | HIGH | 24 mutating routes unaudited | no audit in three modules | `26aa11b` | router-driven gate + behavioural tests | new route + removed record() | `FIXED_AND_VERIFIED` | rejected writes are not audited, by decision |
| SEC-11 | MEDIUM | `GET /proof-policy` wrote a row | lazy persistence in a read accessor | `d9f0d53` | no-row-created + control | save() restored → fails | `FIXED_AND_VERIFIED` | — |
| SEC-12 | HIGH | consent RULEs missed truncation and refused silently | rules never see table-level truncation | `76c653c` | UPDATE/DELETE/TRUNCATE refused + live-schema | trigger removed → DB accepts | `FIXED_AND_VERIFIED` | bounded to the application role; not schema owners or superusers |

**Eleven `FIXED_AND_VERIFIED`. SEC-02 remains `OPEN`.**

---

## 7. Verification history that is deliberately preserved

Deleting these would leave the final results looking like they were right the first time.

1. **Three invalid first-run verifications** — an empty catalogue, a skipped cross-tenant assertion, and an escalation test whose actor was permitted. [`invalid-first-run-verifications.md`](../../evidence/step-04/invalid-first-run-verifications.md).
2. **A test-count claim of 411 where the verified figure was 413.** [`corrections.md`](../../evidence/step-04/corrections.md). Understatement, disclosed regardless.
3. **Two failed runtime-proof attempts**, both **verification setup errors and not product defects**: a trailing slash in the base URL that failed `Environment.validate`, and tenant identifiers read before a deterministic reseed. Neither was worked around by weakening an assertion.
4. **A mutation that did not discriminate.** Flipping the customer-detail projection default from `NONE` to `FULL` passed the entire suite, because every caller passes a context explicitly — so the "fails closed" claim was unproven. It now has a dedicated test that fails under that mutation. **This test must not be removed or weakened.**

---

## 8. What Step 4 does not deliver

Orders, POS, payments, invoices, receipts, production, quality control, pickup, delivery, courier
assignment, the tracking portal, WhatsApp, the reminder ladder, reporting, subscription, and
deployment all remain `NOT IMPLEMENTED`. A price list is not a priced order; a service catalogue is
not a POS; printer configuration is not a nota.
