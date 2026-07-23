# DEC-0036 — Order-Line Rounding Mode: HALF_UP (OQ-017 Ratification)

**ID:** DEC-0036
**Title:** Order-Line Rounding Mode is HALF_UP (OQ-017 Ratification)
**Status:** ACCEPTED
**Date:** 23 July 2026

---

## Context

FR-038 requires that where a money computation could produce a fractional Rupiah, the rounding rule be
**explicit and applied at one defined point** — not left to a language default. It does not name a
canonical mode, and neither does the Master Source. Step 5's order-line pricing is the first place the
product actually rounds: a per-kilogram price times a fractional weight (for example Rp7.333/kg ×
2.5 kg = 18332.5) lands between two whole Rupiah and must resolve to one.

The Step 5 foundation implemented this through the single rounding point
[`RupiahRounding`](../../backend/app/Modules/SharedKernel/Money/RupiahRounding.php), selected by
[`OrderPricing::ROUNDING_MODE`](../../backend/app/Modules/Ordering/Support/OrderPricing.php), and used
**HALF_UP** — the conventional Indonesian retail rounding — as a single, named, changeable constant.
Because the Master Source did not fix a mode, that choice was recorded as an **open question**
([OQ-017](../product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md)) and flagged as provisional rather than
presented as settled: choosing a mode and hiding it would have silently settled an owner question
(Rule 00 hard rule 6).

The repository owner has now decided OQ-017.

## Options considered

- **HALF_UP** (round half away from zero). Adopted. The conventional expectation at an Indonesian
  retail counter; the customer-facing half-Rupiah always resolves in the direction the counter and
  customer both expect.
- **HALF_EVEN** (banker's rounding). Rejected as the canonical retail mode: it is designed to remove
  bias across a long series of accounting entries, not to match a single counter transaction's
  expectation, and it surprises a cashier who expects 22.5 to become 23.
- **TOWARD_ZERO / AWAY_FROM_ZERO**. Rejected: neither is a "half" rule, and both are already available
  in `RupiahRounding` for any future computation that genuinely needs them.

## Decision

1. **The canonical order-line rounding mode is `HALF_UP`.** This RATIFIES the existing provisional
   implementation; it changes no code behaviour and introduces no new Step 5 feature. OQ-017 is
   thereby resolved.

2. **The rounding boundary and calculation order already implemented stand.** Rounding happens once,
   at `RupiahRounding::scale()`, at the point a fractional line gross is resolved to whole Rupiah —
   before any line or order discount is subtracted. Repository truth was checked against the canonical
   money rules (Rule 04, FR-037, FR-038) and does not violate them, so it is preserved unchanged.

3. **The integer-Rupiah financial model is preserved.** Money is stored and computed as integer
   Rupiah; fractional QUANTITIES (a kiloan weight) are carried as integer thousandths
   (`quantity_milli`) and combined with the price by exact integer arithmetic
   (`amount × numerator`, `intdiv`, and an exact integer remainder). **No floating-point arithmetic is
   introduced anywhere in any money path** (Rule 04 hard rule 2); "exact decimal for fractional
   quantities" is satisfied by this exact integer representation, which loses nothing.

4. **This decision is bounded to order-line rounding.** It does not create or change any pricing, tax,
   discount, or accounting policy, and it does not authorise any new feature. A later step that
   introduces tax or a percentage-discount engine records its own rounding treatment if it needs one;
   `RupiahRounding` already requires an explicit mode at every call site, so no default leaks this
   decision outward.

5. **The mode remains a single, named, changeable constant** (`OrderPricing::ROUNDING_MODE`). Changing
   it in future requires a superseding decision record, exactly as changing it required this one.

## Consequences

OQ-017 is closed. The order-total rounding a customer sees is now a ratified, tested product decision
rather than a flagged provisional default.

### Positive consequences

- The rounding a cashier and customer experience matches the conventional Indonesian retail
  expectation, and it is now written down and covered by boundary tests immediately below, exactly at,
  and immediately above the half-Rupiah threshold.
- The choice stays isolated to one constant and one rounding point, so it can neither drift across call
  sites nor silently affect a future tax or discount computation.

### Negative consequences / trade-offs

- HALF_UP is very slightly biased upward across a long series of exact-half results compared with
  HALF_EVEN. For counter-level laundry pricing this is immaterial and is the accepted trade-off for
  matching the counter's expectation; a future accounting-grade computation may choose HALF_EVEN under
  its own record.

## Verification

Verified on `feature/step-05-pos-order-payment-foundation`:

- `OrderPricing::ROUNDING_MODE === RupiahRounding::HALF_UP`, applied only through `RupiahRounding`,
  whose every operation is integer and overflow-checked (no float, Rule 04).
- `tests/Unit/Ordering/OrderPricingTest.php` covers the half-Rupiah threshold below (1.499 → 1), at
  (1.500 → 2), and above (1.501 → 2), plus an exact-half case (22.5 → 23) that a HALF_EVEN mode would
  round to 22 — locking the mode by test.
- `scripts/validate-money-rules.py` confirms no float/decimal money column exists in the corpus.

Executed output for these is captured in the Step 5 evidence pack bound to the exact commit it was
produced from (Rule 01, DEC-0013). This record quotes no result it did not produce.

## Requirement references

FR-037 (integer Rupiah), FR-038 (explicit rounding rule at one defined point), FR-051
(server-authoritative totals). No requirement is created, changed, or withdrawn; this record only
fixes the rounding mode those requirements left to an owner decision.

## Rule references

- Rule 04 — financial integrity; integer Rupiah, no floating point in any money path.
- Rule 00 hard rule 6 — an open question is not closed by invention; it is decided by the owner.
- Rule 18 invariant 10 — money is integer Rupiah.

## Supersession policy

This record supersedes nothing. It resolves OQ-017 and fixes a value the requirements deliberately left
open. It does not touch DEC-0035 (the Step 5 runtime scope transition) or any other decision. Changing
the order-line rounding mode in future requires a new accepted decision record naming this one; editing
`OrderPricing::ROUNDING_MODE` without such a record is a governance breach (Rule 00).

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §16 — financial integrity.
- §24 — Roadmap; Step 5.
- §31 — Decision records.
- §32 — Changelog.
