# DEC-0027 — Local Development Environment Bootstrap and Template Contract

**ID:** DEC-0027
**Title:** Local Development Environment Bootstrap and Template Contract
**Status:** ACCEPTED
**Date:** 20 July 2026

---

## Context

Step 3 introduced a real Laravel backend that must authenticate to a real PostgreSQL instance. That
made the local environment contract load-bearing for the first time: before Step 3 there was no
runtime that could read an environment file, so a divergence between two example files was
inconsequential.

Six facts drove this record:

1. **The root and backend example files had diverged.** `.env.example` carried the canonical local
   development values; `backend/.env.example` still carried `DB_PORT=5432`,
   `DB_DATABASE=replace_with_local_database_name`, and `DB_USERNAME=replace_with_local_username`.
   `DB_HOST` and `DB_PASSWORD` were already correct in both files and were not part of the divergence.

2. **Correcting only the backend values did not guarantee a reproducible fresh clone.** Making the two
   files agree fixes the *contents* of a template. It does not create the file the application reads.

3. **The documented setup only instructed creation of the root `.env`.**
   `docs/runtime/LOCAL_DEVELOPMENT.md` said, in full: "Copy `.env.example` to `.env`." There was no
   instruction anywhere in the committed documentation to create `backend/.env`, and
   `scripts/bootstrap-step-03.sh` contained no environment-file step at all.

4. **Laravel actually reads `backend/.env`.** A fresh clone followed exactly as documented therefore
   produced no `backend/.env` whatsoever, and the backend could not authenticate.

5. **An existing local `backend/.env` concealed the bootstrap defect.** The maintainer's working
   repository already held an ignored, correct `backend/.env` from earlier manual work. Every local
   run therefore succeeded, and the gap was invisible from inside that checkout.

6. **Local success based on pre-existing ignored files is not fresh-clone evidence.** This is the
   general lesson, not an incidental detail: a reproducibility claim verified only in an environment
   that already contains untracked state proves nothing about a clean checkout (Rule 47).

A prior-session fresh-clone run is reported to have failed with an `SQLSTATE[08006]` authentication
error against an unresolved placeholder username. That run is recorded here as
**REPORTED FROM A PRIOR SESSION — NOT RE-VERIFIED IN STAGE 1 OR STAGE 2A**. It is consistent with the
defect described above, but this record does not rely on it: facts 1, 3, 4, and 5 were each observed
directly by command, and each is independently sufficient.

## Options considered

**Option 1 — correct `backend/.env.example` and stop there.**
Rejected. It fixes template values while leaving the fresh clone unable to produce `backend/.env` at
all. It would also have produced a passing validator alongside a still-broken clone, which is worse
than no validator: it launders the defect into apparent assurance.

**Option 2 — instruct both copies in documentation only.**
Rejected. Documentation is not an executable path. The defect being corrected *was* a documentation
gap, and a second documentation-only instruction has exactly the same failure mode — it is not
checked, and nothing fails when it drifts.

**Option 3 — an executable, idempotent, non-overwriting bootstrap plus a fail-closed validator.**
**Adopted.** The path becomes runnable, the contract becomes machine-checked in CI, and an existing
local file is never destroyed.

## Decision

1. **`.env.example` is the canonical source of shared local-development values.** Where the two
   templates disagree, the root file is correct and the backend file is the defect.

