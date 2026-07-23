# Step 5 — POS, Order, and Payment Foundation: Evidence Pack

**Step:** 5 — POS, Order, and Payment Foundation
**Status:** `IN PROGRESS` — backend (Units A–E) and the operator UI (Unit F) implemented and verified;
OQ-017 resolved. `GO` is the owner's to confer after merge.
**Authorised by:** the canonical roadmap (Master Source §24) — runtime scope opened by
[DEC-0035](../../docs/decisions/DEC-0035-step-05-runtime-scope-transition.md); order-line rounding
mode ratified by [DEC-0036](../../docs/decisions/DEC-0036-oq-017-order-rounding-mode-halfup.md).
**Master Source version:** 1.4.7

## SHAs (kept distinct, Rule 01)

| Role | SHA |
|---|---|
| Baseline (`main` at Step 5 start) | `d18602950034973b6f2bdeef107d146e940450e8` |
| Backend runtime checkpoint (first gate run) | `c2565fca4d64bad15b75830d37ab39c8ac06f060` |
| Backend evidence commit | `9d381d57dd854a74356e7f69c8b34abe2234a135` |
| **Complete Step 5 candidate — all gates re-run here (backend + Unit F)** | **`b12921a8a0de744ae8ecbae3c83e10962699281f`** |

The gate results in THIS pack were produced at the complete candidate
**`b12921a8a0de744ae8ecbae3c83e10962699281f`**, on a clean working tree, against the authoritative
PostgreSQL database and with the pinned Flutter 3.44.6 SDK (Rule 43, Rule 37). This README and the
`.txt` files are committed in the immediately following commit; the results belong to the candidate SHA
above. Results captured only at the earlier backend checkpoint are NOT claimed to prove the Flutter
candidate — the full backend suite was re-run here.

**Sanitisation.** Every captured file is test/gate console output only — no customer datum, phone,
address, token, secret, or credential; every fixture is recognisably fictional; scanned before commit
(Rule 23).

## What was verified (captured files, all at `b12921a`)

| Gate | File | Result |
|---|---|---|
| Full backend suite (regression + Step 5), live PostgreSQL | [`backend-full.txt`](backend-full.txt) | **538 passed** (5209 assertions), 0 failed |
| Step 5 backend suites (Ordering + Payments) | [`backend-step5.txt`](backend-step5.txt) | **72 passed** |
| Flutter analyze (domain, networking, ops_android, admin_web) | [`flutter-analyze.txt`](flutter-analyze.txt) | No issues found |
| Flutter order/payment contract tests (`pos_repository`) | [`flutter-pos-repo.txt`](flutter-pos-repo.txt) | **7 passed** |
| Flutter ops_android suite (incl. POS widget tests) | [`flutter-ops-suite.txt`](flutter-ops-suite.txt) | **113 passed** |
| Governance validator suite (7 validators) | [`governance.txt`](governance.txt) | **PASS** |
| Runtime-scope guard (`classify`) | [`runtime-scope.txt`](runtime-scope.txt) | PASS |
| DEC-0035 label audit | [`dec-0035-labels.txt`](dec-0035-labels.txt) | PASS |
| Step 5 adversarial harness | [`adversarial-step05.txt`](adversarial-step05.txt) | **12/12** |
| Live-schema Step 5 scope guard | [`schema-scope.txt`](schema-scope.txt) | within scope (3 Step 5 tables, 0 forbidden) |
| No float in any money path | [`money-rules.txt`](money-rules.txt) | PASS |
| MASTER_SOURCE checksum (1.4.7) | [`checksum.txt`](checksum.txt) | OK |

Runner: [`scripts/verify-step-05.sh`](../../scripts/verify-step-05.sh) — orchestrates the above plus
the delegated Step 0–4 regression (the Flutter Step 0–4 gates run in CI / with Flutter on PATH).

## Requirement → evidence traceability

**Backend (FR-048 … FR-070, FR-036):**

