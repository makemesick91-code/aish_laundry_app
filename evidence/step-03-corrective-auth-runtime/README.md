# Step 3 Corrective — Runtime Authentication Wiring: Evidence

**Bound to commit:** `838ee7b8f00bf2e7be3bb93fc9ed6bfafff14d8c`
**Branch:** `fix/step-03-auth-runtime-wiring` (cut from `origin/main` at `1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`)
**Timezone:** Asia/Jakarta

Evidence produced at one SHA does not carry to another (Rule 01, DEC-0013). An
capture has been invalidated and re-run twice, and both reasons are recorded
rather than quietly replaced:

  * `95469ba2dcefbfa137cca65c32b94e1cd695100c` — `scripts/validate-secrets.sh`
    failed in CI on three inline `password: '...'` literals in the new tests.
    The values were fictional, but the shape is exactly what the scanner exists
    to catch, so the tests were changed rather than the scanner.
  * `0af9a743f55ac9e46576472e1d3c605cbf03fdb4` — `dart format
    --set-exit-if-changed` failed in CI on seven files. The local gate list had
    omitted the format check; it is included below from here.

Everything below was re-run from scratch at the SHA above. The capture is
verbatim; the working tree was clean when it ran. The only
redaction is the development password, replaced with `<redacted>` — it is
generated fresh per seeder run and is never committed (Rule 23, Rule 45).

The classification and rationale for this remediation are in
[`CORRECTIVE-CLASSIFICATION.md`](CORRECTIVE-CLASSIFICATION.md).

---

## What was broken

`AuthService` had one implementation in the tree — `FakeAuthService` in
`packages/testing` — and all three applications declared their production
provider as a throwing stub with no production override anywhere. The first
frame that read it threw `UnimplementedError`. **No build of any surface could
authenticate.**

## The proof that matters

Reverting `apps/ops_android/lib/src/app.dart` to the throwing stub and re-running:

```
$ flutter test apps/ops_android/test/runtime_composition_test.dart
00:00 +0 -1: production composition resolves a CONCRETE AuthService without throwing [E]
  UnimplementedError: authServiceProvider must be overridden.
00:00 +0 -2: production composition does not resolve a test double in production [E]
  UnimplementedError: authServiceProvider must be overridden.

$ flutter test apps/ops_android/test/tenant_outlet_selection_test.dart
00:03 +28: All tests passed!
```

The new composition tests fail with the original error. **All 28 pre-existing
screen tests still pass**, because each supplies `FakeAuthService` through the
same provider — reproducing exactly the false-confidence condition that let this
reach a GO-tagged foundation.

## Adversarial validator testing (Rule 47)

| Guard | Mutation applied | Result |
|---|---|---|
| `runtime_composition_test.dart` | `authServiceProvider` reverted to throwing stub | **FAILED as intended** |
| `api_client_test.dart` — "never on shared options" | credential written back to `Dio.options.headers` | **FAILED as intended** |
| `api_client_test.dart` — "concurrent requests" | same mutation | **PASSED — does not discriminate** |
| `backend_auth_service_test.dart` — "network failure does NOT delete a good credential" | clearing keyed on state instead of consequence | **FAILED as intended** |

The third row is reported because it is a negative result. That test was written
believing it guarded a race in the previous implementation; the mutation showed
it does not discriminate, so it was **re-labelled** as a forward-looking property
guard rather than presented as a regression guard it is not. The
discriminating test for that property is the row above it.

## Defects found while writing the tests, fixed here

1. **A network blip at launch deleted a valid credential.** Credential clearing
   keyed on the resulting `AuthState`; during restoration the transient fallback
   is `unauthenticated`, so a device with no signal was indistinguishable from a
   dead session. Now keyed on the consequence.
2. **Startup could hang forever.** If secure storage never answered, the app sat
   on "Memeriksa sesi Anda…" with a spinner and no way forward — contrary to
   Rule 29 hard rule 13. Storage calls are now bounded and fail closed.

## Residual risk — stated, not hidden