2. **`backend/.env.example` is the Laravel template and must match the canonical `DB_*` contract**
   exactly, for `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

3. **Bootstrap creates `.env` from `.env.example` when `.env` is absent.**

4. **Bootstrap creates `backend/.env` from `backend/.env.example` when `backend/.env` is absent.**

5. **An existing destination file is never overwritten.** A developer's local overrides survive every
   bootstrap run, and bootstrap is idempotent.

6. **Template and documentation consistency is automatically validated.**
   `scripts/validate-dev-environment-contract.py` enforces both the template contract and the
   existence of the executable bootstrap path, and runs inside the `runtime-foundation` context and
   the canonical Step 3 verifier. Template drift is a CI failure, not a later discovery.

7. **Committed credentials remain fictional and local-development only.** The committed password
   carries an explicit `CHANGEME` / `LOCAL_DEV_ONLY` marker so that it is recognisably fabricated to
   an outside reader of this `PUBLIC` repository (Rule 45, Rule 23).

8. **Host-side PostgreSQL uses port `55433`.** This is the published host port from
   `infrastructure/docker-compose.dev.yml` (`127.0.0.1:55433:5432`).

9. **Container-internal PostgreSQL may use port `5432`.** The internal listener is not interchangeable
   with the published host port, and GitHub Actions service containers legitimately use `5432` on
   their own network. Replacing an internal container port with the published host port is a defect,
   not a synchronisation.

10. **Staging and production configuration are out of scope.** Nothing in this record authorises a
    deployment, a remote environment, or a non-local credential. Deployment remains `ABSENT`.

11. **No password value may be printed** by any validator, the bootstrap, a log, or an evidence
    artefact. Password assertions report a boolean property only.

## Consequences

A fresh clone can reach the documented backend environment by running one committed command, and the
class of defect that produced this record is now caught by CI rather than by a failed clone.

### Positive consequences

- Fresh clones can reach the documented backend environment without undocumented manual steps.
- Local custom environment files remain owner-controlled and are never destroyed by tooling.
- Bootstrap is safe and idempotent; running it twice changes nothing on the second run.
- Template drift between the two example files becomes a CI failure at the exact candidate SHA.
- The distinction between the published host port and the container-internal port is documented and
  enforced, so neither is "corrected" into the other.
- The validator never prints a credential, so its output is safe to commit as evidence in a `PUBLIC`
  repository.

### Negative consequences / trade-offs

- **The canonical values are now pinned in a validator.** Changing the local database name, user, or
  port requires editing the contract deliberately in `scripts/validate-dev-environment-contract.py`
  as well as both templates. This friction is intentional and is the point of the control, but it is
  real friction.
- **The contract is enforced structurally, not semantically.** The validator proves the templates
  agree and that the bootstrap path exists and is structurally sound. It does not prove that
  PostgreSQL accepts the credentials — only an executed connection does that, and that evidence
  belongs to a fresh-clone run bound to an exact SHA.
- **A second script now exists** (`scripts/bootstrap-env-files.sh`). Delegation was chosen over
  inlining so the behaviour can be exercised in isolation by the adversarial harness, at the cost of
  one more file to keep in view.
- **The overwrite refusal cuts both ways.** A developer holding a stale `backend/.env` will keep it,
  and bootstrap will report it as preserved rather than repairing it. Destroying a local file that may
  contain deliberate overrides was judged the worse failure.

## Verification

Executed at the exact commit SHA recorded in the Step 3 evidence pack (Rule 01, DEC-0013):

- `python3 scripts/validate-dev-environment-contract.py` — the template contract and the bootstrap
  path, fail-closed, with stable machine-readable failure codes;
- `bash scripts/test-dev-environment-contract.sh` — the adversarial harness, which mutates a
  throwaway copy of the repository and asserts that the validator turns **red** for each documented
  violation class and **green** for the legitimate control cases, including four behavioural cases
  that execute the bootstrap itself in a sandbox (create-both, preserve-both, idempotent re-run);
- the harness verifies the working tree is byte-identical before and after, and refuses to report
  success otherwise.

Both are wired into `scripts/verify-step-03.sh` and into the `runtime-foundation` workflow. **No
sixteenth required GitHub status context was created**; the result is reported inside the existing
`runtime-foundation` context and the canonical verifier.

**Not verified by this record.** That the corrected bootstrap produces a working database connection
from a genuinely clean checkout is a fresh-clone claim. It requires its own executed run, bound to its
own exact SHA, and it is not asserted here.

## Requirement references

`NFR-` reproducibility of the development environment; `SEC-` no committed credential; `TEN-`
unaffected — this record introduces no tenant-scoped behaviour.

## Threat references

Relates to the committed-credential and public-repository disclosure surface (Rule 03, Rule 23,
Rule 45). Introduces no new trust boundary: both files created by the bootstrap are git-ignored, hold
fictional values, and address only a loopback-bound local service.

## Rule references

Rule 01 (status and evidence), Rule 03 (security and privacy), Rule 23 (public repository safety),
Rule 37 (toolchain and dependencies), Rule 43 (database and migrations), Rule 45 (secret and public
repository safety at runtime), Rule 47 (runtime testing and adversarial gates).

## Supersession policy

This record governs the local-development environment template and bootstrap contract from Step 3
onward. Changing the canonical `DB_*` values, widening the accepted host set, permitting an overwrite,
or extending the contract to a non-local environment requires a superseding decision record that names
this one explicitly. Narrowing the contract — stricter validation, additional checks, more mutation
cases — requires no new record.

Nothing in this record authorises deployment, a staging environment, a remote credential, or any Step
4+ business feature.

## Related Master Source sections

Master Source §6 (backend and API foundation), §15.8 (public repository safety), §24 (roadmap and step
locking). Builds on DEC-0013 (exact-SHA evidence), DEC-0016 (public repository visibility), and
DEC-0024 (Step 3 runtime introduction).
