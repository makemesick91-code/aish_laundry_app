# Post-Tag Evidence Synchronization — Step 0

## Why an evidence-only pull request

The GO tag points at the foundation merge commit. Closure evidence — exact-SHA
CI results, ruleset verification, merge and tag verification — can only be
written *after* that commit exists. Writing it into the tagged commit is
impossible, and moving the tag to include it would destroy the tag's meaning.

The evidence-only pattern resolves this: evidence lands on a separate branch and
pull request, and the tag stays exactly where it was.

## Branch and pull request

| Item | Value |
|---|---|
| Branch | `docs/step-00-post-tag-evidence` |
| Branched from | `main` at `8494bc8543b9301351da6055337832597f1f2d9f` |
| Base | `main` |

## Scope of change — strictly limited

Changed:

- `docs/STATUS.md` — Step 0 status `IN PROGRESS` → `GO`, closure SHAs recorded,
  visibility deviation stated
- `docs/CHANGELOG.md` — closure entry
- `evidence/step-00/*` — PENDING records replaced with actual results

Explicitly **not** changed:

| Constraint | Verified |
|---|---|
| No product decision altered | DEC-0001 … DEC-0015 untouched |
| No canonical fact altered | pricing, roadmap, architecture unchanged |
| Master Source content unchanged | v1.0.0, checksum `9b9539d0…22fe` unchanged |
| No runtime added | `validate-no-runtime.py` passes |
| GO tag not moved | verified below |
| Steps 1–14 still PLANNED | `validate-roadmap.py` and `validate-status.py` pass |
| Features still NOT IMPLEMENTED | `validate-status.py` passes |

Because the Master Source is unchanged, its recorded SHA-256 still matches and no
checksum regeneration was required. Had a canonical file genuinely changed, the
checksum would have been regenerated and the change recorded as such.

## SHA distinction

```
GO tag peeled SHA:            8494bc8543b9301351da6055337832597f1f2d9f
Post-tag evidence main SHA:   <recorded in final-closure.md>
```

These are deliberately different commits. The tag continues to point at the
foundation merge, not at the later documentation commit. That difference is the
evidence that the foundation source was not moved.

## Tag immutability re-verified after merge

Confirmed after the evidence PR merged:

- `git cat-file -t <tag>` still returns `tag` (still annotated)
- `git rev-parse <tag>` still returns `e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9`
- `git rev-parse <tag>^{}` still returns `8494bc8543b9301351da6055337832597f1f2d9f`
- `git ls-remote --tags origin` reports the same two refs on the server

The tag was not deleted, recreated, moved, or force-pushed at any point.

Exact command output is recorded in `final-closure.md`.

## Exact-SHA CI

The evidence PR does not inherit the foundation run's green result. It ran its
own required checks against its own head SHA, and was merged only after those
checks passed on that exact SHA. Details in `final-closure.md`.
