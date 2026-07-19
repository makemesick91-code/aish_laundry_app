# Exact-SHA CI Evidence — Step 0

Every CI claim below is bound to a specific full commit SHA. A green run from any
other SHA is stale and was not accepted as evidence at any point.

## Candidate SHA (foundation PR head)

```
b1bd1549b50f828b009c2241a0836ae23fcf4608
```

Obtained with `git rev-parse HEAD` on
`feature/step-00-master-source-and-governance` immediately before push.

### Workflow runs on the exact candidate SHA

| Run ID | Workflow | Event | Branch | Conclusion | Head SHA |
|---|---|---|---|---|---|
| 29678026975 | Governance | `pull_request` | `feature/step-00-master-source-and-governance` | success | `b1bd1549b50f828b009c2241a0836ae23fcf4608` |
| 29678026977 | Security | `pull_request` | `feature/step-00-master-source-and-governance` | success | `b1bd1549b50f828b009c2241a0836ae23fcf4608` |
| 29678026981 | Runtime Detection | `pull_request` | `feature/step-00-master-source-and-governance` | success | `b1bd1549b50f828b009c2241a0836ae23fcf4608` |

### Check runs on the exact candidate SHA

Command:

```bash
gh api repos/makemesick91-code/aish_laundry_app/commits/b1bd1549b50f828b009c2241a0836ae23fcf4608/check-runs
```

| Conclusion | Check name |
|---|---|
| success | `validate` |
| success | `Documentation / links` |
| success | `Required Gate` |
| success | `secret-scan` |
| success | `Workflow / actionlint` |
| success | `classify` |

`Required Gate` was green on the exact candidate SHA. All six required checks
matched the SHA under review — no substitution, no stale run.

## Merge SHA (main)

```
8494bc8543b9301351da6055337832597f1f2d9f
```

Merge method: merge commit. PR `#1`.

### Workflow runs on the exact merge SHA

| Run ID | Workflow | Event | Branch | Conclusion | Head SHA |
|---|---|---|---|---|---|
| 29678111996 | Governance | `push` | `main` | success | `8494bc8543b9301351da6055337832597f1f2d9f` |
| 29678112016 | Security | `push` | `main` | success | `8494bc8543b9301351da6055337832597f1f2d9f` |
| 29678112033 | Runtime Detection | `push` | `main` | success | `8494bc8543b9301351da6055337832597f1f2d9f` |

### Check runs on the exact merge SHA

| Conclusion | Check name |
|---|---|
| success | `validate` |
| success | `Documentation / links` |
| success | `Required Gate` |
| success | `secret-scan` |
| success | `Workflow / actionlint` |
| success | `classify` |

Two additional non-required checks (`Dependabot`, `.github/dependabot.yml`) also
reported success. They are not part of the required gate.

## SHA distinctions

These are deliberately kept separate and are never conflated:

| Role | SHA |
|---|---|
| Foundation candidate (PR head) | `b1bd1549b50f828b009c2241a0836ae23fcf4608` |
| Foundation merge (`main`) | `8494bc8543b9301351da6055337832597f1f2d9f` |
| GO tag object | `e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9` |
| GO tag peeled commit | `8494bc8543b9301351da6055337832597f1f2d9f` |
| Post-tag evidence merge (`main`) | recorded in `post-tag-evidence.md` |

The GO tag peels to the **foundation merge SHA**, not to the later documentation
SHA. That separation is the entire purpose of the evidence-only pattern: adding
evidence must never move the tagged foundation source.

## Policy applied

- CI conclusions were read per-SHA via the check-runs API, not inferred from a
  branch-level status badge.
- No merge was performed on the basis of a run whose `headSha` differed from the
  commit under review.
- Evidence added after the tag is carried on a separate branch and PR, and is
  re-validated by its own exact-SHA CI run rather than inheriting the foundation
  run's result.
