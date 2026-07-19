# Step 2 — Pre-Tag Closure Record

**Status: `MERGED / PRE-TAG WATCH`.**

**`GO` has not been conferred and is not claimed in this document.** `GO` is the
repository owner's to give and is never self-declared by an agent (Rule 01,
Rule 15). No Step 2 tag exists.

---

## 1. Merge

| Field | Value |
|---|---|
| Pull request | **#9** — `feat(step-02): design system and UX foundation` |
| Final candidate SHA | `b9f51c55829a7c3ef4e7a7c64d4f78389a3ae765` |
| **Merge SHA** | **`fc4449e922a0effa86b9770f5a2863a99fe776d6`** |
| Merge method | merge commit (`--merge`), matching the repository's existing history |
| Base branch | `main` |
| Merged at | 2026-07-19T15:12:04Z |
| Branch up to date with base at merge | yes — merge-base equalled `origin/main` (`25379e89…`) |

## 2. Ruleset — independently re-verified

The owner ran `scripts/owner/update-ruleset-step-02.sh`. **The owner's report was
not taken as evidence.** The live ruleset was re-read through the GitHub API and
verified independently.

| Property | Verified value |
|---|---|
| Ruleset ID | `19164588` |
| Unique required contexts | **12** (0 duplicates) |
| Enforcement | `active` |
| Bypass actors | **0** |
| `strict_required_status_checks_policy` | `true` |
| Rules retained | `deletion`, `non_fast_forward`, `pull_request`, `required_status_checks` |

All nine pre-existing contexts remain present: `validate`,
`Documentation / links`, `Required Gate`, `secret-scan`, `Workflow / actionlint`,
`classify`, `product-requirements`, `domain-model`, `threat-model`.

The three Step 2 contexts were added: **`design-system`**, **`ux-foundation`**,
**`accessibility-privacy`**.

## 3. Negative enforcement proof

A required context that is reported but does not block is not enforcement. This
was proved rather than assumed.

| Field | Value |
|---|---|
| Temporary pull request | **#10** — "TEMPORARY — negative enforcement proof (DO NOT MERGE)" |
| Temporary branch | `test/step-02-negative-enforcement-proof` |
| Exact SHA under test | `0694ca726ac9f549f2638f0b09266bd5790d7ad4` |
| Controlled break | the mandated `LOW-FIDELITY — NOT IMPLEMENTED` label removed from exactly one wireframe (`console-web-SCR-CON-001-portfolio-dashboard.svg`) |
| Failed context | **`ux-foundation`** (only `validate-wireframes.py` checks this label, and it runs only in that workflow) |
| Other required contexts | 11 / 11 **passed** |
| `mergeable` | `MERGEABLE` |
| **`mergeStateStatus`** | **`BLOCKED`** |
| Outcome | pull request **CLOSED without merging** — `mergedAt: null` |

The break was chosen so that exactly one context could fail; this was confirmed
locally before pushing (`validate-wireframes.py` exit 1; `design-required-files`,
`accessibility`, and `public-repository-safety` all exit 0).

**A merge was deliberately not attempted.** Issuing a merge call against a
pull request carrying a known-broken tree would risk writing that tree to `main`
if enforcement were flawed — which is the very thing under test. `BLOCKED`
alongside a failing required context is the authoritative signal, and it was
taken instead.

**Residual item, disclosed rather than silently resolved.** The temporary remote
branch `test/step-02-negative-enforcement-proof` **still exists**. Branch
deletion is blocked by the destructive-operations guard and is owner territory
(Rule 11 item 5, Rule 12). The guard was not edited, disabled, or routed around.
Removing that branch is an owner action.

## 4. Post-merge verification at the exact merge SHA

### 4.1 Continuous integration

All twelve required contexts reported **`success`** on
`fc4449e922a0effa86b9770f5a2863a99fe776d6`:

| Context | Result | Workflow run ID |
|---|---|---|
| `accessibility-privacy` | success | 29692354898 |
| `classify` | success | 29692354886 |
| `design-system` | success | 29692354907 |
| `Documentation / links` | success | 29692354911 |
| `domain-model` | success | 29692354899 |
| `product-requirements` | success | 29692354894 |
| `Required Gate` | success | 29692354911 |
| `secret-scan` | success | 29692354893 |
| `threat-model` | success | 29692354893 |
| `ux-foundation` | success | 29692354917 |
| `validate` | success | 29692354911 |
| `Workflow / actionlint` | success | 29692354893 |

Candidate-SHA run IDs (`b9f51c55…`) were also recorded: 29691751613,
29691751605, 29691751582, 29691751591, 29691751588, 29691751589, 29691751621,
29691751586.

