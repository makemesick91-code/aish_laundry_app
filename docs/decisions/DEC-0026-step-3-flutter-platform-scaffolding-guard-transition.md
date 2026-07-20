# DEC-0026 — Step 3 Flutter Platform Scaffolding Guard Transition

**ID:** DEC-0026
**Title:** Step 3 Flutter Platform Scaffolding Guard Transition
**Status:** ACCEPTED
**Date:** 20 July 2026

---

## Context

Steps 0–2 created no runtime, and `.claude/hooks/guard-destructive-operations.sh` enforced that with
an **unconditional** block on `flutter create` and `dart create`. That rule was correct for its
period.

DEC-0024 then authorised Step 3 runtime, and Master Source moved to 1.4.0. The guard, however, still
carried the unconditional Step 0 prohibition. It therefore did exactly what it was written to do —
it blocked `flutter create` — while no longer representing current canon.

The consequence was concrete: `apps/customer_android/android/`, `apps/ops_android/android/`, and
`apps/admin_web/web/` could not be generated, so all three builds failed and no build result could be
claimed. The Dart workspace, nine shared packages, three application shells, and 187 passing Flutter
tests existed, but nothing could be compiled.

Three resolutions were available, and two were wrong:

- **Hand-authoring the platform directories.** Rejected. It produces files the official generator
  would have produced, without the generator's guarantees, and it evades the guard in spirit while
  technically not editing it. CLAUDE.md §10 forbids routing around the guard, and hand-writing its
  output is routing around it.
- **Removing or globally weakening the block.** Rejected. It would drop protection against every
  unapproved Flutter scaffold at exactly the moment the repository first gains the ability to create
  one.
- **Making the block phase-aware.** Adopted.

## Options considered

**Option 1 — delete the `flutter create` rule.**
Rejected: removes enforcement rather than transitioning it, and permits arbitrary Flutter projects
anywhere in the tree.

**Option 2 — allow `flutter create` whenever DEC-0024 exists.**
Rejected. A decision record is a file, and a file can be created. Authorisation keyed on the mere
existence of a filename is authorisation an attacker — or a careless agent — can manufacture. The
guard must verify the canonical state itself.

**Option 3 — a narrow, fail-closed, phase-aware authorisation.**
**Adopted.** One command family, three exact targets, one platform each, gated on independently
re-verified canonical state.

## Decision

1. **The official Flutter platform generator is permitted for exactly three targets:**
   `apps/customer_android`, `apps/ops_android`, `apps/admin_web`. No fourth application, no package
   directory, no path outside the approved runtime roots.

2. **Each target permits exactly one platform:** `android` for `customer_android` and `ops_android`,
   `web` for `admin_web`. `--platforms` must be present and must equal the approved value. iOS,
   macOS, Windows, and Linux desktop are refused, as is any unrestricted multi-platform request.

3. **`dart create` remains unconditionally blocked.** No approved scaffold requires it. Authorising it
   would need its own decision record.

4. **Authorisation is never granted on the existence of a decision file alone.** Before allowing the
   command the guard independently verifies: the canonical repository path; the `makemesick91-code/aish_laundry_app`
   remote; a Step 3 feature branch and never `main`; Master Source version 1.4.0; a validating Master
   Source checksum; DEC-0024 and DEC-0026 both present and `ACCEPTED`; `docs/STATUS.md` reporting
   Step 3 `IN PROGRESS` with no Step 4+ entry advanced; and the Step 0–2 tags still peeling to their
   recorded commits. **Every check fails closed.**

5. **All unrelated guard prohibitions are preserved**, including recursive deletion, history rewriting,
   force push, tag deletion, database destruction, container pruning, deployment tooling, and the
   Step 0–2 historical protections.

6. **Destructive, publishing, and signing options are refused** — `--overwrite`, `--force`,
   `--delete`, and anything naming publish, deploy, keystore, or upload.

7. **Path traversal and symlink escape are refused.** The target is resolved before containment is
   judged; `..` and absolute paths are rejected outright.

8. **Example identifiers are refused** — `com.example`, `my_app`, `untitled`, or a bare `example`.

9. **The guard emits a sanitised authorisation record**, never full command arguments:
   `AUTHORIZED: STEP_3_FLUTTER_PLATFORM_SCAFFOLDING` with category, repository, branch, target,
   platform, Master Source version, decisions, step status, and result. There is no generic
   "bypass" or unrestricted allow result.

10. **Scaffolding is not a build.** Generating platform directories permits nothing to be claimed
    about compilation. Each application remains `PLATFORM SCAFFOLDING PRESENT — BUILD NOT VERIFIED`
    until a real build exits zero and an artefact exists.

