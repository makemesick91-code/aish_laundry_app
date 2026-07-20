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

Recorded honestly: an earlier attempt to apply this amendment inside the agent session left the guard
temporarily broken — the dispatch referencing `_step3_flutter_scaffold_authorized` was written before
the function itself, and the second edit was refused by the harness. The guard failed **closed**,
blocking every command, and was reverted to a byte-identical committed state with its self-test
passing 171/171. No unsafe command ran. The ordering error is why the amendment is now delivered as a
single atomic owner-run script with automatic rollback.

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
