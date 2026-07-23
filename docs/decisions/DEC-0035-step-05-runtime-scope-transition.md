# DEC-0035 — Step 5 Runtime Scope Transition

**ID:** DEC-0035
**Title:** Step 5 Runtime Scope Transition
**Status:** ACCEPTED
**Date:** 23 July 2026

---

## Context

`scripts/validate-runtime-scope.py` refuses, by construction, to let a Step 5 business table
exist. Since DEC-0030 it carries a `STEP5_PLUS_FEATURE_TOKENS` map — thirty labels owned by Step 5
or later — and rejects any migration filename, `Schema::create` argument, route path segment,
Eloquent model class name, or module directory whose identifier matches one of those tokens. Among
them:

```
"POS":            {"pos", "kasir", "point_of_sale"}
"order":          {"orders", "order_lines", "order_items", "transaksi", "pesanan"}
"laundry intake": {"intakes", "laundry_intake", "penerimaan"}
"payment":        {"payments", "pembayaran", "payment_transactions"}
"refund":         {"refunds", "pengembalian_dana"}
"QRIS":           {"qris"}
"receipt":        {"receipts", "nota", "struk"}
```

That is exactly what Step 5 — POS, Order, and Payment Foundation — must build. The guard is not an
obstacle to work around; it is doing precisely the job DEC-0024 gave it, and it keeps doing that job
until a decision record moves the boundary.

**Rule 36, hard rule 8 governs how that boundary moves:** the scope guard "may only be narrowed,
never silently widened. Widening the approved runtime roots, adding a runtime language, permitting a
further business-feature label, or authorizing deployment requires its own decision record naming
what it supersedes." DEC-0030's supersession policy says the same, and adds: "**Editing
`STEP5_PLUS_FEATURE_TOKENS` to unblock work is a governance breach, not a fix.** The step that owns a
label must itself have been authorised before that label may be permitted." This record is that
decision record.

Two facts bound what it may do.

1. **The canonical roadmap already authorises Step 5.** Master Source §24, `docs/ROADMAP.md`, and
   `docs/STATUS.md` all record **Step 5 — POS, Order, and Payment Foundation**. This record does not
   grant that authorisation and does not redefine it; it is the mechanical guard transition that lets
   the already-canonical Step 5 runtime pass the `classify` check. Step 5 remains, unchanged, "POS,
   Order, and Payment Foundation."

2. **DEC-0030 permitted Step 4 — and only Step 4.** It split the former single map by delivering
   step and permitted exactly four labels, leaving twenty-eight (later re-counted as thirty tokens
   across as many labels) owned by Step 5 or later, "forbidden unconditionally at Step 4."

The question this record answers is therefore narrow: which of the guard's forbidden feature labels
stop being forbidden now that the canonical current step reaches 5, and which stay forbidden.

## Options considered

**Option 1 — remove `STEP5_PLUS_FEATURE_TOKENS` entirely now that Step 5 is under way.**
Rejected outright, for the same reason DEC-0030 rejected removing its predecessor. The map does not
mean "Step 5 features"; it means "features belonging to Step 5 **or later**". Twenty-three of its
thirty labels are Step 6+ scope — production, washing, drying, finishing, quality control, rework,
tracking, WhatsApp, pickup, delivery, courier settlement, the reminder ladder, storage fees,
receivables aggregation, finance reports, loyalty, and subscription billing. Deleting the map to
unblock seven labels would silently unblock all of them at exactly the moment the repository first has
order and payment runtime for forward leak to be tempting.

**Option 2 — add per-file suppressions or an allowlist of specific Step 5 paths.**
Rejected. Suppressions accumulate, are granted under deadline pressure, and are invisible in
aggregate; and they make the guard's answer depend on where a file sits rather than on what it is —
the property that made naive prose matching unusable in the first place.

**Option 3 — split the map again by delivering step and gate it on the canonical current step.**
**Adopted.** The seven labels Step 5 delivers move into a `STEP5_FEATURE_TOKENS` set that is permitted
once the canonical current step reaches 5. Everything else moves into `STEP6_PLUS_FEATURE_TOKENS` and
stays forbidden. The boundary is derived from `_common.CANONICAL_CURRENT_STEP`, so it advances only
when a step actually starts under its own authorisation — never as a side effect of an edit to the
guard. This is the identical mechanism DEC-0030 used, and using it again rather than inventing a new
one is deliberate.

## Decision

1. **Exactly seven feature labels become permitted, effective from canonical step 5:** `POS`,
   `order`, `laundry intake`, `payment`, `refund`, `QRIS`, and `receipt`. Each traces to a Step 5
   requirement: order and POS intake (FR-048 … FR-051, FR-053, FR-057 … FR-060), the nota
   (FR-052 → `receipt`), payment methods and gateway verification (FR-061 … FR-064, FR-068, FR-069),
   and refund/void with reason (FR-065 … FR-067). No token in any of the seven sets is changed.

