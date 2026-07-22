# DEC-0030 — Step 4 Runtime Scope Transition

**ID:** DEC-0030
**Title:** Step 4 Runtime Scope Transition
**Status:** ACCEPTED
**Date:** 21 July 2026

---

## Context

`scripts/validate-runtime-scope.py` refuses, by construction, to let a Step 4 business table exist. It
carries a `STEP4_FEATURE_TOKENS` map and rejects any migration filename, `Schema::create` argument,
route path segment, Eloquent model class name, or module directory whose identifier matches one of
those tokens. Among them:

```
"service catalog":     {"service_catalog", "servicecatalog", "katalog_layanan"}
"price list":          {"price_list", "pricelist", "daftar_harga", "price_lists"}
"customer management": {"customers", "customer_profiles", "pelanggan"}
"printer":             {"printers", "printer_settings"}
```

That is exactly what Step 4 must build. `verify-step-03.sh` also runs a `schema scope (no Step 4+
table)` gate against the live database. The guard is not an obstacle to work around — it is doing
precisely the job DEC-0024 gave it, and it will keep doing that job until a decision record moves the
boundary.

**Rule 36, hard rule 8 governs how that boundary moves:** the scope guard "may only be narrowed, never
silently widened. Making the guard stricter needs no decision record. Widening the approved runtime
roots, adding a runtime language, or authorizing deployment requires its own decision record naming
what it supersedes." DEC-0024's supersession policy says the same. This record is that decision
record.

Two facts bound what it may do.

1. **DEC-0024 authorized Step 3 foundation runtime only**, and says so explicitly: "It does not
   authorize Step 4 or later business features, and it does not authorize deployment. A later step that
   wants to widen scope needs its own decision record; DEC-0024 does not cover it in advance."

2. **DEC-0028 authorized Step 4 — and only Step 4.** Its scope is the canonical Step 4 scope: customer
   master data, service master data, per-brand price lists, outlet master data, and staff and role
   assignment within a tenant, delivering FR-021 … FR-047.

The question this record answers is therefore narrow: which of the guard's forbidden feature labels
stop being forbidden, and which stay forbidden.

## Options considered

**Option 1 — remove `STEP4_FEATURE_TOKENS` entirely now that Step 4 is authorised.**
Rejected outright. The map does not mean "Step 4 features"; it means "features belonging to Step 4 **or
later**". Twenty-eight of its thirty-two labels are Step 5+ scope — orders, payments, QRIS, production,
tracking tokens, WhatsApp, pickup, delivery, courier settlement, the reminder ladder, receivables,
finance reports, loyalty. Deleting the map to unblock four labels would silently unblock all of them
and destroy the forward-leak guarantee at the exact moment the repository first has enough runtime for
forward leak to be tempting.

**Option 2 — add per-file suppressions or an allowlist of specific Step 4 paths.**
Rejected. Suppressions accumulate, are granted under deadline pressure, and are invisible in aggregate:
nobody reviews the total surface a suppression list has opened. It also makes the guard's answer depend
on where a file sits rather than on what it is, which is the property that made naive prose matching
unusable in the first place.

**Option 3 — split the map by delivering step and gate it on the canonical current step.**
**Adopted.** The four labels that Step 4 delivers move into a `STEP4_FEATURE_TOKENS` set that is
permitted once the canonical current step reaches 4. Everything else moves into
`STEP5_PLUS_FEATURE_TOKENS` and stays forbidden. The boundary is derived from
`_common.CANONICAL_CURRENT_STEP`, so it advances only when a step actually starts under its own
authorization — never as a side effect of an edit to the guard.

## Decision

1. **Exactly four feature labels become permitted, effective from canonical step 4:**
   `service catalog`, `price list`, `customer management`, and `printer`. Each traces to a Step 4
   requirement: FR-031 … FR-033 and FR-040 (service catalog), FR-034 … FR-040 (price lists),
   FR-021 … FR-030 (customer management), and FR-045 (printer configuration).

2. **Every other label remains forbidden and is moved into `STEP5_PLUS_FEATURE_TOKENS`**, with no
   change to its tokens: POS, order, laundry intake, payment, refund, QRIS, receipt, production,
   washing, drying, finishing, quality control, rework, tracking portal, tracking token, WhatsApp,
   notification provider, pickup, delivery, courier routing, proof of delivery, courier settlement,
   unclaimed laundry, reminder ladder, storage fee, receivables, finance reports, and loyalty.

3. **`receipt` stays forbidden while `printer` is permitted, and the distinction is deliberate.** FR-045
   authorises an outlet to *register printer configuration*; the nota itself is FR-052, which is Step 5.
   A `printers` or `outlet_printers` table is Step 4 master data. A `receipts`, `nota`, or `struk` table
   is not, and is still rejected.

4. **The boundary is derived, never hardcoded.** The guard reads `_common.CANONICAL_CURRENT_STEP`. Below
   4 the Step 4 labels remain forbidden exactly as before, so this record cannot retroactively permit
   anything in a Step 0–3 tree.

5. **Nothing else about the guard is relaxed.** Approved runtime roots are unchanged. No runtime
   language is added. Deployment-artifact detection, credential detection, personal-data detection,
   symlink-escape detection, and status-claim honesty checks are untouched. **Deployment remains
   `ABSENT` and is not authorized by any part of this record.**

