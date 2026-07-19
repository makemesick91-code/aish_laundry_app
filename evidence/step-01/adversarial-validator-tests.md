# Adversarial Validator Tests — Step 1

**Exact commit SHA:** `663f432d68eeaec4a7cd7d5f7b0d477bd9fa2948`
**Timestamp:** 2026-07-19 17:36:27 WIB
**Command:** `bash scripts/test-step-01-validators.sh`
**Exit code:** 0

## Why this exists

A validator that has never failed has never been verified. Step 1 has no runtime, so **the validators
are the enforcement layer**; a validator that fails open is a control that does not exist.

This harness copies the repository into a disposable sandbox, deliberately breaks one thing at a time,
and asserts that the responsible validator **fails**. **The working tree is never mutated** — every
mutation happens inside the sandbox copy, which is removed on exit. A control case asserts the
unmutated copy still passes, so a harness that silently stopped testing would be visible.

Credential-shaped fixtures (an AWS-key-shaped string, an Indonesian mobile number, and the literal
sentence claiming the repository is private) are **assembled at runtime from fragments**, because a
literal credential-shaped string in this file would itself be a finding on a public repository. The
scanners were not given an exclusion carve-out.

## What this found

Building the harness exposed **six defects in validators written during this Step**, none of which
were visible by reading the code:

| Defect | Consequence had it shipped |
|---|---|
| Threat severity read by scanning for the word "HIGH" | `Likelihood: HIGH` on an `INFORMATIONAL` threat counted as high-severity; the mirror of this defect could have hidden a genuinely unmitigated `HIGH` threat |
| Threat records split on every ID mention | Cross-references produced phantom records, diluting the population |
| Requirement pattern rejecting backticked IDs | A backticked duplicate definition passed undetected |
| Acceptance-criteria docs excluded as definition sources | All 68 `SEC-` definitions erased from traceability |
| Negation matched `\bforbid\b`, not "forbidden" | Correct prose flagged as a violation, forcing authors to reword accurate documents |
| Negation scoped to a character window | A widened window **excused** deliberately introduced disposal and route-optimization claims |

The last two are a matched pair and show why the scope matters in both directions: too narrow flags
correct prose, too wide misses real violations. The scope is now the containing markdown block.

---

## Full unedited output

```text
========================================================================
ADVERSARIAL VALIDATOR TESTS — STEP 1
========================================================================
repo    : /home/fikri/Projects/aish_laundry
sandbox : /tmp/aish-step01-adversarial.k68L8O

The working tree is not modified. All mutations occur in the sandbox copy.

PASS  control: unmutated sandbox copy passes requirement-ids

--- mutations ---
PASS  caught: duplicate requirement ID (exit 1)
PASS  caught: pricing figure altered (Rp79.000 -> Rp89.000) (exit 1)
PASS  caught: H+7 reminder stage removed (exit 1)
PASS  caught: tenant boundary document emptied (exit 1)
PASS  caught: integer Rupiah rule removed from the corpus (exit 1)
PASS  caught: tracking token described as the order number (exit 1)
PASS  caught: canonical order status removed (READY_FOR_PICKUP) (exit 1)
PASS  caught: CRITICAL/HIGH threat stripped of its mitigation (exit 1)
PASS  caught: pubspec.yaml added (Step 1 scope breach) (exit 1)
PASS  caught: AWS access key committed (exit 1)
PASS  caught: real-looking Indonesian phone number committed (exit 1)
PASS  caught: repository described as private (exit 1)
PASS  caught: Step 2 marked IN PROGRESS (forward scope leak) (exit 1)
PASS  caught: a feature marked IMPLEMENTED (exit 1)
PASS  caught: unclosed markdown code fence (exit 1)
PASS  caught: acceptance criterion cites an undefined requirement (exit 1)
PASS  caught: automatic disposal of laundry proposed (exit 1)
PASS  caught: route optimization claimed (exit 1)
PASS  caught: Master Source checksum hand-edited (exit 1)
PASS  caught: Master Source version bumped in header but not footer (exit 1)
PASS  caught: SQL CREATE TABLE leaked into the domain model (exit 1)
PASS  caught: a mandatory persona removed (exit 1)

------------------------------------------------------------------------
ADVERSARIAL RESULTS: 23/23 mutations correctly caught
RESULT: PASS — every mutation was caught by the validator responsible for it.
```