2. **Every other label remains forbidden and is moved into `STEP6_PLUS_FEATURE_TOKENS`**, with no
   change to its tokens: production, washing, drying, finishing, quality control, rework, tracking
   portal, tracking token, WhatsApp, notification provider, pickup, delivery, courier routing, proof
   of delivery, courier settlement, unclaimed laundry, reminder ladder, storage fee, receivables,
   finance reports, loyalty, commercial membership, and subscription billing.

3. **`receipt` becomes permitted while `production` begins the retained band, and the distinction is
   deliberate.** FR-052 authorises the nota — the document an order's captured prices are printed on.
   Producing the laundry the order describes (washing, drying, finishing, quality control) is Step 6
   and stays rejected. A `receipts`, `nota`, or `struk` table is now Step 5 runtime; a
   `production_jobs` or `produksi` table is not.

4. **`receivables` stays forbidden, and FR-070 does not require it to move.** Receivable tracking
   (FR-070) is a value DERIVED over orders and payments — the unpaid balance per order and customer,
   in integer Rupiah, read from authoritative records. It creates no `receivables`/`piutang` table of
   its own; the finance-reports aggregate that would is Step 10. Permitting `order` and `payment` is
   sufficient for FR-070, so `receivables` is left in the forbidden band.

5. **The boundary is derived, never hardcoded.** The guard reads `_common.CANONICAL_CURRENT_STEP`,
   raised from 4 to 5 in this same change. Below 5 the Step 5 labels remain forbidden exactly as
   before, so this record cannot retroactively permit anything in a Step 0–4 tree, and Step 6's own
   labels cannot be unblocked by editing this guard — they need Step 6's own authorisation and its own
   record.

6. **Nothing else about the guard is relaxed.** Approved runtime roots are unchanged. No runtime
   language is added. Deployment-artifact detection, credential detection, personal-data detection,
   symlink-escape detection, and status-claim honesty checks are untouched. **Deployment remains
   `ABSENT` and is not authorized by any part of this record.**

7. **Structural detection remains structural.** Renaming a Step 6+ feature to evade token matching is
   the same violation as building it under its plain name (Rule 36, hard rule 4), and permitting seven
   labels does not soften that.

8. **The required CI status check context remains exactly `classify`** and is not renamed (Rule 36,
   hard rule 5).

9. **A permitted label is not an implemented feature.** `orders` ceasing to be a forbidden token means
   an `orders` table may now legally exist. It says nothing about whether one does, whether it is
   tenant-scoped, whether money is integer Rupiah, whether payment is idempotent, or whether any test
   passes. `classify` reports scope classification only and executes no application test (Rule 36,
   hard rule 6). The financial-integrity and tenant-isolation hard gates apply in full to every
   permitted label (Rule 04, Rule 48).

10. **The residual audits move with the boundary.** `validate-dec-0030-labels.py` is made
    canonical-step-aware so it no longer treats the seven now-permitted labels as forbidden from Step
    5 (a false failure on authorised runtime), and a new `validate-dec-0035-labels.py` audits the Step
    5 residual: each permitted label still traces to a PRD requirement, and every Step 6+ label stays
    structurally absent. No token protection is removed — it moves to the step-appropriate auditor.

## Consequences

Step 5 can build the POS, order, and payment foundation it was authorised to build, and the
forward-leak guard survives intact for Steps 6 through 14.

### Positive consequences

- The guard's strength against Step 6+ leakage is unchanged: twenty-three labels remain forbidden, now
  labelled by the step that owns them rather than lumped under a name that stopped being accurate.
- The permitted set is enumerable and reviewable in one place, rather than scattered across
  suppressions.
- Tying the boundary to `CANONICAL_CURRENT_STEP` means Step 6's own labels cannot be unblocked by
  editing this guard — they need Step 6's own authorization and its own record, exactly as Step 5
  needed this one and Step 4 needed DEC-0030.

### Negative consequences / trade-offs

- Seven labels of outright token protection are genuinely given up. If a Step 6 production table were
  named `orders`, the token check would no longer catch it — the tenant-isolation, financial-integrity,
  and review gates would have to. This record does not pretend that residual risk is zero; it is stated
  and accepted, and `validate-dec-0035-labels.py` narrows it by asserting no Step 6+ token appears in
  any structural position.
- `receipt` permitted while `production` is forbidden is a fine distinction a future contributor could
  get wrong in either direction. It is written down here and covered by the residual audit, but it is a
  sharp edge — the same shape as `printer` vs `receipt` was under DEC-0030.
