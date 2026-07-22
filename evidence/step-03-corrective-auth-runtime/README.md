# Step 3 Corrective — Runtime Authentication Wiring: Evidence

**Bound to code commit:** `a7350ba97feb59249801772b05271a6e74da0b86`
**Branch:** `fix/step-03-auth-runtime-wiring` (cut from `origin/main` at `1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`)
**Timezone:** Asia/Jakarta

Evidence produced at one SHA does not carry to another (Rule 01, DEC-0013).
Everything below was re-run from scratch at the SHA above, with a clean tree.

**On the relationship between this SHA and the PR head.** A file cannot contain the
hash of the commit that introduces it, so this document is bound to the final CODE
commit and is added by the commit that follows it. That successor commit changes
**documentation only** — verifiable with `git diff --stat a7350ba <pr-head>`,
which lists nothing under `apps/`, `packages/`, `backend/` or `scripts/`. The
pull-request head is separately verified in its own right by authoritative CI,
whose run identifiers are recorded on the pull request.

Companion documents:
[`DEC-0032`](../../docs/decisions/DEC-0032-step-03-post-go-corrective-auth-runtime-wiring.md)
(the defect classification and rationale — carried here as an unnumbered evidence
artefact while decision IDs 28–31 were still unmerged, and formalised once they
landed), [`POST-MERGE-CLOSURE.md`](POST-MERGE-CLOSURE.md) (merge SHA and
fresh-clone verification), and [`GOVERNANCE-FOLLOW-UP.md`](GOVERNANCE-FOLLOW-UP.md)
(an unrelated guard-scope issue found while doing this work).

---

## What was broken

`AuthService` had one implementation in the tree — `FakeAuthService` in
`packages/testing` — and all three applications declared their production provider
as a throwing stub with no production override anywhere. The first frame that read
it threw `UnimplementedError`. **No build of any surface could authenticate.**

## The proof that the guard discriminates

Reverting `apps/ops_android/lib/src/app.dart` to the throwing stub makes the new
composition tests fail with the original `UnimplementedError`, while **all 28
pre-existing screen tests still pass** — because each supplies `FakeAuthService`
through the same provider. That is exactly the false-confidence condition that let
this reach a GO-tagged foundation, reproduced on demand.

## Runtime verification on real platforms

The host suite proves the SERVICE. It cannot prove the PLATFORM:
`flutter_secure_storage` has no channel under `flutter test`, so every call there
fails closed. The runs below close that gap.

### Android — real emulator, real Keystore, real backend

Sections D and E of the capture: **14/14 for `ops_android`, 14/14 for
`customer_android`**. Together they cover unauthenticated startup, sign-in, invalid
credentials, the token genuinely landing in the Android Keystore, authenticated
startup restoring from it, tenant context reaching a real tenant-scoped endpoint, a
foreign tenant refused by the real server, logout clearing the platform, an
unauthorized session reported and cleared, a transient network failure NOT deleting
a good credential, and bounded startup when the keystore never answers.

The backend stayed **loopback-bound** throughout: the emulator reached it through
`adb reverse tcp:8000 tcp:8000`, not by binding the dev backend to `0.0.0.0`
(Rule 45).

### Android — token persistence across a genuine application restart

`flutter test` uninstalls the application between invocations, which wipes
`EncryptedSharedPreferences`. That was **diagnosed rather than assumed**: a bare
keystore marker carrying no session failed the same way, which identified it as a
harness artefact and not a product defect. The proof therefore runs the same
INSTALLED package twice through instrumentation, with no reinstall between runs:

```
$ adb shell am instrument -w id.aishtech.laundry.ops.test/androidx.test.runner.AndroidJUnitRunner
OK (1 test)
RESTART-PROOF: run=1 prior=absent action=sign-in
RESTART-PROOF: run=1 credential-left-in-keystore=yes

$ adb shell am instrument -w id.aishtech.laundry.ops.test/androidx.test.runner.AndroidJUnitRunner
OK (1 test)
RESTART-PROOF: run=2 prior=present action=restore-only
RESTART-PROOF: run=2 restored-without-sign-in=yes
RESTART-PROOF: run=2 logout-cleared-keystore=yes
```

Run 2 is a new application process that restored a working session **without
signing in**, then proved logout removes it for good.

### Android — release build exercised, not merely built

`flutter build apk --release` produced both surfaces (51.3 MB each, exit 0). The
`ops_android` release APK was then installed and launched:

```
process ALIVE
ResumedActivity = id.aishtech.laundry.ops/.MainActivity
FATAL EXCEPTION / UnimplementedError in logcat: none
```

This is the launch that threw `UnimplementedError` before the fix.

### Console Web — real Chrome, real cookie session

Google Chrome 149.0.7827.114, headless, driven over the DevTools Protocol against
the release web bundle served on `http://localhost:3000`, with the API proxied on
the same origin:

