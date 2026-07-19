# Step 1 Evidence Pack — Product Requirement and Domain Model

**Step:** 1 — Product Requirement and Domain Model
**Status:** `GO WITH ACCEPTED DEVIATION` — conferred by the repository owner, 19 July 2026
**Tagged commit:** `4eadbc73f8bacdc9cd2acfcc62280ac932116089`
**Accepted deviation:** single-maintainer governance, no independent human review (DEC-0017)
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
| [`post-tag-evidence.md`](post-tag-evidence.md) | Tag facts, the ten independent pre-tag verifications, and the ruleset state at tag time |
| [`final-closure.md`](final-closure.md) | The Step 1 closure record |

## Governance mode

**Independent human approval is `ABSENT`.** Governance operates in single-maintainer mode
(DEC-0016). The compensating controls are the active ruleset, exact-SHA CI, deterministic validators,
and recorded internal re-verification.

**Internal re-verification is not peer review and is never described as such** anywhere in this pack.

## `GO`

`GO` is conferred by the repository owner and is **never** self-declared by an agent (Rule 01).

The owner conferred **`GO WITH ACCEPTED DEVIATION`** for Step 1 on 19 July 2026, against exact-SHA
evidence, after instructing that the authorisation text itself **not** be relied upon. Ten independent
verifications were re-run directly against the repository and the GitHub API before the tag was created;
they are recorded in [`post-tag-evidence.md`](post-tag-evidence.md).

The accepted deviation is **single-maintainer governance with no independent human review**
([DEC-0017](../../docs/decisions/DEC-0017-single-maintainer-approval-standing-deviation.md)). Step 1 GO
means every technical and governance gate passed **with that requirement deliberately deviated from and
documented** — not that it was met.
