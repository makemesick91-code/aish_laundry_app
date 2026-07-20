# DEC-0024 — Step 3 Runtime Introduction and Runtime Scope Guard Transition

**ID:** DEC-0024
**Title:** Step 3 Runtime Introduction and Runtime Scope Guard Transition
**Status:** ACCEPTED
**Date:** 20 July 2026

---

## Context

Steps 0, 1, and 2 deliberately prohibited **all** application runtime. That prohibition was not
incidental — it was the central guarantee of those steps, mechanically enforced by
`scripts/validate-no-runtime.py` and by the `Runtime Detection / classify` job, which is one of the
twelve required status checks on the active branch ruleset (ID 19164588, `enforcement: active`,
`bypass_actors: 0`).

Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC — is the first canonical step authorised to
introduce runtime: a Flutter workspace, a Laravel backend, PostgreSQL, Redis, and runtime CI.

These two facts are in direct conflict. The absence guard cannot remain the policy for current `main`
without making Step 3 impossible to perform: the first `pubspec.yaml` or `backend/composer.json`
would fail a required check that has no bypass path.

The naive resolutions are both wrong:

- **Deleting the guard** would remove the only mechanical protection against scope leakage at exactly
  the moment the repository first gains the ability to leak scope.
- **Relaxing the guard retroactively** would falsify history: Steps 0–2 genuinely were runtime-free
  when tagged, and that fact must remain provable against their immutable tags.

What is actually needed is a **versioned change of guard semantics**, applied forward only.

## Options considered

**Option 1 — Delete `validate-no-runtime.py` and drop the `classify` check.**
Rejected. It removes enforcement rather than transitioning it, and it changes the published required
check set, which is owner-territory repository configuration (Rule 11).

**Option 2 — Keep the absence guard and exempt Step 3 paths by adding exclusions.**
Rejected. An exclusion list grows silently and inverts the security posture: everything is permitted
unless someone remembered to forbid it. It also gives no protection against Step 4+ features, which
is the actual risk once runtime exists.

**Option 3 — Replace the absence guard with an allowlist-based runtime *scope* guard, and retain the
absence guard as a historical validator executed against the Step 0–2 tags.**
**Adopted.** Enforcement is transitioned rather than removed; history stays provable; and the new
guard is strictly stronger than the old one in every dimension except the single one the owner
authorised.

## Decision

**Option 3 is adopted.**

1. **Step 3 may introduce runtime only inside explicitly approved monorepo locations.** The approved
   runtime roots are:
   `apps/customer_android/`, `apps/ops_android/`, `apps/admin_web/`,
   `packages/design_system/`, `packages/core/`, `packages/domain/`, `packages/auth/`,
   `packages/networking/`, `packages/local_storage/`, `packages/offline_sync/`,
   `packages/observability/`, `packages/testing/`, `backend/`, and development-only runtime files
   under `infrastructure/`. Project-level workspace and toolchain manifests are limited to
   `pubspec.yaml` (Dart workspace root) and `analysis_options.yaml` (shared lints).
   **A runtime manifest outside an approved root fails.**

2. **The old absence guarantee remains historically true** for the immutable Step 0, Step 1, and
   Step 2 `GO` tags. `scripts/validate-no-runtime.py` is retained, unmodified in intent, as the
   historical validator for those tagged snapshots.

3. **Guard semantics are versioned and are not applied retroactively.** The scope guard governs
   current `main` and Step 3 onward. It is never used to re-judge a prior tag, and the absence guard
   is never used to re-judge current `main`. Applying either backwards would invalidate a prior `GO`
   on a rule that did not exist when it was conferred.

4. **Current `main` uses a runtime-scope guard, not a runtime-absence guard:**
   `scripts/validate-runtime-scope.py`, which is **allowlist-based and fails closed**.

5. **The required check context remains exactly `classify`.** The workflow's reported states change;
   its published name does not. Renaming it would silently drop a required check from the ruleset.

6. **The scope guard is at least as strict as the previous guard** regarding secrets, personal data,
   deployment artifacts, unsupported runtime locations, out-of-step business features, and misleading
   implementation claims. Where the previous guard checked absence of runtime, the new guard checks
   *placement* of runtime **and** adds Step 4+ feature detection, deployment detection, credential
   detection, and status-claim honesty, none of which the old guard performed.

7. **Step 4+ business features remain forbidden.** Detection is **structural** — migration filenames,
   `Schema::create` table arguments, route path segments, Eloquent model class names, and
   module/feature directory names — never naive prose substring matching. A guard that flagged the
   word "order" would fire on `orderBy()` and on the phrase "in order to", and would still miss a POS
   module named `kasir`; such a guard produces false positives and false assurance simultaneously.

8. **A runtime manifest is not evidence that a feature works.** The `classify` job reports scope
   classification only. It executes no application test and claims no application test result.

9. **Step 3 `GO` requires** actual Flutter and Laravel builds, executed tests, verified PostgreSQL and
   Redis connectivity, authentication tests, RBAC tests, tenant-isolation tests, exact-SHA CI, a clean
   checkout, and an owner-conferred `GO`. Runtime existing is the beginning of Step 3, not the end.

