# Ruleset Verification — Step 0

## Ruleset

| Item | Value |
|---|---|
| Ruleset ID | **19164588** |
| Name | `main-branch-protection` |
| Target | `branch` — `~DEFAULT_BRANCH` (`main`) |
| Enforcement | `active` |
| Bypass actors | **0** |

Zero bypass actors is significant: the repository owner and admin cannot bypass
the ruleset either. Protection is not merely advisory.

## Rules applied

| Rule | Configuration |
|---|---|
| `pull_request` | required; conversation resolution required; stale reviews dismissed on push |
| `required_status_checks` | six required checks; strict up-to-date policy enabled |
| `deletion` | branch deletion blocked |
| `non_fast_forward` | non-fast-forward (force push) blocked |

## Required status checks

Read back from the API:

```
validate
Documentation / links
Required Gate
secret-scan
Workflow / actionlint
classify
```

`strict_required_status_checks_policy: true` — a branch must be up to date with
`main` before it can merge.

All six are required **independently**. This matters: `Required Gate` can only
depend on jobs inside its own workflow file, so it cannot itself gate the
Security and Runtime Detection workflows. Requiring all six at the ruleset level
closes that gap rather than relying on the gate to cover workflows it cannot see.

## Enforcement proven, not assumed

Reading configuration back only proves the configuration was stored. Enforcement
was verified by attempting a real direct push to `main`:

```
$ git push origin main
remote: error: GH013: Repository rule violations found for refs/heads/main.
 ! [remote rejected] main -> main (push declined due to repository rule violations)
error: failed to push some refs
```

The push was rejected. `main` is genuinely protected. The probe commit was
discarded with `git reset --soft HEAD~1` and never reached the remote.

## Plan constraint encountered

Creating this ruleset while the repository was PRIVATE was rejected:

```
HTTP 403
"Upgrade to GitHub Pro or make this repository public to enable this feature."
```

GitHub's free plan does not support rulesets or branch protection on private
repositories. The repository owner elected PUBLIC visibility so that enforcement
could be applied. See `repository-verification.md` and `AMENDMENT-0001`.

## Approval policy

```
SINGLE-MAINTAINER GOVERNANCE
```

`required_approving_review_count` is **0**. This repository has one maintainer,
and GitHub does not permit self-approval of one's own pull request, so requiring
an approval would create a permanent deadlock rather than real assurance.

**No claim of independent human review is made.** The foundation PR was not
reviewed by a second person. What remains genuinely enforced is: pull requests
are mandatory, all six status checks must pass, conversations must be resolved,
branches must be up to date, force pushes and deletions are blocked, and nobody
can bypass any of it.

This is recorded honestly rather than presented as peer review.