**Note on the legacy status API.** `GET /commits/{sha}/status` returns
`pending` for this commit because no legacy commit statuses are posted by these
workflows — every gate reports through the Checks API. The twelve `check-runs`
above are the authoritative result. This is recorded so the `pending` value is
not later mistaken for an unfinished gate.

### 4.2 Fresh-clone verification of `main`

A completely fresh clone was taken from the remote and verified with no local
state, no cached artifacts, and no pre-existing working tree.

| Check | Result |
|---|---|
| Cloned SHA | `fc4449e922a0effa86b9770f5a2863a99fe776d6` — matches the merge SHA |
| `bash scripts/verify-step-02.sh` | **53 / 53 PASS** |
| `bash scripts/test-step-02-validators.sh` | **30 / 30 mutations caught** |
| Working tree after both runs | clean (0 changes) |
| Runtime artifacts | 0 |
| Third-party dependencies | none — validators import the Python standard library only |

A fresh clone of the feature branch at the candidate SHA `b9f51c55…` was
verified identically before the merge: 53/53, 30/30, clean tree, 0 runtime
artifacts.

## 5. Substantive state at the merge SHA

| Item | Value |
|---|---|
| Master Source version | **1.3.0** |
| Master Source checksum | `sha256sum -c` → **OK** (regenerated by tool, never hand-edited) |
| Requirements classified | **498 / 498** — **0 unclassified** |
| UI-critical requirement orphans | **0** |
| Open `CRITICAL` findings | **0** |
| Open `HIGH` findings | **0** |
| Relationship graph | 1190 nodes, 2493 links — **0 orphans** across 12 checked classes |
| Screens specified | 89 |
| Critical journeys | 32 |
| Components specified | 70, each resolved against 17 states (1190 cells, 0 blank) |
| Low-fidelity wireframes | 32 |
| Accessibility wording | `DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS` · `NOT YET RUNTIME-TESTED` |
| Flutter workspace | `ABSENT` |
| Backend runtime | `ABSENT` |
| Database | `ABSENT` |
| Deployment | `ABSENT` |
| Application CI | `NOT APPLICABLE` |
| UAT | `NOT STARTED` |
| All product features | `NOT IMPLEMENTED` |
| Steps 3–14 | all `PLANNED` |
| Runtime artifacts in tree | 0 |
| Runtime folders | contain only `README` / `.gitkeep` |

## 6. Released tags — unmoved

| Tag | Type | Peeled commit |
|---|---|---|
| `aish-laundry-step-00-master-source-governance-v1.0.0-go` | annotated | `8494bc8543b9301351da6055337832597f1f2d9f` |
| `aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` | annotated | `4eadbc73f8bacdc9cd2acfcc62280ac932116089` |

Verified both in the working repository and in the fresh clone of `main`.

## 7. Dependabot

**Pull request #2 (`ci: bump actions/checkout from 4.2.2 to 7.0.0`) remains
`OPEN` and unmerged (`mergedAt: null`).** It was not merged, closed, rebased,
modified, or pulled into Step 2 scope. `actions/checkout` remains pinned to
`11bd71901bbe5b1630ceea73d27597364c9af683` (v4.2.2) in every workflow, including
the three added by Step 2.

## 8. What this record does NOT establish

- **It does not establish that Step 2 has `GO`.** Step 2 is `MERGED / PRE-TAG
  WATCH`. No tag exists.
- **It does not establish that anything was runtime-tested.** Accessibility is
  designed to a target and has not been exercised with any assistive technology,
  because there is nothing to exercise. Runtime accessibility testing belongs to
  Step 13 and is `NOT STARTED`.
- **It does not establish that the validator set is complete.** The harness
  proves each validator turns red on the defect it targets. A defect nobody
  wrote a validator for is not caught.
- **It does not establish independent review.** Governance is single-maintainer
  and independent human approval is `ABSENT`
  ([DEC-0017](../../docs/decisions/DEC-0017-single-maintainer-approval-standing-deviation.md)).
  The compensating controls are load-bearing but are **not equivalent** to
  independent review. That residual risk is accepted, not eliminated. This
  document records **internal re-verification under single-maintainer
  governance**, not an approval.

**Documentation is not implementation.** A design token is not a theme, a
component specification is not a component, a wireframe is not a screen, and an
accessibility requirement is not a passed audit.

## 9. Remaining owner actions

1. **Confer `GO`** for Step 2, or decline.
2. **Create the annotated GO tag** at the merge SHA, if `GO` is conferred.
   Proposed name: `aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go`.
3. **Delete the temporary branch** `test/step-02-negative-enforcement-proof`
   (blocked by the destructive-operations guard for an agent).
4. **Decide Dependabot #2** separately from Step 2.
