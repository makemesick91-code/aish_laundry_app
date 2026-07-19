# Compatibility Matrix — Step 3 Runtime Foundation

**Status:** `IN PROGRESS` — Step 3, Phase A
**Companion to:** [`TOOLCHAIN.md`](./TOOLCHAIN.md), [`TOOLCHAIN_SOURCES.md`](./TOOLCHAIN_SOURCES.md)

A pinned version list is not a compatibility guarantee. This file records which pairings are
**required to hold**, which are **asserted by a declared constraint**, and which are **verified by an
executed command**. The distinction matters: a declared constraint is a vendor's promise; an executed
verification is evidence.

---

## 1. Verification levels

| Level | Meaning |
|---|---|
| `DECLARED` | The vendor's own manifest declares the constraint. Not independently executed. |
| `EXECUTED` | A command was run at an exact commit SHA and its output captured in the evidence pack. |
| `NOT VERIFIED` | The pairing is assumed but has not been exercised. Stated openly, never rounded up. |

Rule 01 governs this table. A `DECLARED` row must never be reported as though it were `EXECUTED`.

---

## 2. Backend pairings

| A | B | Constraint | Level |
|---|---|---|---|
| Laravel 13.20.0 | PHP 8.5.4 | `laravel/framework` declares `php: ^8.3`; 8.5.4 satisfies it | `DECLARED` |
| Laravel 13.20.0 | Composer 2.10.1 | Composer 2.x required | `DECLARED` |
| Laravel Sanctum 4.3.2 | Laravel 13.20.0 | Sanctum 4.x targets the current Laravel major | `DECLARED` |
| Laravel 13.20.0 | PostgreSQL 18.4 | Laravel's `pgsql` driver; requires `pdo_pgsql` | `NOT VERIFIED` |
| Laravel 13.20.0 | Redis 8.2 | Laravel Redis via `phpredis` or `predis` | `NOT VERIFIED` |
| PHP 8.5.4 | `pdo_pgsql`, `redis` extensions | required for the above | `NOT VERIFIED` |

Rows marked `NOT VERIFIED` are **Phase B gates**. They move to `EXECUTED` only when the backend
actually connects, migrates, and passes integration tests against containerised PostgreSQL 18.4 and
Redis 8.2 — not before, and the status is not advanced to make this table read cleanly.

## 3. Client pairings

| A | B | Constraint | Level |
|---|---|---|---|
| Flutter 3.44.6 | Dart 3.12.2 | Dart SDK is bundled with the Flutter release | `EXECUTED` — `flutter --version` reports Dart 3.12.2 |
| Flutter 3.44.6 | Ubuntu 26.04 LTS host | SDK runs and self-reports | `EXECUTED` — `flutter doctor` section `[✓]` |
| Flutter 3.44.6 | Chrome 149.0.7827.114 | Web target device available | `EXECUTED` — `flutter doctor` section `[✓]` |
| Flutter 3.44.6 | **Android SDK 36** | **BLOCKED** — host has Android SDK **35.0.0**; Flutter 3.44.6 requires **36** plus BuildTools 28.0.3 | `EXECUTED` — `flutter doctor` reports `[!]` |
| Flutter 3.44.6 | JDK 21.0.10 | Android Gradle toolchain requires a supported JDK | `NOT VERIFIED` |
| Flutter 3.44.6 | Flutter Web (`admin_web`) | Web is a first-class stable target in 3.44.x | `NOT VERIFIED` |
| Dart 3.12.2 | Dart workspaces (monorepo) | Dart workspaces require Dart ≥ 3.6 | `DECLARED` |

**Open Phase C gate — Android SDK 36.** `flutter doctor` reports, verbatim:

```
✗ Flutter requires Android SDK 36 and the Android BuildTools 28.0.3
```

The host Android SDK is at `/home/fikri/Android/Sdk` with all licenses accepted, but platform 36 is
not installed. Android builds for `customer_android` and `ops_android` **cannot succeed until this is
resolved** by installing the platform component via `sdkmanager`. This is an additive SDK component
install, not a destructive change to the host toolchain. Until it is done and an Android build is
captured at an exact SHA, **no Android build result may be claimed**.

Remaining `NOT VERIFIED` rows are **Phase C gates**, cleared by `flutter analyze`, `flutter test`,
and an actual Android and Web build — captured at an exact SHA.

## 4. Cross-cutting

| A | B | Constraint | Level |
|---|---|---|---|
| Docker 29.5.3 | Compose v5.1.4 | Compose plugin v5 requires a current Engine | `EXECUTED` (both reported their own versions on the host) |
| PostgreSQL 18.4 container | psql 18.4 host client | matching major avoids client/server protocol surprises | `EXECUTED` (client version only) |
| CI runner (`ubuntu-latest`) | every pin above | CI must reproduce the pinned set | `NOT VERIFIED` |

The CI row is a **Phase D gate**. Until a runtime CI workflow actually installs the pinned toolchain
and runs green at an exact SHA, application CI remains `NOT APPLICABLE` in
[`docs/STATUS.md`](../STATUS.md) and no application pipeline is claimed.

---

## 5. Known constraints and deliberate exclusions

1. **Linux development only.** The pinned set is verified on Linux. macOS and Windows development are
   `NOT VERIFIED` and are not claimed to work.
2. **No iOS target.** Customer and Ops are Android-first. Adding an iOS target requires a decision
   record, an expanded toolchain, and a macOS host — none of which exists.
3. **PostgreSQL is authoritative.** SQLite may not stand in for PostgreSQL in any integration or
   tenant-isolation test. A green test suite run on SQLite is not evidence of tenant isolation.
4. **Node and Python are tooling only.** Neither is an application runtime; the runtime-scope
   validator continues to reject application source in those languages outside permitted tooling
   directories.
5. **No production or staging environment is contacted** by anything in this matrix.

---

## 6. Drift enforcement

[`scripts/validate-toolchain-locks.py`](../../scripts/validate-toolchain-locks.py) asserts that the
version strings in this file, `TOOLCHAIN.md`, `infrastructure/docker-compose.dev.yml`, the CI
workflows, and the generated runtime manifests **all agree**. Divergence between any two surfaces is
a validator failure.

---

## 7. Honest status

At the time of writing, the client-side and database pairings in this matrix are **declared, not
executed**. Nothing here should be read as a statement that the stack has been proven to work
end-to-end. Step 3 advances these rows to `EXECUTED` phase by phase, on captured evidence at exact
commit SHAs, and this file is updated as reality changes — never ahead of it.
