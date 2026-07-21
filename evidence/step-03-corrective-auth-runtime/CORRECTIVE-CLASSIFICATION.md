# Step 3 Post-GO Corrective Remediation: Runtime Authentication Wiring

**Type:** Corrective classification and rationale (evidence artefact)
**Status:** RECORDED — decision record DEFERRED, see "Decision-record numbering" below
**Date:** 21 July 2026

> **This is not a decision record.** It carries the classification, rationale and
> verification for a corrective bug fix so that none of it is lost, and so the
> reasoning is reviewable at the SHA it was produced from. It introduces no
> product decision and changes no product decision.

## Decision-record numbering

A decision record for this remediation is **deferred deliberately**, not omitted.

`scripts/validate-decisions.py` requires a CONTIGUOUS range of decision IDs and
pins `LAST_DEC = 27` on `main`. DEC-0028 through DEC-0031 are accepted records
that exist only on `feature/step-04-laundry-master-data` and have not yet
merged. That leaves no ID this branch can use without one of three costs:

  * taking DEC-0028 here would collide when PR #18 lands, forcing four already
    accepted records to be renumbered and every citation in Rule 49, Rule 50,
    `STATUS.md` and `ROADMAP.md` to be rewritten — and a decision ID is meant to
    be permanent;
  * taking DEC-0032 here would leave IDs 28–31 absent, which the validator
    treats as a hard failure, so `main` would go red until PR #18 merged;
  * editing the validator to tolerate a gap would weaken a governance gate to
    unblock work, which is a breach in itself.

The owner elected to defer. Once PR #18 has landed its records, this content is
promoted to **DEC-0032** with no ID reused, no accepted record renumbered, and
no validator weakened.

---

## Context

Step 3 was built, verified, merged, and owner-conferred **`GO WITH ACCEPTED DEVIATION`**, and GO-tagged
`aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go`. That tag remains immutable and is not
moved, deleted, recreated, or retargeted by this record.

During Step 4 work it was found that **no application could authenticate against a real backend**.

`AuthService` had exactly one implementation anywhere in the tree — `FakeAuthService` in
`packages/testing` — and all three Flutter applications declared their production provider as:

```dart
final Provider<AuthService> authServiceProvider = Provider<AuthService>(
  (ref) => throw UnimplementedError('authServiceProvider must be overridden.'),
);
```

Nothing overrode it outside a test. `main.dart` overrode only `environmentProvider`. The first frame
that read `authServiceProvider` — the route guard, on every launch — threw `UnimplementedError`.

Two supporting gaps made a concrete implementation impossible to write without them:

1. `ApiClient` returned `Result<ApiSuccess>` and **discarded** the `ClientErrorConsequence` that
   `ApiErrorMapper` had just computed, so no caller could distinguish "session expired" from "device
   revoked" from "rate limited". `authStateFor` — the mapper from consequence to `AuthState` — had **no
   production caller at all**.
2. `ApiClient` had no way to send `X-Tenant-Id` or `X-Outlet-Id`. `ResolveTenantContext` reads those
   headers for token clients and the session for cookie clients, so a bearer-token surface could
   authenticate and then reach **no tenant-scoped endpoint whatsoever**.

### Why this was not caught

Every widget test in every application overrode `authServiceProvider` with `FakeAuthService`. That is
correct for exercising screens, and it is exactly why the defect survived: the suite was green, the
builds succeeded, the scaffold-authorization checks passed, and no test ever resolved the production
provider graph. **Green tests and a successful build were mistaken for a working authentication path.**

This is recorded plainly because the failure mode is general: a test double supplied through the same
provider the production code uses will hide a missing production binding indefinitely.

## Options considered

