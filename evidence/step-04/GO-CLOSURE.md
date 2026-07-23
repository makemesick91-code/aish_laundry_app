# Step 4 — GO Closure

**Classification: `GO`** — owner-authorized, conferred 22 July 2026 against
exact-SHA evidence after merge.

This file records the merge, the tag, the post-merge CI, and the fresh
clean-checkout re-verification. It is the evidence counterpart to the tag's own
annotation.

---

## 1. Merge

| | |
|---|---|
| Pull request | #18 — **MERGED** |
| Method | merge commit (not squashed, not rebased) |
| Final candidate (feature head) | `1a9e2d3bf31704821718a97536e37f56fd44d6c4` |
| Merge commit | `af31ea3b0945b274b249ff21cf30918cb2d17a5f` |
| First parent | `0f065a330e085228aaeed086f620d8752291e0af` (prior main) |
| Second parent | `1a9e2d3b…` — the tested candidate |
| Merge tree | **byte-identical** to the tested candidate tree |

Merging with a merge commit was deliberate: the finding-level commits, the three
insufficient first remediations, and the adversarial review chronology are the
record, and squashing would erase exactly the history that shows the final
results were not right first time.

## 2. Tag

| | |
|---|---|
| Name | `aish-laundry-step-04-laundry-master-data-v1.0.0-go` |
| Kind | annotated, immutable |
| Tag object | `55ed19761714aea945ecfcc919a78bae769339ac` |
| Peels to | `af31ea3b0945b274b249ff21cf30918cb2d17a5f` (the merge commit) |
| Remote object | equal to local — verified after push |

The tag targets the **merge commit**, never this later evidence commit. The
Step 3 GO tag (`8b37230…` peeling to `0e25543…`) is unchanged.

## 3. Post-merge CI

**11 required workflows, 11 successful, at the exact merge SHA `af31ea3` on
`main`** — `push` event, not inherited from the PR-head. Run identifiers are in
[`authoritative-ci.txt`](authoritative-ci.txt). CI database isolation satisfies
the six documented conditions ([`ci-database-isolation.txt`](ci-database-isolation.txt)).

## 4. Fresh clean-checkout re-verification at the merge SHA

An isolated clone at `af31ea3`, on a filesystem with adequate disk:

| Gate | Result |
|---|---|
| Backend suite | 466 passed, 4212 assertions |
| Lifecycle fresh / rollback(5) / re-apply(5) | clean |
| Five hardened triggers | all `tgenabled='A'` |
| Consent boundary (normal + replica) | UPDATE / DELETE / truncation refused, `23001` |
| Published price-list boundary | model, query-builder, forceDelete, raw SQL, replica-mode raw SQL — all refused |
| Fixture preconditions | asserted before every refusal; row counts unchanged after |
| Supersession composite FK / no duplicate constraint | present / confirmed |
| 8 validators | PASS |
| Harnesses | 11/11, 9/9, 10/10 |
| verify-step-00 / 01 / 02 | PASS |
| **verify-step-03** | **52 passed, 0 failed, 1 skipped** |
| **verify-step-04** | **27 passed, 0 failed, 1 skipped** |

The one skip is the named `DEC-0026` scaffold suite (exit-78, precondition
unavailable off a Step 3 branch). It is not counted as a pass, and no Step 4 gate
hides behind it.

**On the earlier unresolved result.** A first clean-checkout run reported two
build-gate failures (Ops Android debug, Admin Web release). That run also
exhausted the disk. The failures were **disk exhaustion, not a regression**:
re-run with adequate disk, both gates pass, and the identical buildable tree
(`apps/`, `backend/`, `packages/`) is byte-identical to the merge commit. This is
recorded rather than hidden — a failure interrupted by a resource limit is
neither a pass nor a confirmed regression until it is re-run.

A separate observation from that run: `validate-dec-0030-labels` inspects path
components outside the repository root, so a clone directory named
`fresh-checkout` made it report a false failure on the token `checkout`. From a
neutral path it passes 4/4. Recorded as a tooling-governance follow-up; it is not
a Step 4 product defect.

## 5. What GO does and does not confer

- **Does:** Step 4 master-data implementation is complete and verified, with
  FR-024 and FR-025 `COMPLETE_AND_VERIFIED`.
- **Does not:** start Step 5, authorise deployment, or discharge the seven
  `STEP_5_E2E_PENDING` requirements (FR-029, FR-033, FR-036, FR-039, FR-044,
  FR-046, FR-047). FR-036 is a mandatory Step 5 financial-integrity obligation.
- **Accepted deviations:** NEW-04 (`ACCEPTED_OPERATIONAL_RESIDUAL`); single-
  maintainer governance with no independent human review (DEC-0017); the
  database guarantees bounded to the application connection, with a non-owner,
  non-superuser runtime role `REQUIRED_FOR_FUTURE_DEPLOYMENT` /
  `NOT_YET_PROVISIONED` / `NOT_CLAIMED_AS_CURRENT_CONTROL`.

## 6. Evidence indirection

The captures in this pack bind to anchor `6abd3fd`. This closure file and the
GO-status documentation are added afterward, on the closure branch, so the commit
recording them is necessarily later than the merge the tag points to. The delta
is documentation and evidence only — no runtime, migration, test, validator, or
workflow change — which is why the tag stays on the verified merge commit and not
on any documentation commit.
