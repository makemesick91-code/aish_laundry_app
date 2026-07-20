# Step 3 Phase C — Build Evidence

**Source commit at build time:** `3c12d6376cf724c40ee59bce5936d2a56bae9a41`
**Captured:** 20 July 2026, Asia/Jakarta
**Environment:** Ubuntu 26.04 LTS, local development host

Per Rule 01 and DEC-0013, evidence is bound to the exact commit it was produced from and does not
carry to another SHA. Artefacts themselves are **not committed** — only these sanitised facts.

---

## 1. Toolchain

| Component | Version |
|---|---|
| Flutter | 3.44.6 (stable, revision `ee80f08bbf`) |
| Dart | 3.12.2 |
| Android SDK platform | 36 |
| Android build-tools | 36.0.0 |
| Android NDK | 28.2.13676358 (installed during first build) |
| CMake | 3.22.1 (installed during first build) |
| JDK | OpenJDK 21.0.10 |
| Gradle | 9.1.0 |
| Android Gradle Plugin | 9.0.1 |

## 2. Customer Android — `CUSTOMER ANDROID DEBUG BUILD VERIFIED`

```
command : flutter build apk --debug
exit    : 0
artefact: apps/customer_android/build/app/outputs/flutter-apk/app-debug.apk
size    : 157,557,270 bytes
sha256  : d372f5facef44edc380040838774844417bbb4dad168007a45e8fe7bb3842c68
```

| Field | Value |
|---|---|
| `applicationId` | `id.aishtech.laundry.customer` |
| `namespace` | `id.aishtech.laundry.customer` |
| compileSdk / targetSdk / minSdk | Flutter 3.44.6 defaults (`flutter.compileSdkVersion` etc.) |
| Gradle task | `assembleDebug`, 267.2 s |
| Tests | 20 passed |

## 3. Ops Android — `OPS ANDROID DEBUG BUILD VERIFIED`

```
command : flutter build apk --debug
exit    : 0
artefact: apps/ops_android/build/app/outputs/flutter-apk/app-debug.apk
size    : 157,562,194 bytes
sha256  : 5a5c7c333cdb9c5da732eec684a908d4d893f1a041768fe54f486e7ff7afe814
```

| Field | Value |
|---|---|
| `applicationId` | `id.aishtech.laundry.ops` |
| `namespace` | `id.aishtech.laundry.ops` |
| Gradle task | `assembleDebug`, 57.0 s |
| Tests | 28 passed |

## 4. Admin Web — `ADMIN WEB BUILD VERIFIED`

```
command : flutter build web --release
exit    : 0
output  : apps/admin_web/build/web
size    : 41 MB
```

| Field | Value |
|---|---|
| Compile | `lib/main.dart` for the Web, 38.3 s |
| Icon tree-shaking | MaterialIcons 1,645,184 → 10,256 bytes (99.4% reduction) |
| Tests | 20 passed |

### Web output security scan

| Check | Result |
|---|---|
| Files referencing `localStorage` | **0** |
| Files referencing `sessionStorage` | **0** |
| `CHANGEME_local_dev_only` | 0 hits |
| `aish_dev` | 0 hits |
| `secret_key` / `api_key` | 0 hits |
| Credential-shaped assignments (`password: "…"`) | **0** |

`main.dart.js` contains 15 occurrences of the string `password`. All were inspected: they are
Flutter's own text-input handling (`s.type="password"`, autofill attribute wiring) and the
`signIn(identifier:, password:)` parameter name. **None is a credential value.**

A build-time warning was emitted and is recorded rather than suppressed:
`Expected to find fonts for (MaterialIcons, packages/cupertino_icons/CupertinoIcons), but found
(MaterialIcons)`. It does not fail the build; the Cupertino icon font is unused by these shells.

## 5. Scaffold generation

Generated one at a time through the DEC-0026 owner-controlled gate, which authorised each invocation
and printed `AUTHORIZED: STEP_3_FLUTTER_PLATFORM_SCAFFOLDING`:

```
flutter create --platforms=android --org id.aishtech.laundry --project-name aish_customer_android apps/customer_android
flutter create --platforms=android --org id.aishtech.laundry --project-name aish_ops_android      apps/ops_android
flutter create --platforms=web     --org id.aishtech.laundry --project-name aish_admin_web        apps/admin_web
```

Verified after each invocation:

- **no tracked file was modified or deleted** — existing `lib/` and hand-written tests survived intact;
- only the approved platform directory appeared: `android/` for both Android apps, `web/` for Admin
  Web. No iOS, macOS, Windows, or Linux directory was generated;
- `.idea/`, `build/`, `.dart_tool/`, `*.apk`, and Gradle caches are git-ignored and **0 are
  committable**;
- no keystore, signing material, or production credential exists anywhere in the tree.

### Two corrections made after generation

1. **Stock template tests removed.** The generator wrote a counter-app `widget_test.dart` into each
   app, referencing a `MyApp` constructor none of these applications defines. It broke compilation
   for the whole test directory. The files were untracked generated boilerplate testing an
   application that does not exist, and were removed. The hand-written suites
   (`auth_flow_test.dart` and siblings) were untouched and pass.

2. **`applicationId` reconciled to the canonical value.** `flutter create` appends the project name to
   `--org`, producing `id.aishtech.laundry.aish_customer_android`. `docs/runtime/APPLICATION_IDENTIFIERS.md`
   is canonical and decides `id.aishtech.laundry.customer` / `.ops`, so `namespace` and `applicationId`
   were set to those values. `MainActivity.kt` was relocated to the matching package directory —
   without that, the manifest's relative `.MainActivity` reference would not have resolved against the
   new namespace and the build would have failed.

## 6. Regression at the same commit

| Suite | Result |
|---|---|
| `dart format --set-exit-if-changed .` | 103 files, 0 changed |
| `flutter analyze` | No issues found |
| Flutter test suites (10) | **0 failing suites** |
| Backend (`php artisan test`) | **202 passed, 1292 assertions** |
| Guard self-test | **171/171** |
| DEC-0026 adversarial suite | **38/38**, tree byte-identical |
| Step 3 runtime-scope harness | **36/36**, tree byte-identical |
| Governance | 7/7 gates |
| Status | 28/28 |
| Secrets / public-repository safety | 10/10 · 14/14 |
| Step 0 / 1 / 2 regressions | PASS · PASS · PASS |
| `classify` | `STEP_3_RUNTIME_FOUNDATION_WITHIN_SCOPE` |

## 7. What is still NOT claimed

- **No release build, no signed artefact.** Both Android builds are **debug**. No keystore exists.
- **No device or emulator run.** The APKs were compiled, not launched. Nothing is claimed about
  runtime behaviour on a physical device.
- **No deployment.** Deployment remains `ABSENT`; the Web output was not served anywhere.
- **No store publication**, and `aishtech.id` domain ownership is **unverified** — required before any
  publication, out of scope for Step 3.
- **No performance measurement** of any kind.
- **Application CI remains `NOT APPLICABLE`** — these builds ran locally. It becomes `ACTIVE` only
  when the three runtime workflows execute real tests in CI.
- All Step 4+ business features remain `NOT IMPLEMENTED`.