1. **Fold the fix into the Step 4 pull request (PR #18).** Rejected. It would attribute Step 3
   remediation to Step 4, blur the rollback boundary, and make the exact-SHA evidence for each concern
   inseparable.
2. **Move or recreate the Step 3 GO tag to include the fix.** Rejected outright. A released tag is
   immutable (Rule 11). The tag records what was true at closure, including that this defect was present
   and undetected. Rewriting it would erase the evidence that the gate missed something.
3. **Reopen Step 3 / revert its status to `IN PROGRESS`.** Rejected. Step 3's `GO` was conferred by the
   owner against the evidence that existed. Retroactively withdrawing it would misrepresent the
   historical record and `scripts/validate-status.py` fails closed on it.
4. **A separate post-GO corrective branch and pull request.** **Chosen.**

## Decision

The absence of a concrete runtime `AuthService` is classified as an **internal, pre-existing runtime
defect in the Step 3 authentication foundation**, at severity:

```
HIGH — REAL RUNTIME PATH UNAVAILABLE
```

It is **not** an external blocker, not cosmetic, not optional, and not a Step 4 product feature.

Remediation is carried on a separate branch, `fix/step-03-auth-runtime-wiring`, cut from canonical
`main`, and merged through its own pull request **before** Step 4 continues.

1. **The Step 3 GO tag is not touched.** It still peels to
   `0e2554338812b05eba8411afeb099212b05f9761`, asserted by `scripts/validate-status.py`.
2. **Step 3 remains `GO WITH ACCEPTED DEVIATION`.** This record does not upgrade or downgrade it.
3. `BackendAuthService` is added as the production implementation, composing the pieces that already
   existed rather than introducing a second authentication framework.
4. `AuthRuntime` is the single composition root for all three surfaces.
5. `ApiErrorMapper.consequenceOf` and `RequestContext` close the two supporting gaps.
6. **Step 4 closure depends on this correction being merged first.**

### What this record does NOT claim

It does not claim Step 3 was wrongly given `GO`, that the tag covers this fix, that authentication was
ever previously working at runtime, or that keystore behaviour on a physical device has been verified.

## Consequences

The original defect and its correction both remain visible in history. Anyone reading the Step 3 tag
sees the state at closure; anyone reading `main` sees the corrected state and this record explaining the
gap between them.

### Positive consequences

- All three applications can authenticate against a real backend, proven end to end.
- Scope attribution stays clean: Step 3 remediation is in a Step 3 branch, with its own SHA and its own
  CI run.
- The rollback boundary is a single revertable merge.
- A regression guard now exists that fails if the production wiring is removed again.
- Two further defects found while writing tests were fixed here rather than shipped (see Verification).
- The tenant boundary is now exercised end to end against a real second tenant, not only asserted.

### Negative consequences / trade-offs

- Step 4 is delayed by the length of this corrective cycle. Accepted: merging Step 4 on top of a
  foundation whose authentication path cannot run would build more on the same defect.
- `packages/auth` gains a dependency on `aish_local_storage`. Accepted: the alternative was a parallel
  credential-storage abstraction, which Rule 06 and Rule 17 both forbid.
- One pre-existing `ApiClient` test became vacuous under the per-request header change and was
  replaced. A test that can no longer fail is worse than no test, so this is disclosed rather than left.
- **Keystore behaviour on a physical device remains unverified.** `flutter_secure_storage` has no
  platform channel under `flutter test`, so the end-to-end suite substitutes
  `InMemoryCredentialStore`. Everything else in that suite is real. This is a genuine residual gap and
  is not presented as covered.
- The end-to-end suite requires a running backend and is skipped by default. A skip is not a pass, and
  the evidence pack records the exact invocation under which it actually ran.

## Verification

All results below are bound to the exact commit SHAs recorded in
[`../../evidence/step-03-corrective-auth-runtime/`](../../evidence/step-03-corrective-auth-runtime/).

1. **The defect is reproducible and the guard discriminates.** Reverting `ops_android` to the throwing
   stub makes the new composition tests fail with the original `UnimplementedError` while all 28
   pre-existing screen tests still pass — reproducing the exact false-confidence condition.
2. **End-to-end against a running backend.** Eight paths pass against real Laravel and real PostgreSQL:
   sign-in; wrong password; unknown account refused indistinguishably from a wrong password;
   server-verified restoration; selecting the caller's own tenant; **selecting a different real tenant
   and being refused**; outlets refused before a tenant is chosen; and sign-out proven to revoke the
   token server-side by replaying it from a fresh runtime.
3. **Two defects found by writing the tests, fixed here.**
   - Credential clearing keyed on the resulting `AuthState`. During restoration the transient fallback
     is `unauthenticated`, so a device with no signal at launch was indistinguishable from a dead
     session and the stored token was **deleted**. Now keyed on the consequence.
   - Startup could hang indefinitely on "Memeriksa sesi Anda…" if secure storage never answered,
     contrary to Rule 29 hard rule 13. Storage calls are now bounded and fail closed.
4. **Adversarial validator testing.** The new concurrency test was checked against the previous
   implementation, **passed**, and was therefore re-labelled as a forward-looking property guard rather
   than reported as a regression guard it is not (Rule 47, Rule 01).
5. **Governance validators pass**, including `validate-status.py` asserting the Step 3 tag object and
   peeled SHA are unchanged.
6. **Secret and PII scan** over the full diff: clean. No credential is committed; the development
   seeder generates a distinct random password per run, read from the environment at run time.

## Requirement references

Supports the Step 3 authentication, tenancy and RBAC foundation that FR-001 … FR-020 depend on. It
introduces **no new product requirement** and no new product decision.

## Threat references

- **T-Auth-Enumeration** — unknown account and wrong password produce the same client state; asserted
  end to end.
- **T-Cross-Tenant-Access** — selecting a foreign tenant is refused by the server; asserted end to end
  against a real second tenant.
- **T-Credential-Disclosure** — no token, password or OTP reaches a log, a `toString()`, a failure
  message, or a committed file; asserted by test.
- **T-Session-Persistence** — sign-out revokes server-side rather than forgetting locally; asserted by
  replaying the revoked token.

## Rule references

- Rule 01 — exact-SHA evidence; a vacuous test disclosed rather than counted; a passing mutation test
  reported as such.
- Rule 02, Rule 39, Rule 48 — client-supplied tenant identifiers are untrusted hints; denial is
  indistinguishable from absence.
- Rule 03, Rule 38 — no plaintext credential; no bearer token in browser-readable storage; no user
  enumeration.
- Rule 11 — the released Step 3 tag is immutable and was not moved.
- Rule 29 — a surface must resolve rather than spin indefinitely.
- Rule 46 — no credential in any log or telemetry path.
- Rule 47 — validators tested adversarially before being relied upon as gates.
- Rule 49 — Step 3 status snapshot; this record adds the post-GO corrective state.

## Promotion and supersession policy

This artefact is promoted to DEC-0032 once decision IDs 28–31 have merged to `main`. Until then it
carries no decision-record authority and must not be cited as one. It does **not** supersede DEC-0024,
DEC-0025, or DEC-0026, and it does not alter the Step 3 `GO` determination or its tag.

Widening what the authentication runtime may do — a new credential transport, a new token storage
mechanism, a second authentication framework, or any relaxation of server-side verification — requires
its own decision record and may not be done by editing this one.

## Related Master Source sections

- §6 — backend and API foundation (`/api/v1`, modular monolith)
- §7 — multi-tenancy and the tenant hierarchy
- §15.8 — public repository visibility and authoring constraints
- §24 — the locked roadmap and the Step 3 / Step 4 boundary