- The guard is now conditional in three bands rather than two. A conditional guard is one a reader can
  misread; the residual auditors and the adversarial fixtures are the compensating control, and they
  are load-bearing rather than supplementary.

## Verification

Verified on `feature/step-05-pos-order-payment-foundation`, branched from the post-Step-4 canonical
`main` at `d18602950034973b6f2bdeef107d146e940450e8`:

- `scripts/validate-runtime-scope.py` carried `STEP5_PLUS_FEATURE_TOKENS` with the thirty labels, and
  rejected every Step 5 table before this record. After the split it classifies the current tree
  `STEP_3_RUNTIME_FOUNDATION_WITHIN_SCOPE` (5/5 checks) with `CANONICAL_CURRENT_STEP = 5`.
- Rule 36 hard rule 8 and DEC-0030's supersession policy were read directly and are quoted above.
- FR-048 … FR-070 were read from `docs/product/PRODUCT_REQUIREMENTS.md` and are the sole basis for
  which seven labels move; `validate-dec-0035-labels.py` re-asserts that tracing on every run.
- `validate-dec-0030-labels.py` (made step-aware) and `validate-dec-0035-labels.py` both pass at
  `CANONICAL_CURRENT_STEP = 5`.

The adversarial-fixture result demonstrating that each of the twenty-three retained labels is still
rejected, and that the seven permitted labels are accepted, is recorded in the Step 5 evidence pack
under `evidence/step-05/` bound to the exact commit it was produced from (Rule 01, DEC-0013). This
record quotes no result it did not produce.

## Requirement references

FR-048 … FR-060 (order and POS intake, nota, cancellation, audit), FR-061 … FR-069 (payment methods,
idempotency, gateway verification, refund/void, reversal, serialised money operations, financial audit
trail), FR-070 (receivable tracking, derived). No requirement is created, changed, or withdrawn; this
record only permits the runtime that already-existing requirements call for.

## Threat references

The threat this record manages is scope leakage: Step 6+ business functionality entering the tree
under Step 5's authorization, either by plain naming or by renaming to evade detection. Mitigated by
retaining all twenty-three Step 6+ labels, by deriving the boundary from `CANONICAL_CURRENT_STEP`
rather than from an edit to the guard, and by adversarial fixtures over both the permitted and the
retained sets. Residual risk is stated in the trade-offs above and is accepted, not eliminated.

## Rule references

- Rule 36 — runtime architecture and scope; hard rule 4 (structural detection), hard rule 5
  (`classify` never renamed), hard rule 6 (presence is not correctness), hard rule 8 (widening requires
  a decision record naming what it supersedes).
- Rule 42 — Step 4+ backend scope; Step 6+ features remain `NOT IMPLEMENTED`.
- Rule 02 / Rule 39 / Rule 48 — tenant isolation; permitting a table never relaxes tenant scoping.
- Rule 04 / Rule 18 — financial integrity; order and payment money is integer Rupiah, payments are
  idempotent, financial records are append-only, historical prices are immutable.
- Rule 19 — the canonical order status lifecycle; enforcement begins in this step and Step 6.
- Rule 47 — adversarial validator testing before a gate is relied upon.
- Rule 49 / Rule 50 — step status; the canonical roadmap authorises Step 5 and nothing later.

## Supersession policy

This record supersedes **only** the Step 5 portion of DEC-0030's runtime scope split — the seven
labels it moves from `STEP5_PLUS_FEATURE_TOKENS` to `STEP5_FEATURE_TOKENS`. Every other part of
DEC-0030 and DEC-0024 remains in force unchanged: the four Step 4 labels stay permitted, the approved
runtime roots are untouched, the guard-versioning split between `validate-runtime-scope.py` and
`validate-no-runtime.py` stands, the Step 0–2 guard boundary remains immutable, and the `classify`
check name is unchanged.

Permitting any further feature label requires a new accepted decision record naming this one, and the
step that owns the label must itself have been authorised first. **Editing `STEP6_PLUS_FEATURE_TOKENS`
to unblock work is a governance breach, not a fix** (Rule 00, Rule 23). Narrowing the guard — moving a
label back to forbidden, or adding tokens to an existing label — needs no record, exactly as Rule 36
hard rule 8 provides.

This record does not renumber, replace, reinterpret, or expand the canonical roadmap. Step 5 remains
"POS, Order, and Payment Foundation"; DEC-0035 is only the auditable governance mechanism that
transitions the previously forbidden order/payment/POS runtime tokens into an explicitly Step-5-gated,
testable state.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §6 — architecture and the locked backend stack.
- §16 — financial integrity.
- §24 — Roadmap; the Step 5 entry and the roadmap lock.
- §31 — Decision records.
- §32 — Changelog.
