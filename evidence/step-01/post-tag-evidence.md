# Post-Tag Evidence — Step 1

**Step:** 1 — Product Requirement and Domain Model
**Status:** `GO WITH ACCEPTED DEVIATION`
**Conferred by:** the repository owner, 19 July 2026, against exact-SHA evidence (DEC-0013)

This record is written **after** the GO tag was created and remotely verified. It records the tag facts
and the independent verifications performed **before** tagging.

---

## 1. The tag

| Property | Value |
|---|---|
| Tag name | `aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` |
| Reference | `refs/tags/aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` |
| Type | **annotated** (`tag`) |
| Tag object SHA | `faed53c7ed3c5c164e48c861ed065661f6461270` |
| **Peeled commit SHA** | **`4eadbc73f8bacdc9cd2acfcc62280ac932116089`** |
| Tagger date | `2026-07-19T11:56:29Z` |
| Remote | present on `origin` |

The peeled commit matches the SHA the owner authorised, exactly. **No other SHA was tagged.**

Verified independently through the GitHub API, not only through the local git client:

```
$ gh api repos/makemesick91-code/aish_laundry_app/git/ref/tags/aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go
{"ref":"refs/tags/aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go",
 "sha":"faed53c7ed3c5c164e48c861ed065661f6461270","type":"tag"}

$ gh api repos/makemesick91-code/aish_laundry_app/git/tags/faed53c7ed3c5c164e48c861ed065661f6461270
{"tag":"aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go",
 "type":"commit","peeled":"4eadbc73f8bacdc9cd2acfcc62280ac932116089",
 "tagger":"2026-07-19T11:56:29Z"}
```

**This tag is immutable.** It is never moved, deleted, recreated, or force-pushed.

## 2. Step 0 tag — unchanged

| Property | Value |
|---|---|
| Tag name | `aish-laundry-step-00-master-source-governance-v1.0.0-go` |
| Tag object SHA | `e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9` |
| Peeled commit SHA | `8494bc8543b9301351da6055337832597f1f2d9f` |

Both values match those recorded at Step 0 closure, locally and on the remote. The Step 0 tag was
**not** moved, deleted, or re-pointed by any operation in this Step.

## 3. Independent pre-tag verifications

The owner's authorisation explicitly instructed that its own text **not** be relied upon. Every item
below was re-verified directly against the repository and the GitHub API before the tag was created.

| # | Verification | Method | Result |
|---|---|---|---|
| 1 | Ruleset `19164588` state | GitHub API re-read | **PASS** — see §4 |
| 2 | `main` is exactly `4eadbc73…` | `git rev-parse origin/main` | **PASS** |
| 3 | All required checks success at that SHA | GitHub check-runs API | **PASS** — 9/9 |
| 4 | `bash scripts/verify-step-01.sh` | run in a fresh clone | **PASS** — 32/32 |
| 5 | Fresh checkout is clean | `git clone` + `git status --porcelain` | **PASS** — 0 dirty files |
| 6 | Step 0 tag unchanged | local `git tag` + `git ls-remote` | **PASS** |
| 7 | Master Source 1.2.0, checksum valid | header/footer + `sha256sum` | **PASS** |
| 8 | DEC-0017 present and ACCEPTED | file + `validate-decisions.py` | **PASS** — 52/52 |
| 9 | No open `CRITICAL` or `HIGH` findings | security review + validators | **PASS** — 0 and 0 |
| 10 | No Flutter, Laravel, database, deployment, or Step 2 runtime | `validate-no-runtime.py` + tree scan | **PASS** — none found |

Verification 4 and 5 were performed in a **fresh `git clone` of the public remote**, checked out at the
target SHA — not in the working tree — so nothing local could mask a missing or uncommitted file.

## 4. Ruleset at tag time

Applied by the repository owner. Re-read independently through the API before tagging:

| Property | Value |
|---|---|
| Ruleset ID | `19164588` (`main-branch-protection`) |
| Enforcement | **active** |
| Bypass actors | **0** |
| `strict_required_status_checks_policy` | **true** |
| Rule types | `deletion`, `non_fast_forward`, `pull_request`, `required_status_checks` |

Required status-check contexts — **9**:

`validate` · `Documentation / links` · `Required Gate` · `secret-scan` · `Workflow / actionlint` ·
`classify` · `product-requirements` · `domain-model` · `threat-model`