```
CONSOLE-WEB: booted-in-chrome=yes
CONSOLE-WEB: storage-after-boot={"local":[],"session":[]}
CONSOLE-WEB: login-status=200
CONSOLE-WEB: authenticated-me=200|no-token-in-body
CONSOLE-WEB: storage-after-auth={"local":[],"session":[]}
CONSOLE-WEB: script-readable-cookies=XSRF-TOKEN=<redacted>
CONSOLE-WEB: api-calls=["401 /api/v1/auth/me","200 /api/v1/auth/login","200 /api/v1/auth/me"]
CONSOLE-WEB: unimplemented-provider-errors=0
```

Four things are established there that a build scan cannot establish:

1. the corrective composition **boots in a browser**, with zero
   `UnimplementedError`;
2. **nothing is written to `localStorage` or `sessionStorage`** — not at boot, and
   not after authenticating. Rule 38 hard rule 2 verified by behaviour rather than
   by a string search of the bundle;
3. the session cookie is **not readable by page script**; only Laravel's
   `XSRF-TOKEN` is, which is its intended double-submit CSRF mechanism;
4. the application's own startup probe returns `401 /auth/me`, which is the correct,
   honest unauthenticated startup.

**What this deliberately does NOT establish:** the SPA and the API were served from
one origin, so cross-origin CORS preflight was not exercised. That is a realistic
deployment shape, but it is not the same as verifying the CORS allowlist, and it is
not claimed to be.

## Adversarial validator testing (Rule 47)

| Guard | Mutation applied | Result |
|---|---|---|
| `runtime_composition_test.dart` | `authServiceProvider` reverted to throwing stub | **FAILED as intended** |
| `api_client_test.dart` — "never on shared options" | credential written back to `Dio.options.headers` | **FAILED as intended** |
| `api_client_test.dart` — "concurrent requests" | same mutation | **PASSED — does not discriminate** |
| `backend_auth_service_test.dart` — "network failure does NOT delete a good credential" | clearing keyed on state instead of consequence | **FAILED as intended** |

The third row is a negative result and is reported as one. That test was written
believing it guarded a race in the previous implementation; the mutation showed it
does not discriminate, so it was **re-labelled** as a forward-looking property guard
rather than presented as a regression guard it is not.

## Defects found while doing this work

1. **A network blip at launch deleted a valid credential.** Clearing keyed on the
   resulting `AuthState`; during restoration the transient fallback is
   `unauthenticated`, so a device with no signal was indistinguishable from a dead
   session. Now keyed on the consequence.
2. **Startup could hang forever** on "Memeriksa sesi Anda…" if secure storage never
   answered, contrary to Rule 29 hard rule 13. Storage calls are now bounded and
   fail closed.
3. **A credential-storage regression introduced by this branch.** Adding
   `aish_local_storage` to `aish_auth` pulled `flutter_secure_storage` into Console
   Web, whose web implementation IS `localStorage`. Caught by
   `scripts/scan-web-build.py`, not by the author. A plugin in a build dependency
   graph is registered into that build, so it could not be fixed by not calling it;
   the abstraction moved to pure-Dart `aish_core` and only the platform-backed
   implementation stayed in `aish_local_storage`.
4. **A test bug in this verification work.** The transient-failure test pointed at a
   TEST-NET-1 address, and `Environment.validate` correctly refuses plaintext HTTP to
   any non-loopback host. Corrected to an unused loopback port.

## Residual risk — stated, not hidden

- **No physical device was used.** Verification ran on an x86_64 Android 34 emulator
  with KVM. Keystore-backed storage behaved correctly there; hardware-backed key
  attestation on a real handset is NOT covered.
- **Cross-origin CORS preflight for Console Web is unverified** (see above).
- **No Console Web UI was driven through its widgets.** The browser evidence
  exercises boot, storage behaviour and the authenticated HTTP round-trip; it does
  not click through the sign-in form.
- **`customer_android` has no `am instrument` scaffolding.** Its 14/14 on-device run
  is real, but the cross-restart proof was performed on `ops_android` only.
- **No deployment exists.** Nothing here authorises one.
- Governance remains single-maintainer; independent human approval is `ABSENT`
  (DEC-0017). Nothing above is independent peer review.

---

## Captured output

