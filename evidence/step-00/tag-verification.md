# GO Tag Verification — Step 0

## Tag

| Item | Value |
|---|---|
| Tag name | `aish-laundry-step-00-master-source-governance-v1.0.0-go` |
| Type | **annotated** (`tag` object, not lightweight) |
| Tag object SHA | `e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9` |
| Peeled commit SHA | `8494bc8543b9301351da6055337832597f1f2d9f` |
| Tag message | `Aish Laundry App Step 0 Master Source and Governance v1.0.0 GO` |
| Remote verified | YES |

The peeled commit SHA equals the **foundation merge SHA** exactly.

## Collision check performed before creation

```bash
git ls-remote --tags origin
```

Returned no tags. The tag name did not already exist, so there was no risk of
colliding with an existing tag pointing at a different SHA. Had such a collision
existed, the correct outcome would have been `NO-GO`, not an overwrite.

## Creation

```bash
git tag -a aish-laundry-step-00-master-source-governance-v1.0.0-go \
  8494bc8543b9301351da6055337832597f1f2d9f \
  -m "Aish Laundry App Step 0 Master Source and Governance v1.0.0 GO"

git push origin aish-laundry-step-00-master-source-governance-v1.0.0-go
```

Result: `* [new tag]` — created, not updated, not forced.

## Verification commands and output

```bash
$ git cat-file -t aish-laundry-step-00-master-source-governance-v1.0.0-go
tag

$ git rev-parse aish-laundry-step-00-master-source-governance-v1.0.0-go
e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9

$ git rev-parse aish-laundry-step-00-master-source-governance-v1.0.0-go^{}
8494bc8543b9301351da6055337832597f1f2d9f

$ git ls-remote --tags origin
e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9	refs/tags/aish-laundry-step-00-master-source-governance-v1.0.0-go
8494bc8543b9301351da6055337832597f1f2d9f	refs/tags/aish-laundry-step-00-master-source-governance-v1.0.0-go^{}
```

`git cat-file -t` returning `tag` rather than `commit` is what proves the tag is
annotated. A lightweight tag would return `commit` and would carry no tag object,
no message, and no tagger.

The remote listing shows both refs, which independently confirms on the server
that the tag object and its peeled commit are what the local repository reports.

## Preconditions verified before tagging

| Condition | Result |
|---|---|
| Foundation PR merged | YES — PR #1 |
| Main exact merge SHA verified | YES — `8494bc85…2d9f` |
| Main governance CI green on that exact SHA | YES — all six required checks |
| Fresh checkout of `main` passes | YES — 11/11, clean tree |
| Ruleset active | YES — ID 19164588, enforcement proven |
| No CRITICAL or HIGH finding open | YES — all closed and re-verified |
| Master Source and decision records complete | YES — v1.0.0, checksum valid, DEC-0001..DEC-0015 |
| All features remain NOT IMPLEMENTED | YES |
| No runtime present | YES |
| Technical evidence sufficient | YES |
| Repository private | **NO — PUBLIC by deliberate owner decision** (`AMENDMENT-0001`) |

Every precondition is satisfied except repository visibility, which is a recorded
deviation rather than an unverified claim.

## Immutability

The tag is treated as immutable:

- It has not been moved, deleted, or recreated.
- It was not force-pushed.
- Evidence added after tagging is carried on a separate branch and PR
  (`docs/step-00-post-tag-evidence`) precisely so the tagged foundation source
  does not move.
- If the tag were ever found to be invalid, the correct response is to document
  the incident and issue a **new** tag version after remediation — never to
  rewrite this one.

Post-tag immutability is re-confirmed in `post-tag-evidence.md` after the
evidence PR was merged.
