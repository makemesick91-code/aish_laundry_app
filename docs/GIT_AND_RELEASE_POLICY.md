# Git and Release Policy — Aish Laundry App

Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §26
Baseline date: 19 July 2026

Repository: `makemesick91-code/aish_laundry_app` · Default branch: `main` · Visibility: **PUBLIC**
(see AMENDMENT-0001 in [`ASSUMPTIONS.md`](ASSUMPTIONS.md))

This policy exists so that the repository's history is a truthful, immutable record of what happened.
History is evidence. Evidence that can be rewritten is not evidence.

---

## 1. Branch model

### 1.1 `main`

- `main` is the single long-lived branch and always represents the current canonical truth.
- `main` is **protected**. Direct pushes are forbidden for everyone, including the repository owner and
  including AI agents.
- `main` is always in a state consistent with [`STATUS.md`](STATUS.md).
- Force push to `main` is forbidden. History rewriting on `main` is forbidden.

Branch protection is enforced by the platform, which is why the repository is public — the free plan
cannot protect a private repository (AMENDMENT-0001).

### 1.2 Working branches

Branches are short-lived, cut from the current `main` head, and deleted after merge.

| Purpose | Pattern | Example |
| --- | --- | --- |
| Canonical step | `feature/step-NN-<slug>` | `feature/step-00-master-source-and-governance` |
| Bug fix | `fix/<slug>` | `fix/status-table-typo` |
| Documentation | `docs/<slug>` | `docs/clarify-quiet-hours` |
| Chore / tooling | `chore/<slug>` | `chore/update-validator` |
| Security | `security/<slug>` | `security/harden-token-policy` |
| Revert | `revert/<slug>` | `revert/step-07-notification-regression` |

Rules:

- `NN` is the two-digit canonical step number from [`ROADMAP.md`](ROADMAP.md).
- Slugs are lowercase kebab-case ASCII.
- One branch, one Step or one focused change. Mixing Steps in a branch is forbidden.
- Long-lived divergent branches are forbidden; rebase onto `main` frequently **while the branch is
  unmerged and unreviewed**, and never after review has begun.

### 1.3 Commits

Conventional Commits, as specified in [`../CONTRIBUTING.md`](../CONTRIBUTING.md) §3. Commit messages
describe what changed and why; they never claim work that did not happen.

---

## 2. Pull requests only

**All change to `main` arrives through a pull request. There are no exceptions.**

Requirements before merge:

1. The pull request description states the Step, the scope, what is explicitly out of scope, and an
   honest status statement using the canonical vocabulary.
2. The Step's validator passes and its unedited output is attached.
3. All required checks are green **on the exact head SHA that will be merged**.
4. At least one approving review from someone other than the author for a Step-closing change.
5. The Definition of Done for the Step is satisfied
   ([`DEFINITION_OF_DONE.md`](DEFINITION_OF_DONE.md)).
6. No hard gate is failing ([`governance/TENANT_ISOLATION_POLICY.md`](governance/TENANT_ISOLATION_POLICY.md),
   [`governance/FINANCIAL_INTEGRITY_POLICY.md`](governance/FINANCIAL_INTEGRITY_POLICY.md)).

Merge method: squash merge or merge commit, per repository setting. Rebasing a reviewed branch onto
`main` and force-pushing is forbidden, because it invalidates the exact-SHA evidence and the review.

---

## 3. Exact-SHA CI policy

Locked by [DEC-0013](decisions/DEC-0013-exact-sha-evidence-before-go.md).

1. **A CI result belongs to exactly one commit SHA.** It is evidence for that SHA and for no other.
2. **A green check on an earlier commit is not evidence for a later commit.** Pushing one more commit
   invalidates the previous evidence entirely, even if the change was "just a typo".
3. **The SHA that is validated must be the SHA that is merged**, and where a release follows, the SHA
   that is tagged.
