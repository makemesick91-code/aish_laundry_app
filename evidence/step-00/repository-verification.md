# Repository Verification — Step 0

## Target

| Item | Value |
|---|---|
| Owner | `makemesick91-code` |
| Repository | `aish_laundry_app` |
| Default branch | `main` |
| Local monorepo root | `/home/fikri/Projects/aish_laundry` (per `ASSUMPTION-0001`, not renamed) |

## Ownership and safety verification

| Check | Result |
|---|---|
| Owner matches authenticated account | YES — `makemesick91-code` |
| Repository contained another project | NO — `isEmpty: true` at discovery |
| Overwrite / force push / delete performed | NONE |
| Other repositories modified | NONE |

The repository already existed but was verifiably empty. Per the Step 0 protocol
for an existing-but-empty target, work continued from `origin/main` rather than
treating the repository as foreign.

## Visibility — deviation from the canonical fact

**Canonical requirement:** `Required visibility: PRIVATE`.
**Actual final visibility:** `PUBLIC`.

This is a real, deliberate deviation and is recorded rather than concealed.

### Sequence of events

1. At discovery the repository was `PUBLIC` and empty.
2. It was set to `PRIVATE` via `PATCH /repos/{owner}/{repo}` with `private=true`.
   Verified: `{"visibility":"PRIVATE","isEmpty":true}`.
3. Creating a branch ruleset on the private repository was rejected:

   ```
   POST /repos/makemesick91-code/aish_laundry_app/rulesets
   HTTP 403
   "Upgrade to GitHub Pro or make this repository public to enable this feature."
   ```

4. The account is on the GitHub free plan. On that plan, rulesets and branch
   protection cannot be applied to a private repository. Private visibility and
   an enforced ruleset are therefore mutually exclusive under the current plan.
5. The conflict was escalated to the repository owner with four options:
   upgrade to GitHub Pro (keeps PRIVATE, achieves the ruleset), stop at the
   foundation PR and close as NO-GO, switch to PUBLIC, or proceed without a
   ruleset and report WATCH.
6. The owner was explicitly informed that PUBLIC contradicts the canonical fact
   and exposes the commercial pricing and product decisions, and that PUBLIC was
   not the recommended option. The owner selected PUBLIC.
7. Visibility was set back to `PUBLIC` by that explicit instruction.
8. The ruleset was then created successfully.

### Consequences that must not be understated

- The canonical fact `Required visibility: PRIVATE` is **not** satisfied.
- All commercial pricing (`DEC-0009`), pricing guardrails (`DEC-0010`),
  positioning, and roadmap decisions in this repository are publicly readable.
- No claim of private visibility may be made anywhere in this repository.

Recorded as `AMENDMENT-0001` in `docs/ASSUMPTIONS.md`.

## Verified final repository state

Command:

```
gh repo view makemesick91-code/aish_laundry_app \
  --json name,owner,visibility,defaultBranchRef,isEmpty
```

Result:

```json
{
  "name": "aish_laundry_app",
  "owner": "makemesick91-code",
  "visibility": "PUBLIC",
  "default": "main",
  "isEmpty": false
}
```

## Main branch bootstrap

`main` was created by committing a bootstrap `README.md` through the GitHub
contents API, so that no Step 0 feature commit was ever made directly on `main`.

| Item | Value |
|---|---|
| Bootstrap commit SHA | `7ff7007420487b0765294fa513163dbe5f2b5bac` |
| Method | `PUT /repos/{owner}/{repo}/contents/README.md` |
| Message | `chore: bootstrap main` |

Confirmed by `git ls-remote origin refs/heads/main`:

```
7ff7007420487b0765294fa513163dbe5f2b5bac	refs/heads/main
```

The Step 0 feature branch `feature/step-00-master-source-and-governance` was
created from `origin/main` at that commit.

## Ruleset

Recorded separately in `ruleset-verification.md`.
