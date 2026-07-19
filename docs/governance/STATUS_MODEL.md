# Status Model — Aish Laundry App

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §1.3
Baseline date: 19 July 2026

This document defines the **complete and exclusive** status vocabulary for Aish Laundry App. No other
status word may be used in any canonical document, pull request, evidence pack, or agent report.

Status words are machine-validated in [`../STATUS.md`](../STATUS.md). They are written in **UPPER CASE**,
exactly as spelled here.

---

## 1. Vocabulary

| Status | Applies to | Meaning |
| --- | --- | --- |
| `PLANNED` | Step, feature, module | Declared and scheduled. No work has started. |
| `IN PROGRESS` | Step, feature, pull request | Work has genuinely started and is not finished. |
| `TESTED` | Step, feature | Work is complete and its required tests or validators have passed, with evidence. Not yet released. |
| `WATCH` | Step, feature, release | Delivered but under active observation because a risk is known and unresolved. |
| `GO` | Step, release | Fully satisfied its Definition of Done with exact-SHA evidence, both hard gates passed, approved for release. |
| `NO-GO` | Step, release, pull request | Blocked. A hard gate failed, a validator failed, or evidence is missing. Must not merge, must not release, must not tag. |
| `NOT IMPLEMENTED` | Feature, module, capability | The capability does not exist in code. A placeholder folder may exist; it is not the feature. |
| `ABSENT` | Runtime, environment, infrastructure | The thing does not exist at all — no runtime, no workspace, no environment, no deployment. |
| `NOT APPLICABLE` | Process, check, CI, test | The check is meaningless in the current state, because its subject does not exist. |
| `NOT STARTED` | Process, activity | The activity is real and expected, but has not begun. |

---

## 2. Definitions in detail

### 2.1 `PLANNED`

The item is on the roadmap with a declared scope and a Step number. Nobody is working on it. Design
discussion does not make an item `IN PROGRESS`.

Correct: Steps 1 through 14 at the Step 0 baseline.

### 2.2 `IN PROGRESS`

Real work has begun and is incomplete. `IN PROGRESS` is honest about incompleteness — it is not a
placeholder for "nearly done" or "should be fine".

Correct: Step 0 while its foundation pull request is open and unmerged.

### 2.3 `TESTED`

The work is complete, the required tests or validators ran, and they passed, with evidence bound to an
exact commit SHA. `TESTED` is a stronger claim than `IN PROGRESS` and a weaker claim than `GO`: it
asserts correctness, not release approval.

`TESTED` may not be claimed on the basis of a validator that was edited to pass, a test that was skipped,
or a result that was not observed.

### 2.4 `WATCH`

The work is delivered but a known, unresolved risk exists — an unexplained metric, a flaky test, a
performance regression under investigation, a dependency with an open advisory. `WATCH` is a deliberate,
honest state, not a euphemism for broken. Something genuinely broken is `NO-GO`.

Every `WATCH` names the risk, the owner, and the condition that ends the watch.

### 2.5 `GO`

The highest status. Asserts that, at an exact commit SHA:

- the declared scope was delivered in full;
- the Definition of Done was satisfied ([`../DEFINITION_OF_DONE.md`](../DEFINITION_OF_DONE.md));
- the tenant isolation hard gate passed ([`TENANT_ISOLATION_POLICY.md`](TENANT_ISOLATION_POLICY.md));
- the financial integrity hard gate passed
  ([`FINANCIAL_INTEGRITY_POLICY.md`](FINANCIAL_INTEGRITY_POLICY.md));
- an evidence pack exists, bound to that SHA, and it is real
  ([`EVIDENCE_POLICY.md`](EVIDENCE_POLICY.md));
- [`../STATUS.md`](../STATUS.md) reflects reality.

**Constraint for Step 0:** this status word is never written as Step 0's status in
[`../STATUS.md`](../STATUS.md) during the foundation pull request. The highest status Step 0 may carry
before merge is `IN PROGRESS`, and after validation `TESTED` or `WATCH`
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §1.3).

### 2.6 `NO-GO`

Blocked. Merge is forbidden, release is forbidden, tagging is forbidden.