4. **Evidence packs record the exact SHA** they were produced against
   ([`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)).
5. **Re-running CI is permitted; editing its output is not.** Pasted output must be real and unedited.
6. **Required checks are never bypassed**, not by an administrator override, not by disabling a check,
   not by marking a check non-required to unblock a merge.
7. A merge commit produces a new SHA on `main`; post-merge validation on `main` is a separate check with
   its own evidence.

---

## 4. Tags and releases

### 4.1 Tag rules

1. Tags are **annotated**, never lightweight. An annotated tag carries a tagger, a date, and a message,
   which makes it an auditable record.
2. Tags are **immutable**. A tag is never moved, never deleted, never re-pointed, never re-created with
   the same name.
3. A tag points at an exact SHA on `main` that satisfied its Step's Definition of Done with evidence.
4. The tag message names the Step, the Master Source version, and the evidence pack path.

### 4.2 GO tag naming convention

```
aish-laundry-step-NN-<slug>-vX.Y.Z-go
```

Where:

- `NN` — two-digit canonical step number;
- `<slug>` — lowercase kebab-case slug of the canonical Step title;
- `vX.Y.Z` — semantic version of the Master Source at that point;
- `-go` — the suffix asserting the Step reached GO.

Example shape for the governance foundation:
`aish-laundry-step-00-master-source-and-governance-v1.0.0-go`.

### 4.3 What a GO tag asserts

A GO tag is a claim, and under the honesty rules it must be a true one. Creating it asserts that, at that
exact SHA:

- the Step's declared scope was delivered in full;
- the Definition of Done was satisfied;
- both hard gates passed;
- an evidence pack exists, bound to that SHA, and it is real;
- [`STATUS.md`](STATUS.md) accurately reflects reality.

**A GO tag is never created in anticipation, never created to unblock a schedule, and never created for a
Step whose evidence is incomplete.** If any of the above is untrue, the correct outcome is **NO-GO** and
no tag.

### 4.4 Step 0 constraint

Step 0's status while its foundation pull request is open is **IN PROGRESS**, and after validation may be
**TESTED** or **WATCH**. The release status word is never written as Step 0's status in
[`STATUS.md`](STATUS.md) during the foundation pull request
([`MASTER_SOURCE.md`](MASTER_SOURCE.md) §1.3).

---

## 5. Rollback

**Rollback is by revert only.**

1. A regression is undone with `git revert`, producing a new commit that reverses the change.
2. The revert arrives through a pull request, like any other change.
3. The pull request explains what regressed, how it was detected, and what the follow-up is.
4. [`STATUS.md`](STATUS.md) and [`CHANGELOG.md`](CHANGELOG.md) are updated to reflect the reversal
   honestly.
5. The original commits remain in history. **The record of what happened is never erased.**
6. A tag associated with the reverted state is **not** deleted; a subsequent tag records the corrected
   state.

Rollback is never performed by force push, by resetting `main`, by deleting commits, or by rewriting
history.

---

## 6. Forbidden destructive operations

The following are **forbidden** on this repository. An AI agent encountering a situation that appears to
require one of these must **stop and report**, never proceed
([`AI_EXECUTION_POLICY.md`](AI_EXECUTION_POLICY.md)).

| Operation | Why it is forbidden |
| --- | --- |
| `git push --force` / `--force-with-lease` to `main` | Destroys the immutable record; invalidates all exact-SHA evidence |
| History rewriting on `main` (`rebase`, `filter-branch`, `filter-repo`, amend of merged commits) | Same |
| `git reset --hard` on `main` | Same |
| Deleting a tag | Tags are immutable evidence |
| Moving or re-pointing a tag | Same |
| Re-creating a deleted tag with the same name | Produces two different meanings for one identifier |
| Deleting a protected branch | Destroys the canonical line of history |
| Disabling or bypassing a required check to force a merge | Defeats the exact-SHA policy |
| Removing a branch protection rule | Removes the platform enforcement of this policy |
| Force-pushing over a branch under active review | Invalidates the review and its evidence |
| `git clean -fdx` in a working tree with uncommitted deliverables | Destroys unrecorded work |
| Rewriting history to remove a committed secret | Not remediation — rotation is (see [`../SECURITY.md`](../SECURITY.md)) |

### 6.1 If a secret was committed

Because the repository is public, treat the secret as compromised immediately.

1. **Rotate or revoke the credential.** This is the only step that restores security.
2. Remove the value from the working tree through a normal pull request.
3. Report through the private channel in [`../SECURITY.md`](../SECURITY.md).
4. Record the incident and its remediation.

Do **not** attempt to rewrite history. It does not un-leak a public secret, and it is forbidden here.

---

## 7. Evidence and traceability

- Every Step has an evidence pack under `evidence/step-NN/`, bound to an exact SHA.
- Evidence is sanitised: no secrets, no personal data, no internal hostnames
  ([`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)).
- The chain **decision record → rule file → validator → evidence → tag** must be followable for any Step
  ([`GOVERNANCE_TRACEABILITY.md`](GOVERNANCE_TRACEABILITY.md)).

---

## 8. Changing this policy

This policy changes only through a pull request that also updates
[`MASTER_SOURCE.md`](MASTER_SOURCE.md) §26 and adds a changelog entry. Weakening a destructive-operation
ban additionally requires a decision record.