11. **Step 4+ features and all deployment remain prohibited**, unchanged by this decision.

12. **`pubspec.lock` is an approved reproducibility artefact**, tracked deliberately and fully subject
    to secret and public-repository scanning. It is permitted at the canonical workspace root and
    inside approved application or package roots. It authorises nothing else: `.dart_tool/`, `build/`,
    caches, binaries, and APKs remain excluded, and a lockfile outside an approved root still fails.

## Consequences

Android and Web platform scaffolding can be generated reproducibly with the official toolchain. The
guard becomes phase-aware rather than globally weakened. Arbitrary Flutter projects, extra platforms,
and unapproved targets remain blocked.

### Positive consequences

- Step 3 can complete without any protection being removed.
- Enforcement is **stronger after this change than before** for the command it governs: previously
  `flutter create` was blocked outright with no notion of target, platform, path traversal, example
  identifiers, or destructive flags. Those are now all checked.
- The authorisation is auditable — every allow prints why it was allowed.
- Because authorisation is re-verified per invocation, a drifted Master Source, a moved tag, an
  advanced Step 4, or a checkout on `main` all revoke it automatically.

### Negative consequences / trade-offs

- **The guard is now more complex**, and complexity in a security control is itself a risk. A defect
  in the verification logic could grant an allow that should not be granted. This is mitigated by a
  27-case adversarial suite and by every branch defaulting to deny, but not eliminated.
- **The pinned commit SHAs and repository path are embedded in the guard.** Legitimate changes — a
  new Step 3 continuation branch, a repository move — will break authorisation until the guard is
  updated. That friction is deliberate.
- **The guard now reads several files** (Master Source, STATUS, decisions) and consults git. If any is
  unreadable the command is refused, which is correct but can look like an unrelated failure.
- Under single-maintainer governance (DEC-0017), a defect both the maintainer and the adversarial
  suite miss is not caught. That residual risk is accepted, not eliminated.

## Verification

The amendment is delivered as `scripts/owner/apply-dec-0026-guard-amendment.sh`, run by the
repository owner. It refuses to patch a locally-modified guard, backs the file up, and **restores the
backup automatically if the guard's own `--self-test` regresses or if `dart create` stops being
blocked.**

`scripts/test-dec-0026-guard.sh` exercises the amended guard against **3 allowed controls and 24
denial cases**, asserting for each that the intended command was evaluated, that allow or deny
occurred **for the intended reason**, that the guard remained enabled, and that the repository is
restored byte-identically. A denial caused by an unrelated fixture is treated as an invalid result,
not a pass.

### Superseded verification runs — recorded, not erased

**Run 1 — in-session edit, ABORTED.** The dispatch referencing
`_step3_flutter_scaffold_authorized` was written before the function itself, and the second edit was
refused by the harness. The guard failed **closed**, blocking every command, and was reverted to a
byte-identical committed state with its self-test passing 171/171. No unsafe command ran. This is why
the amendment is delivered as a single atomic owner-run script with automatic rollback.

**Run 2 — owner applied, FAILED VERIFICATION, rolled back. 5/29 met, 24 failed.**
All three approved controls were refused with
`docs/STATUS.md does not report Step 3 as IN PROGRESS`, and command-specific cases D1–D20 were denied
by that same earlier check before reaching the condition they were written to test. The harness
correctly classified those as **invalid results rather than security passes**.

The root cause was **not** the guard. `docs/STATUS.md` genuinely declared
`| Step 3 | … | PLANNED |`, stated *"Step 3 has not begun"*, and listed the backend runtime and
Flutter workspace as `ABSENT` — while runtime had in fact been committed across several prior
commits. The guard read the canonical status correctly and refused correctly; **the canonical status
was false.**

That drift had been invisible because `scripts/validate-status.py` carried `CURRENT_STEP = 2` and
*required* the strings "backend runtime is ABSENT" and "Flutter workspace is ABSENT". The validator
was enforcing the untruth rather than catching it. Corrected by: raising `CURRENT_STEP` to 3; making
the runtime-absence declarations apply only while `CURRENT_STEP < 3`; and adding
`check_runtime_matches_reality()`, which compares each claim against an artefact on disk so a status
file cannot assert `ABSENT` while `backend/composer.json` or the workspace `pubspec.yaml` exists.

Neither superseded run may be cited as evidence that this control works.

