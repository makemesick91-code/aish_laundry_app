# Governance follow-up — the destructive-operations guard blocks a legitimate PR-body edit

**Raised from:** the Step 3 corrective authentication remediation (PR #19)
**Status:** OPEN — recorded for the owner, deliberately NOT acted on in this PR
**Date:** 21 July 2026

---

## What happened

Updating the body of pull request #19 was attempted three ways. All three failed:

1. `gh pr edit 19 --body "..."` — fails with a GitHub-side error unrelated to this
   repository:
   `GraphQL: Projects (classic) is being deprecated ... (repository.pullRequest.projectCards)`.
   `gh pr edit` reads `projectCards` while resolving the PR, and that field is now
   retired, so the whole mutation aborts. Nothing local can fix this.
2. `gh pr edit 19 --body-file <file>` — fails identically, same GraphQL path.
3. `gh api -X PATCH repos/<owner>/<repo>/pulls/19 --input <file>` — blocked by
   `.claude/hooks/guard-destructive-operations.sh`:

   ```
   BLOCKED: repository visibility/settings/protection changes via 'gh api'
   require owner approval (AMENDMENT-0001).
   ```

The guard was **not** edited, weakened, or bypassed, and the block is not being
reported as a defect in the guard's intent. The update was published as a PR
comment instead, which is the non-bypassing path.

## A second, separate false positive: the guard matches message TEXT

While committing this very file, the guard also refused:

```
git commit -m "<message describing the blocked call>"
```

The message merely *described* the blocked invocation. No API call was being
made — the command was a local commit. The guard inspects the whole command
string, so quoting the pattern inside a commit message is enough to trip it.

The practical effect is that an incident cannot be described in the commit that
records it, using the words that identify what happened. The message had to be
reworded. This is a matching-scope observation, not a request to relax the
guard, and it is recorded here because a future maintainer will otherwise hit it
and be tempted to reach for `--no-verify`.

## Why this is worth the owner's attention

The guard's rule is right: `gh api` is a general-purpose write surface, and
unrestricted `PATCH` through it can change repository visibility, branch
protection, or ruleset configuration — exactly the owner-territory changes
AMENDMENT-0001 and Rule 11 reserve.

The pattern is, however, broader than that intent. `PATCH /repos/{owner}/{repo}/pulls/{n}`
edits a pull request's title, body, base branch or state. It cannot change
visibility, protection, or settings. It is the same class of action as
`gh pr edit`, which the autonomous-execution policy explicitly permits
(`.claude/rules/12-autonomous-execution.md` — "Opening or updating a pull request").

So the current combination has a concrete consequence: with `gh pr edit` broken
upstream, **there is no permitted path to correct a pull request description at
all.** In this remediation that mattered — the description written at PR
creation predates a credential-storage regression found later, and the
correction had to live in a comment rather than in the description a reviewer
reads first.

## What is NOT being proposed

No change to the guard is proposed here, and none was made. Narrowing a
destructive-operations pattern is owner territory, and doing it inside a
corrective PR — while relying on that same guard's protection — would be
precisely the "edit the guard to get past it" move Rule 12 forbids.

## Options for the owner

1. **Leave as is.** Accept that PR descriptions cannot be edited while `gh pr edit`
   is broken upstream, and use comments. Zero risk, some loss of legibility.
2. **Narrow the guard pattern** so that `gh api` writes to
   `/repos/{owner}/{repo}/pulls/*` and `/issues/*` are permitted, while writes to
   `/repos/{owner}/{repo}` itself, `/branches/*/protection`, `/rulesets/*` and
   `/actions/permissions/*` stay blocked. This preserves the actual intent and
   restores an ability Rule 12 already grants.
3. **Wait for upstream.** The `gh pr edit` failure is a GitHub CLI/API
   deprecation issue and will likely be fixed in a future `gh` release.

Option 2 is the one that matches the written policy, but it is a change to a
fail-closed security control and therefore needs an explicit owner decision —
and, if taken, its own decision record and adversarial re-test of the guard
(`scripts/test-destructive-guard.sh`).

## Verification if option 2 is ever taken

Any narrowing must be adversarially tested before it is trusted: the guard must
still block `gh api -X PATCH repos/<owner>/<repo>` (visibility),
`.../branches/main/protection`, and `.../rulesets/<id>`, and must still block
`gh repo delete` and `gh repo archive`. A narrowing that is not shown to fail on
those inputs has not been tested (Rule 47).