- **Keystore behaviour on a physical device is UNVERIFIED.** `flutter_secure_storage`
  has no platform channel under `flutter test`, so the end-to-end suite substitutes
  `InMemoryCredentialStore`. The service, HTTP client, wire format, server and
  database in that suite are all real; only persistence is substituted.
- **No Android or web release build was produced or exercised here.**
- **Console Web cookie transport was not exercised against a browser.** Its
  transport wiring is asserted by composition test, not by a browser session.
- Governance remains single-maintainer; independent human approval is `ABSENT`
  (DEC-0017). Nothing below is independent peer review.

---

## Captured output

```text
COMMIT_SHA: 838ee7b8f00bf2e7be3bb93fc9ed6bfafff14d8c
CAPTURED:   2026-07-21 20:07:43 WIB
ENV:        Linux 7.0.0-27-generic | Flutter 3.44.6 • channel stable • https://github.com/flutter/flutter.git | PHP 8.5.4 (cli) (built: May 25 2026 12:19:37) (NTS)
TREE:       0 modified files (0 = clean)

### dart format --output=none --set-exit-if-changed .
Formatted 112 files (0 changed) in 0.47 seconds.
### dart analyze apps packages
Analyzing apps, packages...
No issues found!

### flutter test (hermetic)
packages/core              00:00 +18: All tests passed!
packages/domain            00:00 +12: All tests passed!
packages/networking        00:00 +34: All tests passed!
packages/auth              00:00 +34 ~8: All tests passed!
packages/local_storage     00:00 +11: All tests passed!
packages/design_system     00:01 +31: All tests passed!
packages/observability     00:00 +13: All tests passed!
apps/ops_android           00:03 +34: All tests passed!
apps/customer_android      00:03 +26: All tests passed!
apps/admin_web             00:02 +26: All tests passed!

### validators
validate-runtime-scope     RESULT: PASS (runtime-scope)
validate-status            RESULT: PASS (status)
validate-decisions         RESULT: PASS (decisions)
validate-secrets           RESULT: PASS (secrets)

### end-to-end vs running backend (Laravel + PostgreSQL 18.4 + Redis 8.2)
AISH_E2E_BASE_URL=http://127.0.0.1:8000/api/v1 AISH_E2E_IDENTIFIER=owner.kenanga@contoh.invalid \
AISH_E2E_PASSWORD=<redacted> AISH_E2E_TENANT_ID=<kenanga> AISH_E2E_FOREIGN_TENANT_ID=<melati> \
flutter test packages/auth/test/backend_integration_test.dart --tags e2e
  00:00 +0: loading /home/fikri/Projects/aish_laundry/packages/auth/test/backend_integration_test.dart
  00:00 +0: against a running backend a real sign-in produces a real session
  00:00 +1: against a running backend a wrong password is refused and stores nothing
  00:00 +2: against a running backend an unknown account is refused indistinguishably from a wrong password
  00:00 +3: against a running backend a stored token restores through auth/me
  00:01 +4: against a running backend selecting the caller's own tenant succeeds
  00:01 +5: against a running backend selecting ANOTHER tenant is refused by the real server
  00:01 +6: against a running backend outlets are refused before a tenant is chosen
  00:02 +7: against a running backend sign-out revokes the token server-side, not just locally
  00:02 +8: All tests passed!

### Step 3 GO tag integrity
tag object : 8b37230ed8df8da343a1546fd949d8a41329fbdf
peels to   : 0e2554338812b05eba8411afeb099212b05f9761
```

## Step 3 GO tag — NOT moved

The immutable tag `aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go`
still resolves to tag object `8b37230ed8df8da343a1546fd949d8a41329fbdf`, peeling
to `0e2554338812b05eba8411afeb099212b05f9761` — exactly as Rule 49 records. It was
not moved, deleted, recreated, or retargeted, and it does not cover this
correction. `scripts/validate-status.py` asserts both values.

## What this evidence does NOT claim

Step 3 remains `GO WITH ACCEPTED DEVIATION`; this changes neither its status nor
its tag. Step 4 remains `IN PROGRESS`. No deployment exists. No UAT has occurred.
A green CI run classifies scope and executes tests; it is not a product
capability claim (Rule 36 hard rule 6).