**Run 3 — corrected, 38/38 met, 0 failed**, executed against a **disposable patched copy** of the
guard so the canonical hook was never modified during verification. Three approved controls
authorised; twenty-one command-specific denials and eleven canonical-state denials each asserted the
intended reason; execution outside the canonical repository denied; guard self-test 171/171; the
undefined-function regression explicitly tested; working tree byte-identical before and after.

Two defects were found and fixed by that run itself: a target of `.` was denied as "outside the
repository" when the repository root is in fact *inside* it and simply unapproved — a correct denial
for an inaccurate reason, which the harness reports as invalid; and the execution-context case
concatenated a repository prefix onto an already-absolute path, producing exit 127 rather than a
denial.

### Run 4 — owner-applied and independently re-verified: ACTIVE

The repository owner applied the amendment from the canonical baseline
`55b4059f6a8174de3d62cfc57b279840130a0b0f` with:

```
bash scripts/owner/apply-dec-0026-guard-amendment.sh
```

The owner's reported results were **not accepted on report**. Every one was reproduced
independently before this decision was marked active:

| Check | Independent result |
|---|---|
| Applied guard vs. the patch the script generates | **byte-identical** (reproduced on a clean copy of `HEAD` and diffed) |
| Scope of change | one file, `.claude/hooks/guard-destructive-operations.sh`, `+215 / -6` |
| `.claude/settings.json` | **unmodified** — no agent hook permission was granted |
| Other hooks | none exist; none modified |
| `bash -n` | syntax OK |
| Guard self-test | **171/171 PASS** |
| DEC-0026 adversarial suite | **38/38 met, 0 failed**, working tree byte-identical |
| Direct allow/deny matrix (3 allows, 17 denials, run outside the suite) | **0 mismatches**, every denial for its intended reason |
| Step 0–2 tags | annotated and unmoved at `8494bc85`, `4eadbc73`, `47c07d36` |
| Governance / status / secrets / PII / links | 7/7 · 28/28 · 10/10 · 14/14 · PASS |
| Step 0/1/2 regressions | PASS · PASS · PASS |
| Step 3 runtime-scope harness | 36/36, byte-identical |
| classify | `STEP_3_RUNTIME_FOUNDATION_WITHIN_SCOPE` |

Confirmed blocked, each for the correct recorded reason: `dart create`; an unrestricted
`flutter create` with no `--platforms`; a fourth application; every wrong app/platform pairing;
multi-platform requests; the repository root; a package root; `..` traversal; absolute paths;
`--overwrite` and `--force`; `--deploy` and `--keystore`; `com.example`; and `my_app`.

The gate is **ACTIVE** from this commit. It remains owner-controlled: the agent cannot edit the hook,
and no general permission to edit hooks was granted.

### Deterministic parsing

Two loose reads were replaced with deterministic ones:

- **Step status** is parsed from the `CANONICAL_STEP_STATE` block in `docs/STATUS.md`, never from
  prose or a markdown table. Exactly one block; duplicate blocks, duplicate keys, a missing key, an
  unknown status value, or absent markers all fail closed. `validate-status.py` additionally fails if
  the machine-readable block and the human-readable table disagree, so neither can drift alone.
- **Decision acceptance** requires exactly one formal `**Status:**` field reading exactly `ACCEPTED`.
  A filename proves nothing, duplicate status fields fail, and prose mentioning "accepted" elsewhere
  does not satisfy it.

## Requirement references

`NFR-` class requirements covering reproducible builds and toolchain governance; `SEC-` class
requirements covering least privilege and change control on security tooling.

## Threat references

Addresses: unapproved Flutter project creation; extra-platform generation expanding the attack
surface; path traversal and symlink escape out of approved roots; example identifiers reaching a
published artefact; destructive overwrite of existing application source; and authorisation forged by
creating a decision file.

## Rule references

Rule 00 (canonical source, version and checksum discipline), Rule 01 (status vocabulary — scaffolding
is not a build), Rule 11 (owner territory), Rule 12 (autonomous execution boundaries and the
non-negotiable guard), Rule 23 (public repository safety), Rule 33 (adversarial validator testing),
Rule 36 (runtime architecture and approved roots), Rule 37 (toolchain and dependency governance).

## Supersession policy

Superseded only by a later accepted decision record naming DEC-0026 explicitly. Adding a fourth
application, an additional platform, `dart create`, or any new scaffolding generator requires its own
decision record; none is covered here. Narrowing the authorisation needs no new decision — a guard
may always be made stricter.

## Related Master Source sections

§5 platforms; §6 architecture; §15 security; §24 roadmap and step locking; §25 governance. Recorded at
Master Source version **1.4.0**. This decision changes no product decision and introduces no product
capability, so it carries no further version bump.