10. **This decision does not authorise production or staging deployment.** Deployment remains
    `ABSENT`. Deployment artifacts are an explicit failure class in the new guard.

## Consequences

Flutter and Laravel runtime artifacts may now exist inside approved roots. Application CI becomes
applicable once runtime is introduced, and `docs/STATUS.md` changes from `NOT APPLICABLE` only when a
runtime pipeline actually runs. Prior `GO` tags remain immutable and valid. Step 4+ scope leakage
remains an automatic failure, and cross-tenant exposure remains an automatic `NO-GO`.

### Positive consequences

- Step 3 becomes performable without disabling any protection.
- Enforcement against out-of-step features is **stronger after this change than before it**: the old
  guard could not detect a POS module, because it forbade all runtime and therefore never needed to.
- Historical claims stay verifiable against the exact tags they were made at.
- The `classify` check reports a specific, truthful classification instead of a generic success
  message, so a green check states *what* was classified rather than merely that something passed.

### Negative consequences / trade-offs

- **The repository's strongest and simplest invariant is gone.** "No runtime exists" is trivially
  verifiable by any reader; "only approved runtime exists" requires trusting a validator's rules.
  This is a genuine reduction in the legibility of the guarantee, accepted because Step 3 cannot
  proceed otherwise.
- **The Step 4+ token table is a maintained artefact and can drift.** A feature named in a way the
  table does not anticipate — particularly an Indonesian synonym not yet listed — would not be
  detected. The table is explicitly a floor, not a ceiling, and review remains load-bearing.
- **Structural detection can still be evaded deliberately** by an author who wants to evade it. The
  guard raises the cost of accidental leakage; it does not defeat an intentional bypass.
- Under single-maintainer governance (DEC-0017), a defect that both the maintainer and the validators
  miss is not caught. That residual risk is accepted, not eliminated.

## Verification

`scripts/validate-runtime-scope.py` was adversarially tested by
`scripts/test-step-03-validators.sh` against **31 deliberate violations** and **5 legitimate Step 3
cases**, with the working tree verified byte-identical before and after. Result: **36/36 expectations
met**.

Violations proven to turn the guard red: runtime manifests and source outside approved roots (5);
Step 4+ leakage via POS module, payment route, order migration filename, `orders` table, tracking
tokens, delivery module, reminder stages, WhatsApp, QRIS, Eloquent `Payment` model, and a Flutter POS
feature directory (11); deployment artifacts including production and staging compose files,
Terraform, Kubernetes, and a deploy action in a workflow (5); secrets and personal data including a
committed `.env`, a private key, a database dump, a GitHub token, and a realistic Indonesian phone
number (5); and governance-integrity violations including a removed DEC-0024, a Master Source
reverted to 1.3.0 while runtime exists, Step 4 claimed `IN PROGRESS`, Step 5 claimed `GO`, and a
symlink escaping the repository (5).

Legitimate cases proven to stay green: an authorised backend and Flutter foundation; tenancy tables
(`memberships`, `outlets`, `roles`); `orderBy()` not tripping the `order` token; prose in
documentation mentioning POS and payment as future scope; and a documented fictional phone number.

**Recorded honestly:** an earlier revision of the harness reported 31/31 caught. That result was
**false**. The harness embedded literal secret fixtures, which tripped the guard inside every sandbox,
so mutations appeared caught when the mutation setup had in fact failed with a shell syntax error and
never ran. The harness now assembles fixtures at runtime and fails loudly on setup error. The
superseded 31/31 figure must not be cited.

All evidence is bound to the exact commit SHA at which it was captured (DEC-0013). Evidence produced
at one SHA does not carry to another.

## Requirement references

`SEC-` and `TEN-` classes generally; specifically the tenant-isolation and financial-integrity hard
gates recorded in the Master Source, which this decision leaves untouched and which the new guard
continues to protect by forbidding Step 4+ financial artifacts.

## Threat references

Addresses runtime-era threats introduced by this decision: out-of-step feature leakage, deployment
artifact introduction, secret commitment on a `PUBLIC` repository, personal-data commitment, and
false implementation claims in status documents. The Step 3 runtime threat-model delta records these
with full STRIDE attributes.

## Rule references

Rule 00 (canonical source, version and checksum discipline), Rule 01 (status vocabulary and
exact-SHA evidence), Rule 02 (tenant isolation), Rule 11 (required checks and repository settings),
Rule 12 (autonomous execution boundaries), Rule 23 (public repository safety), Rule 33 (adversarial
validator testing), and the Step 3 rules introduced alongside this decision.

## Supersession policy

This decision is superseded only by a later accepted decision record that explicitly names DEC-0024
and states what replaces it. Any future step that widens the approved runtime roots, adds a runtime
language, or authorises deployment requires its own decision record; it is not covered by this one.
Narrowing the guard needs no new decision — a guard may always be made stricter.

## Related Master Source sections

§1 canonical rules; §5 platforms; §6 architecture; §15 security; §24 roadmap and step locking;
§25 governance. Master Source version bumped from 1.3.0 to **1.4.0** as a materially additive change,
with the checksum regenerated from final content by repository tooling and never hand-edited.
