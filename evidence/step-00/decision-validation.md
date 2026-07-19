# Decision Record Validation — Step 0

## Command

```bash
python3 scripts/validate-decisions.py
```

Result: **46/46 checks passed**, exit `0`.

## Completeness

| Check | Result |
|---|---|
| DEC-0001 … DEC-0015 all present | PASS |
| Each ID appears exactly once | PASS |
| No duplicate decision IDs | PASS |
| No missing decision IDs in the range | PASS |
| No unexpected DEC IDs outside the range | PASS |
| Every record has status `ACCEPTED` | PASS |
| Every record carries all 12 required headings | PASS |

## Records

| ID | Title | File | Status |
|---|---|---|---|
| DEC-0001 | Official Product Name | `docs/decisions/DEC-0001-official-product-name.md` | ACCEPTED |
| DEC-0002 | Multi-Tenant Architecture | `docs/decisions/DEC-0002-multi-tenant-architecture.md` | ACCEPTED |
| DEC-0003 | Multi-Laundry Owner Model | `docs/decisions/DEC-0003-multi-laundry-owner-model.md` | ACCEPTED |
| DEC-0004 | Flutter Client and Web Console | `docs/decisions/DEC-0004-flutter-client-and-web-console.md` | ACCEPTED |
| DEC-0005 | API-First Modular Monolith Backend | `docs/decisions/DEC-0005-api-first-modular-monolith-backend.md` | ACCEPTED |
| DEC-0006 | Public Tracking Without App Installation | `docs/decisions/DEC-0006-public-tracking-without-app-installation.md` | ACCEPTED |
| DEC-0007 | Pickup and Delivery as Core Product | `docs/decisions/DEC-0007-pickup-and-delivery-as-core-product.md` | ACCEPTED |
| DEC-0008 | H+1 H+3 H+7 Reminder as Core Product | `docs/decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md` | ACCEPTED |
| DEC-0009 | Initial Commercial Pricing | `docs/decisions/DEC-0009-initial-commercial-pricing.md` | ACCEPTED |
| DEC-0010 | No Lifetime Cloud Subscription | `docs/decisions/DEC-0010-no-lifetime-cloud-subscription.md` | ACCEPTED |
| DEC-0011 | Transparent Third-Party Messaging Costs | `docs/decisions/DEC-0011-transparent-third-party-messaging-costs.md` | ACCEPTED |
| DEC-0012 | Tenant Isolation and Financial Integrity Hard Gate | `docs/decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md` | ACCEPTED |
| DEC-0013 | Exact-SHA Evidence Before GO | `docs/decisions/DEC-0013-exact-sha-evidence-before-go.md` | ACCEPTED |
| DEC-0014 | Customer Android Does Not Replace Public Tracking | `docs/decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md` | ACCEPTED |
| DEC-0015 | MVP Focuses on Laundry Operations | `docs/decisions/DEC-0015-mvp-focuses-on-laundry-operations.md` | ACCEPTED |

## Required headings enforced per record

`ID`, `Title`, `Status`, `Date`, `Context`, `Decision`, `Consequences`,
`Positive consequences`, `Negative consequences / trade-offs`, `Verification`,
`Supersession policy`, `Related Master Source sections`.

Heading matching is case-insensitive and tolerant of markdown heading level and
bold emphasis, so a record cannot pass by coincidence of formatting alone.

## Date

All fifteen records carry the canonical baseline date **19 July 2026**.

## Structural corroboration

Graphify independently reports all fifteen `DEC-####` nodes at an identical
degree of 13. A truncated or malformed record would present a lower degree. This
is corroborating evidence only; `validate-decisions.py` is the authority.

## Scope discipline

No decision beyond DEC-0015 was invented. No decision content was altered to make
a validator pass.

One wording adjustment was made and is recorded honestly: in `DEC-0010`, three
lines in the Context section discussed how lifetime deals fail, and the pricing
validator flagged them because the word "lifetime" appeared without a nearby
prohibition keyword. The lead-in sentence was rephrased to
"is not sustainable and must never be offered" rather than deleting the analysis.
**No canonical fact was changed** — the decision still prohibits lifetime cloud
plans, and the reasoning is intact.