6. **Structural detection remains structural.** Renaming a Step 5+ feature to evade token matching is
   the same violation as building it under its plain name (Rule 36, hard rule 4), and permitting four
   labels does not soften that.

7. **The required CI status check context remains exactly `classify`** and is not renamed (Rule 36,
   hard rule 5).

8. **A permitted label is not an implemented feature.** `customers` ceasing to be a forbidden token
   means a `customers` table may now legally exist. It says nothing about whether one does, whether it
   is tenant-scoped, or whether any test passes. `classify` reports scope classification only and
   executes no application test (Rule 36, hard rule 6).

## Consequences

Step 4 can build the master data it was authorised to build, and the forward-leak guard survives intact
for Steps 5 through 14.

### Positive consequences

- The guard's strength against Step 5+ leakage is unchanged: twenty-eight labels remain forbidden, and
  they are now labelled by the step that owns them rather than lumped under a name that stopped being
  accurate.
- The permitted set is enumerable and reviewable in one place, rather than scattered across
  suppressions.
- Tying the boundary to `CANONICAL_CURRENT_STEP` means Step 5's own labels cannot be unblocked by
  editing this guard — they need Step 5's own authorization and its own record, exactly as Step 4
  needed DEC-0028 and this one.

### Negative consequences / trade-offs

- The guard's most valuable property so far was that it forbade **all** business features, which is
  simple to reason about and impossible to argue with. It is now conditional, and a conditional guard
  is a guard someone can misread. The adversarial fixtures are the compensating control, and they are
  load-bearing rather than supplementary.
- `printer` being permitted while `receipt` is forbidden is a genuinely fine distinction that a future
  contributor could get wrong in either direction. It is written down here and covered by a fixture,
  but it is a sharp edge.
- Four labels of protection are genuinely given up. If a Step 5 order table were named `customers`, the
  token check would no longer catch it — the tenant-isolation and review gates would have to. This
  record does not pretend that residual risk is zero.
- Adding a Step 4 feature the four labels do not cover will require extending the permitted set, and
  the temptation will be to edit the map rather than write a record. The supersession policy below
  exists to make that the wrong move.

## Verification

Verified on `feature/step-04-laundry-master-data`, branched from
`1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`:

- `scripts/validate-runtime-scope.py` carries `STEP4_FEATURE_TOKENS` with the four labels quoted in
  Context, and `verify-step-03.sh` runs a `schema scope (no Step 4+ table)` gate against the live
  database. Both were confirmed to reject Step 4 tables before this record.
- Rule 36 hard rule 8 and DEC-0024's supersession policy were read directly and are quoted above.
- FR-021 … FR-047 were read from `docs/product/PRODUCT_REQUIREMENTS.md` §15.3–§15.5 and are the sole
  basis for which four labels move.

The adversarial-fixture result demonstrating that each of the twenty-eight retained labels is still
rejected, and that the four permitted labels are accepted, is recorded in the Step 4 evidence pack
under `evidence/step-04/` bound to the exact commit it was produced from (Rule 01, DEC-0013). This
record quotes no result it did not produce.

## Requirement references

FR-021 … FR-030 (customer management), FR-031 … FR-033 and FR-040 (service catalog), FR-034 … FR-040
(per-brand price lists), FR-045 (printer configuration). No requirement is created, changed, or
withdrawn; this record only permits the runtime that already-existing requirements call for.

## Threat references

The threat this record manages is scope leakage: Step 5+ business functionality entering the tree
under Step 4's authorization, either by plain naming or by renaming to evade detection. Mitigated by
retaining all twenty-eight Step 5+ labels, by deriving the boundary from `CANONICAL_CURRENT_STEP`
rather than from an edit to the guard, and by adversarial fixtures over both the permitted and the
retained sets. Residual risk is stated in the trade-offs above and is accepted, not eliminated.

## Rule references

- Rule 36 — runtime architecture and scope; hard rule 4 (structural detection), hard rule 5
  (`classify` never renamed), hard rule 6 (presence is not correctness), hard rule 8 (widening requires
  a decision record naming what it supersedes).
- Rule 42 — Step 4+ backend scope; Step 5+ features remain `NOT IMPLEMENTED`.
- Rule 02 / Rule 39 / Rule 48 — tenant isolation; permitting a table never relaxes tenant scoping.
- Rule 04 — financial integrity; price-list money is integer Rupiah regardless of this record.
- Rule 47 — adversarial validator testing before a gate is relied upon.
- Rule 49 / Rule 50 — step status; DEC-0028 authorised Step 4 and nothing later.

## Supersession policy

This record supersedes **only** the Step 4 portion of DEC-0024's runtime scope. Every other part of
DEC-0024 — approved runtime roots, the guard-versioning split between
`validate-runtime-scope.py` and `validate-no-runtime.py`, the immutability of the Step 0–2 guard
boundary, and the `classify` check name — remains in force unchanged.

Permitting any further feature label requires a new accepted decision record naming this one, and the
step that owns the label must itself have been authorised first. **Editing `STEP5_PLUS_FEATURE_TOKENS`
to unblock work is a governance breach, not a fix** (Rule 00, Rule 23). Narrowing the guard — moving a
label back to forbidden, or adding tokens to an existing label — needs no record, exactly as Rule 36
hard rule 8 provides.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §6 — architecture and the locked backend stack.
- §16 — financial integrity.
- §24 — Roadmap; the Step 4 entry and the roadmap lock.
- §31 — Decision records.
- §32 — Changelog.