```text
CODE_SHA:  a7350ba97feb59249801772b05271a6e74da0b86
CAPTURED:  2026-07-21 22:11:11 WIB
HOST:      Linux 7.0.0-27-generic
TOOLCHAIN: Flutter 3.44.6 • channel stable • https://github.com/flutter/flutter.git | PHP 8.5.4 (cli) (built: May 25 2026 12:19:37) (NTS)
DEVICE:    Android  API  x86_64 emulator (KVM)
BACKEND:   Laravel via artisan serve on 127.0.0.1:8000, PostgreSQL 18.4 + Redis 8.2 (pinned dev images)
TREE:      0 modified files (0 = clean)

=== A. HOST GATES ===
dart format                    Formatted 116 files (0 changed) in 0.68 seconds.
dart analyze                   No issues found!
packages/core                  00:00 +18: All tests passed!
packages/domain                00:00 +12: All tests passed!
packages/networking            00:00 +34: All tests passed!
packages/auth                  00:00 +34 ~8: All tests passed!
packages/local_storage         00:00 +11: All tests passed!
packages/design_system         00:01 +31: All tests passed!
packages/observability         00:00 +13: All tests passed!
apps/ops_android               00:03 +34: All tests passed!
apps/customer_android          00:03 +26: All tests passed!
apps/admin_web                 00:03 +26: All tests passed!

=== B. GOVERNANCE VALIDATORS ===
validate-runtime-scope         RESULT: PASS (runtime-scope)
validate-status                RESULT: PASS (status)
validate-decisions             RESULT: PASS (decisions)
validate-secrets               RESULT: PASS (secrets)
scan-web-build                   no browser token storage, credential assignment, or dev value found

=== C. HOST E2E vs RUNNING BACKEND (dart) ===
00:00 +0: loading /home/fikri/Projects/aish_laundry/packages/auth/test/backend_integration_test.dart
00:00 +0: against a running backend a real sign-in produces a real session
00:00 +1: against a running backend a wrong password is refused and stores nothing
00:00 +2: against a running backend an unknown account is refused indistinguishably from a wrong password
00:01 +3: against a running backend a stored token restores through auth/me
00:01 +4: against a running backend selecting the caller's own tenant succeeds
00:01 +5: against a running backend selecting ANOTHER tenant is refused by the real server
00:01 +6: against a running backend outlets are refused before a tenant is chosen
00:02 +7: against a running backend sign-out revokes the token server-side, not just locally
00:02 +8: All tests passed!

=== D. ON-DEVICE ops_android (real composition, real keystore, real backend) ===
00:00 +0: loading /home/fikri/Projects/aish_laundry/apps/ops_android/integration_test/auth_runtime_test.dart
00:00 +0: platform keystore — the thing host tests cannot reach writes and reads back through the platform channel
00:00 +1: platform keystore — the thing host tests cannot reach a value survives a NEW store instance
00:00 +2: platform keystore — the thing host tests cannot reach clearOnLogout removes it from the platform
00:00 +3: authentication against the real backend unauthenticated startup resolves, and asks nothing
00:00 +4: authentication against the real backend a real sign-in produces a real session
00:01 +5: authentication against the real backend invalid credentials are refused and store nothing
00:01 +6: authentication against the real backend the token really lands in the Android Keystore
00:02 +7: authentication against the real backend authenticated startup restores from the keystore
00:02 +8: authentication against the real backend tenant context reaches a real tenant-scoped endpoint
00:03 +9: authentication against the real backend a foreign tenant is refused by the real server
00:03 +10: authentication against the real backend logout deletes the credential from the platform
00:03 +11: authentication against the real backend an unauthorized session is reported, and cleared
00:04 +12: authentication against the real backend a transient network failure does NOT delete a good credential
00:04 +13: authentication against the real backend startup is bounded when secure storage never answers
00:19 +14: (tearDownAll)
00:19 +14: All tests passed!

=== E. ON-DEVICE customer_android ===
00:00 +0: loading /home/fikri/Projects/aish_laundry/apps/customer_android/integration_test/auth_runtime_test.dart
00:00 +0: platform keystore — the thing host tests cannot reach writes and reads back through the platform channel
00:00 +1: platform keystore — the thing host tests cannot reach a value survives a NEW store instance
00:01 +2: platform keystore — the thing host tests cannot reach clearOnLogout removes it from the platform
00:01 +3: authentication against the real backend unauthenticated startup resolves, and asks nothing
00:01 +4: authentication against the real backend a real sign-in produces a real session
00:01 +5: authentication against the real backend invalid credentials are refused and store nothing
00:01 +6: authentication against the real backend the token really lands in the Android Keystore
00:02 +7: authentication against the real backend authenticated startup restores from the keystore
00:02 +8: authentication against the real backend tenant context reaches a real tenant-scoped endpoint
00:02 +9: authentication against the real backend a foreign tenant is refused by the real server
00:03 +10: authentication against the real backend logout deletes the credential from the platform
00:03 +11: authentication against the real backend an unauthorized session is reported, and cleared
00:04 +12: authentication against the real backend a transient network failure does NOT delete a good credential
00:04 +13: authentication against the real backend startup is bounded when secure storage never answers
00:19 +14: (tearDownAll)
00:19 +14: All tests passed!

=== F. STEP 3 GO TAG INTEGRITY ===
tag object : 8b37230ed8df8da343a1546fd949d8a41329fbdf
peels to   : 0e2554338812b05eba8411afeb099212b05f9761
```

## Step 3 GO tag — NOT moved

`aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` still resolves to
tag object `8b37230ed8df8da343a1546fd949d8a41329fbdf`, peeling to
`0e2554338812b05eba8411afeb099212b05f9761` — exactly as Rule 49 records. It was not
moved, deleted, recreated, or retargeted, and it does not cover this correction.
`scripts/validate-status.py` asserts both values.

## What this evidence does NOT claim

Step 3 remains `GO WITH ACCEPTED DEVIATION`; this changes neither its status nor its
tag. Step 4 remains `IN PROGRESS`. Deployment remains `ABSENT`. UAT remains
`NOT STARTED`. A green CI run classifies scope and executes tests; it is not a
product capability claim (Rule 36 hard rule 6).
