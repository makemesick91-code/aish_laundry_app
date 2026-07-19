# DEC-0013 — Exact-SHA Evidence Before GO

## ID

DEC-0013

## Title

Exact-SHA Evidence Before GO

## Status

ACCEPTED

## Date

19 July 2026

## Context

Aish Laundry App is built largely by autonomous AI agents working across many sessions. No human watches
every command. The owner reads reports and merges pull requests based on what those reports claim.

This creates a specific and serious failure mode. A language model produces fluent, confident,
well-structured prose regardless of whether the underlying work happened. A report saying "all tests
pass, the validator is green, Step 7 is complete" is exactly as easy to generate when it is false as when
it is true. Unlike a human contributor, an agent has no reputation at stake and no memory of the
embarrassment.

A weaker but equally real failure mode affects humans too: verifying something, then changing the code,
then reporting the earlier verification. The check genuinely happened; it simply no longer applies.

Ordinary CI practice does not close this. A green badge tracks a branch, not a commit. A CI run on an
earlier commit says nothing about the commit that actually merges. "The tests passed" without a commit
identifier is an unfalsifiable claim.

The gap matters most precisely where the stakes are highest: the two hard gates of DEC-0012 are verified
by test suites, and a fabricated or stale passing result for those suites would defeat the entire
governance structure.

## Decision

**No Step reaches GO without evidence bound to an exact commit SHA.**

1. **Evidence belongs to exactly one commit SHA**, recorded as the full 40-character identifier — not an
   abbreviation, not a branch name, not "latest", not "HEAD".
2. **Evidence for one SHA is not evidence for another.** Not for a parent, not for a child, not for a
   commit that "only changed a comment".
3. **Any new commit invalidates all prior evidence.** The checks are re-run and re-recorded.
4. **The SHA that is validated must be the SHA that is merged**, and where a release follows, the SHA
   that is tagged.
5. **Command output is real and unedited.** Redaction of secrets and marked truncation are permitted;
   altering a result is falsification.
6. **A failure is recorded as a failure.** Evidence of a failed run is committed as readily as evidence
   of a passing one.
7. **Absence of evidence is absence of completion.** A Step that cannot produce evidence is not complete,
   regardless of how confident anyone is.
8. **A green CI badge is not evidence**, because badges track branches.
9. **Every evidence pack carries both hard-gate attestations** (DEC-0012).
10. **A validator is never weakened to produce better evidence.** If an assertion is wrong, it is fixed
    deliberately, in a separate commit, with written justification.

Evidence packs live at `evidence/step-NN/`. Detailed rules:
[`../governance/EVIDENCE_POLICY.md`](../governance/EVIDENCE_POLICY.md).

## Consequences

Every Step produces a committed evidence pack recording the exact SHA, the commands run, their exit
codes, their unedited output, the environment, the hard-gate attestations, and the known gaps. CI policy
requires that required checks are green on the exact head SHA being merged, and bypassing a required
check is forbidden ([`../GIT_AND_RELEASE_POLICY.md`](../GIT_AND_RELEASE_POLICY.md) §3). Rebasing a
reviewed branch is forbidden because it voids the evidence. Agents are required to bind every completion
claim to a SHA and to distinguish "created", "verified", "assumed", "unable", and "not attempted"
([`../AI_EXECUTION_POLICY.md`](../AI_EXECUTION_POLICY.md) §2.2).

## Positive consequences

- Makes a false completion claim detectable by anyone, at any later time, by re-running the recorded
  command at the recorded SHA.
- Converts trust in agent output from something granted into something demonstrated.
- Eliminates the stale-verification failure mode entirely: one more commit means one more run.
- Gives the hard gates of DEC-0012 a verification mechanism that cannot be satisfied by assertion.
- Creates a permanent, auditable record of what was actually true at each release point — valuable for
  incident investigation years later.
- Protects honest contributors: with evidence attached, a claim is defensible rather than a matter of
  reputation.

## Negative consequences / trade-offs

- **Every additional commit costs a full re-verification cycle.** Fixing a typo after evidence was
  produced means re-running everything, which is genuinely tedious and will tempt shortcuts.
- **Evidence packs accumulate in the repository forever** and are never deleted, growing the repository
  over time.
- **Evidence must be sanitised before commit** because the repository is PUBLIC (AMENDMENT-0001), which
  adds a manual review burden and a real risk of leaking something if that review is careless.
- **Discipline overhead on small changes.** A one-line documentation fix still carries the ceremony,
  which is disproportionate but preserves the rule's absoluteness — exceptions are how such rules die.
- **Rebasing a reviewed branch is forbidden**, which makes some ordinary git workflows unavailable.
- **The rule cannot prevent a determined fabrication**, only make it discoverable. It raises the cost of
  dishonesty rather than eliminating the possibility.

## Verification

- `scripts/verify-step-00.sh` asserts the presence and structure of the Step 0 evidence pack.
- Every evidence pack manifest is reviewed for a full 40-character SHA matching the commit under review.
- Reviewers check that validator and test output corresponds to the claims in the pull request
  description, and that failures and skips are disclosed rather than hidden.
- CI policy requires green checks on the exact head SHA to be merged; bypass is forbidden.
- Review rejects any evidence pack whose output shows signs of editing beyond marked redaction and marked
  truncation.
- At the Step 0 baseline, the evidence pack contains governance evidence only — validator output, file
  inventory, absence-of-runtime demonstration, and link resolution — and explicitly states that
  application CI is `NOT APPLICABLE` and no tests exist.

## Supersession policy

Superseded only by a decision record specifying a stronger verification mechanism — for example
cryptographically signed CI attestations bound to a commit — never by a weaker one. **Relaxing exact-SHA
binding to reduce friction is not an acceptable basis for supersession.** Requires a **major** version
bump of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §1 Canonical rules
- §25 Definition of Done
- §26 Git and CI
- §27 AI development rules
- §28 Testing
- §33 AI instructions
