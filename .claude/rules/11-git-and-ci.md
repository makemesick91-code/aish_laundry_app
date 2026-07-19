# Rule 11 — Git Workflow and Continuous Integration

## Purpose

To keep `main` trustworthy, keep history honest, and keep the automation that runs on this repository
from becoming an attack surface. This repository is **PUBLIC** by deliberate owner decision
(AMENDMENT-0001), which raises the stakes on both supply chain and workflow permissions.

Remote repository: `makemesick91-code/aish_laundry_app`. Default branch: `main`.
Local monorepo root directory: `aish_laundry` (ASSUMPTION-0001, resolved and accepted).

## Branch model

1. `main` is the protected default branch and always represents the accepted state of the project.
2. **All changes reach `main` through a pull request.** Direct pushes to `main` are forbidden, for
   humans and agents alike.
3. Work happens on descriptive feature branches, e.g.
   `feature/step-00-master-source-and-governance`.
4. One branch, one coherent purpose. Do not mix a governance change with an unrelated fix.
5. Branches are deleted after merge by the owner or by repository settings — never by an agent
   running `git branch -D` (blocked by the destructive-operations guard).

## Commits

6. **Conventional Commits** are mandatory: `feat:`, `fix:`, `docs:`, `chore:`, `ci:`, `refactor:`,
   `test:`, plus an optional scope, e.g. `docs(step-00): add master source`.
7. Commit messages describe what changed and why. They never claim results that were not verified
   (Rule 01).
8. **History on shared branches is never rewritten.** No force push, no rebase of pushed history, no
   amend of a pushed commit.
9. No secrets, tokens, credentials, or customer data in commits — including in messages and in
   deleted-then-recommitted files (Rule 03).

## Pull requests

10. A pull request states: what changed, which step it belongs to, what was verified, at which exact
    SHA, and what remains unverified.
11. Status language in a pull request uses the approved vocabulary only. **Never write `GO` as the
    Step 0 status** (Rule 01).
12. Merging to `main` is an owner decision. An agent may open and update a pull request; it does not
    merge one on its own initiative (Rule 12).

## Tags and releases

13. Tags are **annotated** (`git tag -a`), never lightweight, and carry a message.
14. Tags are **immutable**. A published tag is never moved, deleted, or re-pointed. If a tag is
    wrong, publish a new one and document why.
15. `git tag -d` and `git push --delete` are blocked by the destructive-operations guard.

## Continuous integration

16. **CI evidence is bound to an exact SHA.** A CI result belongs to the commit it ran against and
    does not transfer to any other commit (DEC-0013, Rule 01).
17. **GitHub Actions are pinned to a full commit SHA**, not a floating tag such as `@v4`. A moving
    tag is a supply-chain risk on a public repository.
18. **Workflow permissions are least-privilege.** Set `permissions:` explicitly at the workflow or
    job level, defaulting to `contents: read`, and grant nothing beyond what the job needs.
19. Workflows never echo secrets, never write secrets to logs or artifacts, and never expose them to
    code from forks.
20. Workflows do not push to `main`, do not create or move tags, and do not change repository
    settings or visibility.

## Application CI status

**Application CI is `NOT APPLICABLE` in Step 0** — there is no application to build or test. Step 0
automation is limited to governance validation (Rule 13). Claiming an application build or test
pipeline exists is a false claim under Rule 01.

## Repository settings

21. Repository visibility, branch protection, rulesets, collaborators, and secrets are **owner
    territory**. An agent never changes them. Unrequested visibility changes via `gh api` are blocked
    by the destructive-operations guard.
22. Never describe this repository as private. It is **PUBLIC** by deliberate owner decision — an
    accepted deviation from a canonical desired PRIVATE, locked by
    [DEC-0016](../../docs/decisions/DEC-0016-public-repository-visibility-accepted-deviation.md). PUBLIC
    visibility is what buys platform-enforced branch protection on a free plan; it is the reason the
    ruleset can be enforced at all, and it is also why every commit must satisfy the authoring
    constraints in Rule 03.
23. **A future move to PRIVATE requires re-verifying the ruleset after the change.** Changing repository
    visibility can silently drop ruleset enforcement, which would turn a governance improvement into a
    governance regression. Visibility changes remain owner territory (rule 21) and require a superseding
    decision record.

## Violation handling

- **A direct push to `main`** — stop, notify the owner immediately, and do not attempt to "fix" it by
  rewriting history. Rewriting the shared history compounds the damage.
- **A force push, hard reset, or history rewrite on a shared branch** — automatic escalation to the
  owner; treat any lost work as an incident and recover from reflog or remote copies before anything
  else proceeds.
- **A secret committed** — rotate first, then remove (Rule 03). Removal alone is not remediation on a
  public repository.
- **An unpinned action, or a workflow with broad `write` permissions** — reject the workflow change
  until pinned and scoped down.
- **A tag moved or deleted** — record the incident, restore the original target if possible, and
  publish a corrective tag.
- **An agent changing repository settings or merging to `main` without owner instruction** — treat as
  a serious autonomy breach under Rule 12 and disclose it plainly.