The three Step 1 contexts are now **enforced**, not merely reported. This closes finding **SR-09** in
[`security-review.md`](security-review.md).

**Note on how this was applied.** The destructive-operations guard **blocked** the agent's attempt to
update the ruleset via `gh api`, correctly: repository settings are owner territory (Rule 11 §21,
Rule 12), and the guard enforces that at execution time regardless of authorisation given in
conversation. The guard was **not** bypassed, weakened, or edited. The owner applied the change, and
the agent then re-read the result independently rather than accepting a report of it.

## 5. Verified state at the tagged commit

| Metric | Value |
|---|---|
| Governance gates | **32 / 32 PASS** |
| Required CI checks | **9 / 9 success** |
| Requirement identifiers | **498**, each defined once in its authoritative register |
| Requirement traceability | **498 / 498 traced (100%)**, **0 orphans** |
| Acceptance criteria citing a non-existent requirement | **0** |
| Threats | **50** — 11 `CRITICAL`, 23 `HIGH`, 14 `MEDIUM`, 1 `LOW`, 1 `INFORMATIONAL` |
| `CRITICAL`/`HIGH` threats with a mitigation | **34 / 34** |
| `CRITICAL`/`HIGH` threats referenced by an acceptance criterion | **34 / 34** |
| Adversarial validator mutations caught | **23 / 23** |
| Graphify (0.8.35, deterministic, no LLM) | 2353 nodes, 3391 links, **0 orphan nodes** |
| Step 1 documents | **48** (15,454 lines) |
| Governance rules | 25 |
| Decision records | 17 |
| Master Source | **1.2.0**, checksum `c68c8eda100d2eb0779e4680c2f19ad307281bf27b9f1be7234f3a2c75c24efb` |

## 6. The accepted deviation

**Single-maintainer governance — no independent human review.**

`MASTER_SOURCE.md` §25.1 item 12 requires a Step-closing pull request to be reviewed and approved by
someone other than the author. Under single-maintainer governance that person does not exist, so the
item **cannot be satisfied**. It is recorded as a standing accepted deviation in
[DEC-0017](../../docs/decisions/DEC-0017-single-maintainer-approval-standing-deviation.md).

Compensating controls: active ruleset with zero bypass actors · exact-SHA CI · deterministic validators
· adversarial validator testing · recorded internal re-verification.

**These are not equivalent to independent review**, and this record does not claim they are. A validator
checks what it was written to check. It cannot notice that a requirement is wrong, that a domain model
is subtly mis-shaped, or that a whole category of threat was never imagined. **A defect that both the
maintainer and the validators miss is not caught.** That residual risk is accepted, not eliminated.

**Step 1 GO therefore means** every technical and governance gate passed, **with the independent-approval
requirement deliberately deviated from and documented**. It does not mean that requirement was met.

## 7. What Step 1 did NOT deliver

Stated explicitly so that absence is not mistaken for completion:

- **No runtime of any kind.** No Flutter workspace, Laravel application, database, schema, migration,
  API endpoint, screen, deployment, or dependency manifest.
- **No application tests.** Step 1 defined acceptance criteria and executed none of them, because there
  is nothing to execute them against. **A written acceptance criterion is not a passed test.**
- **No performance measurement.** Every non-functional target is recorded as **not yet measured**.
  Baselines are set at Step 14.
- **No independent human review** (§6).
- **No visual diagram rendering.** Mermaid validation is structural only and is labelled as such in the
  validator's own output.

All product features remain `NOT IMPLEMENTED`. Backend runtime, Flutter workspace, database and
deployment remain `ABSENT`. Application CI remains `NOT APPLICABLE`. UAT remains `NOT STARTED`.

## 8. Open questions

**26 documented open questions remain open** for the repository owner, recorded in
`docs/product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md` and in [`security-review.md`](security-review.md) §6.

**None was closed by inventing a product decision** (Rule 00 rule 6), and **none is a retroactive Step 1
blocker**. They are inputs to later Steps.

## 9. Step 2

**Step 2 has not begun.** Steps 2–14 remain `PLANNED`. Design-system work does not start until it is
authorised as its own Step (Rule 24).

Dependabot PR #2 (`actions/checkout` 4.2.2 → 7.0.0) remains **parked** by owner decision and was not
merged, so the tagged commit is not disturbed by a CI supply-chain change.
