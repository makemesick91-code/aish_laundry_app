# Step 1 Evidence Pack — Product Requirement and Domain Model

**Step:** 1 — Product Requirement and Domain Model
**Status:** `IN PROGRESS` (this pack is produced while the pull request is open)
**Master Source version:** 1.2.0
**Canonical policy:** [`../../docs/governance/EVIDENCE_POLICY.md`](../../docs/governance/EVIDENCE_POLICY.md)

---

## What this pack is

Verification output produced by the repository's own validators, bound to the **exact 40-character
commit SHA** it was produced from, per
[DEC-0013](../../docs/decisions/DEC-0013-exact-sha-evidence-before-go.md).

**Evidence produced at one SHA does not carry over to another SHA.** If the tree changed, the evidence
was re-run. A file in this directory that names SHA `A` says nothing about SHA `B`.

## What this pack is NOT

Step 1 is **documentation only**. This pack therefore contains **no** application test results, **no**
build output, **no** deployment record, and **no** UAT result — because none of those things exist:

| Item | Status |
|---|---|
| Application unit / widget / integration / end-to-end tests | `NOT APPLICABLE` |
| Application build | `NOT APPLICABLE` |
| Application CI | `NOT APPLICABLE` |
| Deployment | `ABSENT` |
| UAT | `NOT STARTED` |
| Backend runtime | `ABSENT` |
| Flutter workspace | `ABSENT` |
| All product features | `NOT IMPLEMENTED` |

**A written acceptance criterion is not a passed test.** Step 1 defines criteria; it executes none of
them, because there is nothing to execute them against. Any document in this pack that appeared to
claim otherwise would be a false claim under Rule 01 and must be corrected.

## Sanitisation

This repository is **PUBLIC** ([DEC-0016](../../docs/decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
Every file here is world-readable and permanently so.

- No secrets, credentials, tokens, OTP values, private keys, or provider configuration appear in this
  pack.
- No customer data, real phone numbers, names, or addresses appear in this pack.
- Absolute local paths may appear; the local directory name is not sensitive (ASSUMPTION-0001).
- Where redaction was necessary, the file states that redaction occurred.

## Contents

| File | What it records |
|---|---|
| [`validation-results.md`](validation-results.md) | Full `bash scripts/verify-step-01.sh` output at the candidate SHA |
| [`adversarial-validator-tests.md`](adversarial-validator-tests.md) | Proof that the validators actually fail on deliberately broken input |
| [`graphify-summary.md`](graphify-summary.md) | Traceability and orphan analysis across the Step 1 corpus |
| [`security-review.md`](security-review.md) | Internal security re-verification and findings by severity |
| [`tooling-report.md`](tooling-report.md) | Skills, subagents, MCP, and Limit Saver status — what was used and what was not |
| [`corpus-inventory.md`](corpus-inventory.md) | The Step 1 document set with counts, bound to the SHA |

## Governance mode

**Independent human approval is `ABSENT`.** Governance operates in single-maintainer mode
(DEC-0016). The compensating controls are the active ruleset, exact-SHA CI, deterministic validators,
and recorded internal re-verification.

**Internal re-verification is not peer review and is never described as such** anywhere in this pack.

## `GO`

`GO` is conferred by the repository owner and is never self-declared by an agent (Rule 01). Nothing in
this pack asserts `GO` for Step 1.
