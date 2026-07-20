# Rule 37 — Toolchain and Dependencies

## Purpose

A pinned toolchain that quietly becomes "whatever is cached on this laptop" is not pinned at all. Step
3 is the first step where a real SDK, a real package manager, and real container images enter the
repository, and this rule fixes the discipline that keeps them reproducible, approved, and honestly
reported — rather than assumed to work because they happened to work once, locally.

## Hard rules

1. **Toolchain versions are pinned in a single canonical record** — the Flutter and Dart SDK, PHP and
   Composer and the Laravel version, PostgreSQL, Redis, and the Android SDK/NDK versions used for
   builds. A version used in practice that disagrees with the recorded pin is a defect in the pin or in
   the environment, and it is corrected rather than left to drift silently.
2. **A pinned version is never silently downgraded to match whatever happens to be available or cached
   locally.** Substituting a locally cached image for the pinned one turns the pin into fiction and
   makes any "authoritative version" claim false. If a pin cannot be honoured, the pin is fixed and the
   discrepancy is reported — the requirement is not quietly lowered to fit reality.
3. **Dependency and workspace manifests are confined to their approved locations** (Rule 36, hard rule
   3): `pubspec.yaml` and `analysis_options.yaml` at the repository root for the Dart workspace, and
   every other manifest — `composer.json`, package manifests, lockfiles — inside its own approved
   runtime root. A manifest at the repository root that is not one of those two files is out of scope.
4. **Introducing a new third-party dependency, SDK, or paid provider requires explicit owner approval**
   before it is added to any manifest (Rule 12). An agent does not add a package because it is
   convenient; it stops and asks.
5. **A container image or package pulled through a mirror or proxy registry is pinned by digest, not by
   a floating tag.** A digest pin means a compromised or substituted mirror cannot silently deliver
   different content under the same name; a floating tag offers no such protection.
6. **Development seeds and fixtures used to exercise the toolchain contain only fictional data.** No
   real customer name, phone number, address, or credential is used to seed a local database, however
   convenient a "realistic-looking" example would be (Rule 23 of `CLAUDE.md`, Rule 45).
7. **A toolchain component is reported `PREPARED` only once its actual build has been executed and the
   output captured.** Being installed is not the same as being proven to build; a status of `PREPARED —
   BUILDS NOT YET VERIFIED` is the honest interim state and is used until a real build result exists
   (Rule 01).
8. **Lockfiles are committed for every manifest that supports one.** A dependency resolution that is not
   reproducible from a lockfile is not reproducible at all, and later steps would build on a moving
   target.

## Step 3 note

The toolchain exists to the extent recorded in the development environment: PostgreSQL and Redis run as
local, loopback-bound development services (Rule 43, Rule 44). No Flutter or Laravel project has been
scaffolded inside an approved root as of this rule's writing, so no application build has yet been
executed or verified. **The Android toolchain is `PREPARED — BUILDS NOT YET VERIFIED`**, not `TESTED`,
until a real build is run and its output captured at an exact commit SHA (Rule 49).

## Violation handling

- **A dependency manifest found outside its approved root, or at the repository root under a name other
  than `pubspec.yaml` or `analysis_options.yaml`** — reject; move it or remove it before the change
  proceeds.
- **A pinned version silently substituted for a locally available one** — treat as falsified evidence;
  restore the pin and disclose the substitution.
- **A new dependency, SDK, or provider added without owner approval** — revert and escalate (Rule 12).
- **A floating tag used where a digest pin was expected** — reject; require the digest before merging.
- **Real personal data found in a development seed or fixture** — remove immediately and replace with a
  fictional value; treat as a Rule 23 disclosure if it was already pushed.
- **A toolchain component reported `TESTED` or `WORKING` with no captured build output** — correct the
  status to `PREPARED — BUILDS NOT YET VERIFIED` or `ABSENT`, whichever is accurate, and report that the
  earlier claim was wrong.