Triggers include: cross-tenant data exposure; any financial integrity failure; a failing validator; a
failing, skipped, or unrunnable required test; missing evidence; a secret in the repository; a required
forbidden destructive operation; incomplete declared scope.

**A `NO-GO` is never waived, never downgraded to `WATCH` for convenience, and never traded against a
deadline.** Reporting a `NO-GO` honestly is correct behaviour, not failure.

### 2.7 `NOT IMPLEMENTED`

The capability does not exist in code. Used for product features and modules.

The presence of a directory, a README, a design document, or a decision record does **not** move an item
off `NOT IMPLEMENTED`. **An empty folder is never evidence of an implemented feature.**

Correct: every product feature at the Step 0 baseline.

### 2.8 `ABSENT`

Stronger than `NOT IMPLEMENTED`. The thing does not exist at all — there is no runtime, no workspace, no
environment, no artefact, nothing to inspect.

Use `ABSENT` for runtimes, workspaces, environments, infrastructure, and deployments.
Use `NOT IMPLEMENTED` for features and modules.

Correct at the Step 0 baseline: Backend runtime `ABSENT`, Flutter workspace `ABSENT`, Deployment
`ABSENT`.

### 2.9 `NOT APPLICABLE`

The check or process is meaningless because its subject does not exist. It is not a failure and not a
deferral — there is genuinely nothing to check.

Correct at the Step 0 baseline: Application CI is `NOT APPLICABLE`, because there is no application to
build or test. It becomes applicable at Step 3.

`NOT APPLICABLE` must never be used to hide work that was skipped. If the subject exists and the check
was not run, the correct status is `NOT STARTED` or `NO-GO`.

### 2.10 `NOT STARTED`

The activity is real, expected, and has not begun. Distinguished from `NOT APPLICABLE` by the fact that
the activity genuinely applies.

Correct at the Step 0 baseline: UAT is `NOT STARTED` — user acceptance testing is a real, expected
activity for this product; it simply has not begun.

---

## 3. Choosing between confusable statuses

| Question | If yes | If no |
| --- | --- | --- |
| Does the subject of the check exist at all? | Continue below | `NOT APPLICABLE` |
| Is this a runtime, environment, or deployment that does not exist? | `ABSENT` | Continue below |
| Is this a feature that has no code? | `NOT IMPLEMENTED` | Continue below |
| Is this a real activity that has not begun? | `NOT STARTED` | Continue below |
| Is it scheduled but untouched? | `PLANNED` | Continue below |
| Is work underway and unfinished? | `IN PROGRESS` | Continue below |
| Is it blocked by a gate, a failure, or missing evidence? | `NO-GO` | Continue below |
| Is it complete, verified, and evidenced, with a known unresolved risk? | `WATCH` | Continue below |
| Is it complete, verified, and evidenced, but not released? | `TESTED` | Continue below |
| Is it complete, verified, evidenced, gated, and approved for release? | `GO` | Reassess |

---

## 4. Rules of use

1. Status words are UPPER CASE and spelled exactly as in §1.
2. No synonyms. Not "done", "shipped", "complete", "blocked", "WIP", "partial", "mostly working", or
   "should work".
3. No hedged statuses. "`IN PROGRESS` (basically done)" is not a status.
4. A status is advanced only with evidence bound to an exact commit SHA
   ([`EVIDENCE_POLICY.md`](EVIDENCE_POLICY.md)).
5. A status is never advanced to make a report look better, meet a deadline, or avoid an awkward
   conversation.
6. Downgrading a status when reality demands it is correct and required.
7. Every status change appears in [`../STATUS.md`](../STATUS.md) and in
   [`../CHANGELOG.md`](../CHANGELOG.md).
8. `NO-GO` outranks every other status. If any component is `NO-GO`, the Step is `NO-GO`.

---

## 5. Current application of the model

The authoritative current status is [`../STATUS.md`](../STATUS.md). At the 19 July 2026 baseline:

- Step 0 — `IN PROGRESS`
- Steps 1 to 14 — `PLANNED`
- All product features — `NOT IMPLEMENTED`
- Backend runtime — `ABSENT`
- Flutter workspace — `ABSENT`
- Deployment — `ABSENT`
- Application CI — `NOT APPLICABLE`
- UAT — `NOT STARTED`