| Requirement | Enforced by | Proven in |
|---|---|---|
| FR-048/050/053/055/058/059/060, FR-036 snapshot | orders/order_lines schema + `OrderRegistry` | `OrderingSchemaTest` (16), `OrderRegistryTest` (13) |
| FR-051 server-authoritative totals; FR-038/OQ-017 HALF_UP | `OrderPricing` + DB CHECK | `OrderPricingTest` (12, incl. half-Rupiah boundary) |
| FR-052 nota | `ReceiptProjection` (captured-price snapshot) | `PaymentRegistryTest`, `OrderPaymentSurfaceTest` |
| FR-061/062/064 payment methods, idempotency, no client-claimed paid | `PaymentRegistry` + UNIQUE(client_reference) | `PaymentRegistryTest` (14) |
| FR-063 gateway verification + replay reject | `PaymentRegistry::confirmGateway` | `PaymentRegistryTest` |
| FR-065/067 refund/void by reversal + reason | `PaymentRegistry::reverse` | `PaymentRegistryTest` |
| FR-066 no hard delete of financial records | `payments` ENABLE ALWAYS trigger | `PaymentsSchemaTest` |
| FR-070 receivable (derived) | `OrderBalance` | `PaymentRegistryTest`, `OrderPaymentSurfaceTest` |
| Tenant isolation (Rule 02/48); RBAC (Rule 40) | composite FKs + policies | schema tests, `OrderPaymentSurfaceTest` (403/404) |

**Operator UI — Unit F (FR-048/049 intake, FR-051 display, FR-052 nota, FR-057 list, FR-064 QRIS):**

| Requirement | Delivered by | Proven in |
|---|---|---|
| FR-057 order list, outlet-scoped | `pos_counter_screen.dart` | `pos_test.dart` (list render + `outlet_id` sent) |
| FR-048/049 shortest-path intake, idempotent | `pos_new_order_screen.dart` (single `client_reference`) | `pos_test`, `pos_repository_test` |
| FR-051 server totals shown, never client-computed | `OrderProjection`/`Rupiah` (integer, no arithmetic) | `pos_test` (Rp20.000 from server), `pos_repository_test` |
| FR-052 nota | `_ReceiptSheet` from server `Receipt` | `pos_test` (detail), `pos_repository_test` |
| FR-058/065 cancel/reverse with reason | detail actions | `pos_test` (actions), `pos_repository_test` (reason sent) |
| FR-064 QRIS shown as pending, never fabricated | payment sheet + `PaymentMethod.isGateway` | code + `pos_repository_test` |

## What remains (why this is still `IN PROGRESS`, not `GO`)

- **Owner merge of PR #21 to `main`** (Rule 12 — merge is the owner's) and **owner-conferred `GO`**
  (Rule 01 — never self-declared by an agent).
- **Deployment** remains `ABSENT` and is not authorised by anything in Step 5.

No internally-actionable Step 5 requirement remains: the backend and the operator UI are implemented
and verified, and OQ-017 is closed.

## Post-GO (append-only — the two sections above are the state at the candidate SHA)

The "What remains" section above is the state **while Step 5's PR was open** and is retained as history.
Step 5 has since been **merged (PR #21 → `f0524b3`), owner-conferred `GO`, and GO-tagged**
(`aish-laundry-step-05-pos-order-payment-foundation-v1.0.0-go` → `f0524b3`), with the status
synchronisation merged via PR #22. Closure evidence: [`GO-CLOSURE.md`](GO-CLOSURE.md).

A **post-GO verifier-tooling defect** was subsequently found and repaired (the local
`verify-step-0X.sh` chain had a stale version pin and two non-step-aware Step-3 adversarial harnesses;
none was a product, guard, CI, or schema defect, and the `GO` tag is unchanged). Diagnosis, repair, and
the clean re-verification (`verify-step-05.sh` → **PASS 22 / FAIL 0 / SKIP 0**) are in
[`POST-GO-VERIFIER-REPAIR.md`](POST-GO-VERIFIER-REPAIR.md) and
[`verify-step-05-post-repair.txt`](verify-step-05-post-repair.txt); the correction to GO-CLOSURE §4's
"exit 0" reading is in [`GO-CLOSURE.md`](GO-CLOSURE.md) §7.
