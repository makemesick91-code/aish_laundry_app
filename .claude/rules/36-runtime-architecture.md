# Rule 36 — Runtime Architecture and Scope

## Purpose

Steps 0, 1, and 2 were governed by a single, legible guarantee: no runtime exists. Step 3 ends that
guarantee on purpose, and this rule fixes exactly what replaces it — where runtime may now exist, what
still may not exist anywhere, and how the transition itself stays provable rather than assumed.

Backed by **DEC-0024 — Step 3 Runtime Introduction and Runtime Scope Guard Transition**.

## Hard rules

1. **`docs/MASTER_SOURCE.md` version 1.4.0 is the highest authority for every Step 3 runtime
   decision.** A runtime choice that the Master Source does not cover is an open question for the
   repository owner, never a licence for an agent to invent one (Rule 00).
2. **DEC-0024 is the sole authorization for introducing runtime, and it authorizes Step 3 foundation
   runtime only.** It does not authorize Step 4 or later business features, and it does not authorize
   deployment. A later step that wants to widen scope needs its own decision record; DEC-0024 does not
   cover it in advance.
3. **Runtime may exist only inside the approved roots**: `apps/customer_android/`,
   `apps/ops_android/`, `apps/admin_web/`, `packages/design_system/`, `packages/core/`,
   `packages/domain/`, `packages/auth/`, `packages/networking/`, `packages/local_storage/`,
   `packages/offline_sync/`, `packages/observability/`, `packages/testing/`, `backend/`, and
   development-only runtime files under `infrastructure/`. Project-level workspace and toolchain
   manifests are limited to `pubspec.yaml` (Dart workspace root) and `analysis_options.yaml` (shared
   lints) at the repository root. A runtime manifest or application source file outside an approved
   root fails, with no exception for convenience or a "temporary" location.
4. **Step 4 and later business modules are forbidden**, regardless of how they are named. Detection is
   **structural** — migration filenames, `Schema::create` table arguments, route path segments,
   Eloquent model class names, and module or feature directory names — never naive prose substring
   matching. A guard that flagged the word "order" would fire on `orderBy()` and on the phrase "in
   order to," and would still miss a POS module named `kasir`. Renaming a forbidden feature to evade
   structural detection is treated as the same violation as building it under its plain name.
5. **The required CI status check context is exactly `classify`, and it is never renamed.** The
   workflow's reported classification states change as scope evolves; its published check name does
   not. Renaming it would silently drop a required check from the active ruleset (ID `19164588`).
6. **Runtime presence is not proof of runtime correctness.** The `classify` job reports scope
   classification only — it executes no application test and claims no application test result. A
   green `classify` check means placement was legal, not that a feature works, that authentication is
   secure, or that tenant isolation holds.

## Guard versioning

7. **The runtime-scope guard (`scripts/validate-runtime-scope.py`) governs current `main` and Step 3
   onward.** The historical absence guard (`scripts/validate-no-runtime.py`) remains the validator for
   the immutable Step 0, Step 1, and Step 2 `GO` tags. Neither guard is applied backwards across that
   boundary: the scope guard never re-judges a prior tag, and the absence guard never re-judges current
   `main`.
2. *(continued)* A prior `GO` was conferred against the rule that existed at the time. Applying a later
   rule to an earlier tag would invalidate a `GO` on a standard that did not exist when it was granted.
8. **The scope guard may only be narrowed, never silently widened.** Making the guard stricter needs no
   decision record. Widening the approved runtime roots, adding a runtime language, or authorizing
   deployment requires its own decision record naming what it supersedes (DEC-0024's supersession
   policy).
9. **A symlink that escapes the repository root is treated as a scope violation**, regardless of what
   it points to or why it was added.

## Step 3 note

Runtime is no longer categorically absent, but almost nothing is built. `apps/customer_android/`,
`apps/ops_android/`, `apps/admin_web/`, `backend/`, and the `packages/*` directories exist as approved
locations; most contain only a `README.md`. **Backend runtime remains `ABSENT`. Flutter workspace
remains `ABSENT`** until an actual `pubspec.yaml` and application source populate an approved root. All
product features — POS, orders, payments, production, tracking, delivery, reminders, finance,
subscription — remain `NOT IMPLEMENTED` no matter how much foundation runtime exists (Rule 42). No
deployment exists anywhere; deployment remains `ABSENT` (Rule 49).

## Violation handling

- **A runtime manifest or source file outside an approved root** — reject; it fails the scope guard by
  design and is not a borderline case.
- **A Step 4+ feature detected by structural signal, however named** — reject outright; renaming it to
  evade detection is a governance breach in itself, not a mitigating factor.
- **The `classify` check renamed, removed, or replaced** — treat as an unauthorized change to the
  required-check set (Rule 11) and revert it.
- **A claim that runtime existing proves a feature works, or that `classify` passing is a test result**
  — false claim under Rule 01; correct it immediately.
- **The scope guard applied to a Step 0–2 tag, or the absence guard applied to current `main`** —
  revert; each guard governs only its own side of the DEC-0024 boundary.
- **A widened runtime root or a new runtime language introduced without a superseding decision record**
  — reject and escalate to the owner.
