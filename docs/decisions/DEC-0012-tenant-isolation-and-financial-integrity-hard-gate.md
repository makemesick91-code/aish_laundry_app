# DEC-0012 — Tenant Isolation and Financial Integrity Hard Gate

## ID

DEC-0012

## Title

Tenant Isolation and Financial Integrity Hard Gate

## Status

ACCEPTED

## Date

19 July 2026

## Context

Most software defects are negotiable. A layout bug ships and is fixed next week. A slow report is
tolerated until someone complains. Ordinary engineering runs on triage, and triage is correct for almost
everything.

Two categories of defect in Aish Laundry App are not like that.

**Cross-tenant data exposure.** The product deliberately places competing laundry businesses in one
shared database (DEC-0002). A leak between two tenants discloses one business's customer list, pricing,
and revenue to a direct competitor. It cannot be undone, cannot be adequately apologised for, and in a
market that runs on personal trust it would end the product. There is no exposure small enough to
tolerate and no window short enough to accept.

**Financial integrity failure.** Laundry businesses operate on thin margins and substantial cash. A
double charge, a lost payment, a silently overwritten reconciliation, or a historical nota whose price
changes destroys the owner's ability to trust any number the product reports. Money defects also
compound: a duplicate created today corrupts tonight's shift close, tomorrow's customer relationship, and
the monthly report permanently.

Both categories share a property that makes ordinary triage dangerous: they are usually invisible until
they are catastrophic. Nobody notices a missing tenant filter until the wrong customer list appears on
someone's screen.

The specific risk this decision addresses is schedule pressure. Aish Laundry App is built largely by
autonomous agents against a defined roadmap. Without an explicit, unwaivable rule, an agent or a human
under deadline pressure will eventually reason that a "minor" isolation gap or a "rare" duplicate-payment
path can be fixed in the next Step.

## Decision

**Tenant isolation and financial integrity are hard gates. A failure in either is an automatic NO-GO.**

1. **Cross-tenant data exposure is an automatic NO-GO.**
2. **Any financial integrity failure is an automatic NO-GO.**

A NO-GO on either gate:

- **blocks merge** — the pull request does not merge;
- **blocks release** — nothing ships;
- **blocks tagging** — no GO tag is created;
- **is never waived**, never deferred to a later Step, never downgraded to `WATCH` for convenience, and
  never traded against a deadline.

The gates are unconditional. They apply regardless of how much other work is complete, how close a
deadline is, how rare the failure path is believed to be, or how commercially costly the delay is.

**Detailed policies:**

- [`../governance/TENANT_ISOLATION_POLICY.md`](../governance/TENANT_ISOLATION_POLICY.md) — the thirteen
  hard rules, implementation requirements, mandatory test suite, and incident response.
- [`../governance/FINANCIAL_INTEGRITY_POLICY.md`](../governance/FINANCIAL_INTEGRITY_POLICY.md) — integer
  Rupiah, idempotency, server-side callback verification, reversal-only corrections, price-history
  immutability, cash reconciliation, mandatory test suite, and incident response.

**Mandatory verification.** From the Step that introduces the relevant capability, a dedicated
always-run test suite exists for each gate, and a failure fails the build. Every evidence pack carries an
explicit attestation for both gates.

## Consequences

Two test suites become permanent, always-run, non-skippable parts of CI: tenant isolation from Step 3 and
financial integrity from Step 5. The Definition of Done includes both gates for every Step
([`../DEFINITION_OF_DONE.md`](../DEFINITION_OF_DONE.md) §1.4). Every evidence pack from those Steps
onward carries a gate attestation ([`../governance/EVIDENCE_POLICY.md`](../governance/EVIDENCE_POLICY.md)
§7). AI agents are required to stop and report NO-GO when either gate is at risk
([`../AI_EXECUTION_POLICY.md`](../AI_EXECUTION_POLICY.md) §5). Incident response for both categories is
specified in advance, so that a real incident is handled by a plan rather than by improvisation.

## Positive consequences

- Converts the two catastrophic risk categories from matters of judgement into matters of fact, removing
  them from negotiation entirely.
- Gives every contributor — human or agent — explicit permission and an explicit obligation to stop, which
  is the hardest thing to do under deadline pressure without a rule to point at.
- Makes the mandatory test suites non-negotiable, so isolation and money coverage cannot erode as scope
  grows.
- Supports the pricing guardrails: tenant isolation is not an add-on and cannot become one (§21.4).
- Makes the product's trustworthiness demonstrable to a prospective tenant with evidence rather than
  assurance.
- Protects the owner from the two failure modes most likely to end the business.

## Negative consequences / trade-offs

- **Schedule risk is real and intentional.** A gate failure discovered late stops a release entirely.
  That is the mechanism working, and it will be painful at least once.
- **Both suites grow with every endpoint**, and maintaining them is a permanent, non-negotiable tax on
  development speed.
- **False positives are costly.** A flaky isolation test blocks a merge; the correct response is to fix
  the flakiness, never to weaken or quarantine the assertion, which makes flakiness expensive to resolve.
- **Rigidity in edge cases.** A theoretical exposure with no practical exploit path still triggers the
  gate, and there is no mechanism to accept residual risk.
- **The definition of "financial integrity failure" is broad**, covering a float in a money path even
  where no money was lost, which will occasionally feel disproportionate.
- **Cross-tenant analytics and cross-tenant portfolio consolidation are foreclosed** without an explicit
  new decision, which removes some genuinely attractive product options (DEC-0003).

## Verification

- **Tenant isolation suite**, mandatory from Step 3: creates multiple tenants with deliberately colliding
  data, exercises every business endpoint cross-tenant, asserts denial, asserts non-disclosure of the
  other tenant's existence, and covers search, export, files, caches, background jobs, and tenant
  switching.
- **Financial integrity suite**, mandatory from Step 5: duplicate submission, concurrent submission,
  callback replay, invalid signature, mismatched amount, client-claimed payment, deletion attempt,
  unpermissioned refund, price-list mutation against historical orders, offline queue replay after crash,
  payment conflict surfacing, and static rejection of floating-point types in money paths.
- Both suites fail the build on any violation and may not be skipped or quarantined.
- Every evidence pack carries both attestations, with unedited test output.
- At the Step 0 baseline, the attestation is honest: **no runtime exists**, so neither gate can be
  exercised; both suites are `NOT APPLICABLE` and become mandatory at Step 3 and Step 5 respectively.

## Supersession policy

**This decision is not superseded by convenience, schedule, or commercial pressure.**

A superseding record would have to demonstrate that one of these categories is no longer catastrophic for
this product — for example, that tenants are no longer commercial competitors sharing infrastructure, or
that the product no longer handles money. Both are inconceivable while the product remains what §2
describes.

Weakening either gate requires a decision record signed by the repository owner that states the residual
risk explicitly, names who accepts it, and records what the affected tenants would be told. Requires a
**major** version bump of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §3 Product values
- §4 Multi-tenancy
- §15 Security
- §16 Financial integrity
- §25 Definition of Done
- §27 AI development rules
- §28 Testing
