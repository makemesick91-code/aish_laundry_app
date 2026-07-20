# Toolchain — Step 3 Runtime Foundation

**Status:** `IN PROGRESS` — Step 3, Phase A
**Master Source:** v1.3.0 (Step 3 targets v1.4.0; not yet bumped)
**Scope:** the pinned toolchain that the Step 3 runtime foundation is built and verified against.

This document records **what is pinned**, **why**, and **how it was verified**. It is a derived
enforcement artefact under [`docs/MASTER_SOURCE.md`](../MASTER_SOURCE.md) and never overrides it.

---

## 1. Pinning policy

1. Every runtime tool carries an **exact pinned version**. No floating `latest`, no `stable` channel
   alias resolved at build time, no unpinned container tag.
2. A version is adopted only from an **official primary source** (§4 and
   [`TOOLCHAIN_SOURCES.md`](./TOOLCHAIN_SOURCES.md)). A blog post, a tutorial, or model recollection
   is not a source.
3. Prereleases, betas, nightlies, and release candidates are **not adopted** unless a canonical
   requirement demands one, recorded in a decision record.
4. A version bump is a **recorded change**: this file, the compatibility matrix, and the validator
   lock are updated together, in the same pull request.
5. The pinned set must be **reproducible on a fresh clone** via
   [`scripts/bootstrap-step-03.sh`](../../scripts/bootstrap-step-03.sh).
6. Installation is **project-local or user-local**. Step 3 never destructively mutates a
   system-wide toolchain, and never requires `sudo` to reproduce.

---

## 2. Pinned versions

### 2.1 Client runtime (Flutter surfaces)

| Tool | Pinned version | Channel | Released | Source |
|---|---|---|---|---|
| Flutter SDK | **3.44.6** | stable | 2026-07-09 | Flutter release manifest |
| Dart SDK | **3.12.2** | bundled with Flutter 3.44.6 | 2026-07-09 | Flutter release manifest |
| JDK | **21.0.10** (Temurin-compatible, OpenJDK) | LTS | 2026-01-20 | host-verified |
| Android compileSdk / targetSdk | recorded in [`COMPATIBILITY_MATRIX.md`](./COMPATIBILITY_MATRIX.md) | — | — | Flutter template default for 3.44.6 |

Flutter SDK archive integrity:

```
archive : flutter_linux_3.44.6-stable.tar.xz
sha256  : a6320fd72e9a2690c08e2a6a70874a30cb120dee7c78f49d2c628bd7c9e20525
install : ~/flutter  (user-local, not system-wide, not committed)
```

The SDK is **never committed to this repository**. `~/flutter` is outside the repository tree and
the bootstrap script re-downloads and re-verifies the checksum on a fresh machine.

### 2.2 Backend runtime

| Tool | Pinned version | Source |
|---|---|---|
| PHP | **8.5.4** (NTS, CLI) | host-verified |
| Composer | **2.10.1** | host-verified |
| Laravel framework | **13.20.0** | Packagist official metadata |
| Laravel Sanctum | **4.3.2** | Packagist official metadata |

Laravel 13.20.0 declares `php: ^8.3`. The pinned PHP 8.5.4 satisfies that constraint; the pairing is
recorded in the compatibility matrix and asserted by the toolchain validator.

### 2.3 Data and cache runtime

| Tool | Pinned version | Delivery |
|---|---|---|
| PostgreSQL | **18.4** | container (development) + host client verified at 18.4 |
| Redis | **8.2** | container (development) |

**PostgreSQL is the authoritative database for every integration and tenant-isolation test.**
SQLite is not an acceptable substitute and must never be used as evidence of tenant isolation
(Rule 48, when introduced).

### 2.4 Supporting tooling

| Tool | Pinned version | Purpose |
|---|---|---|
| Docker Engine | **29.5.3** | development containers only |
| Docker Compose | **v5.1.4** | `infrastructure/docker-compose.dev.yml` |
| Node.js | **22.22.1** | tooling only — **not** an application runtime |
| Python | **3.14.4** | governance validators only — **not** an application runtime |

Node and Python are **governance and tooling** dependencies. Step 3 introduces no Node application
runtime and no Python application runtime. `validate-runtime-scope.py` continues to reject
application source in those languages outside the permitted tooling directories.

---

## 3. Deliberately NOT in the toolchain

Recorded so their absence is a decision rather than an oversight:

| Excluded | Reason |
|---|---|
| iOS / Xcode / macOS toolchain | Customer and Ops are **Android-first**; the Master Source authorises no iOS surface. Introducing one requires a decision record. |
| Production deployment tooling | Deployment is `ABSENT` and remains out of scope until a later Step. |
| Kubernetes, Terraform, cloud CLIs | No infrastructure is provisioned in Step 3. |
| WhatsApp, payment, or map provider SDKs | Owned by Steps 7, 5, and 8. Introducing one in Step 3 is a scope breach. |
| Staging or VPS configuration | Out of scope; no remote environment is contacted. |

---

## 4. Verification method

Every version above was resolved by executing a command or reading an official machine-readable
manifest, at the SHA recorded in the Step 3 evidence pack. No version in this file was recalled from
memory.

- Host tools: `--version` output captured directly.
- Flutter/Dart: the official Google-hosted release manifest
  `storage.googleapis.com/flutter_infra_release/releases/releases_linux.json`, reading
  `current_release.stable` and its pinned archive SHA256.
- Laravel and Sanctum: official Packagist metadata endpoints.

Full commands, outputs, timestamps, and the exact commit SHA are recorded in the Step 3 evidence
pack. Per Rule 01 and DEC-0013, evidence captured at one SHA does not carry to another.

---

## 5. Enforcement

[`scripts/validate-toolchain-locks.py`](../../scripts/validate-toolchain-locks.py) asserts that:

1. every version in §2 is an exact pin, not a range or an alias;
2. the pinned versions in this file, `COMPATIBILITY_MATRIX.md`, the Compose file, the CI workflows,
   and the Flutter/Laravel manifests **agree with each other**;
3. no forbidden tool from §3 has entered the tree;
4. the Flutter SDK archive checksum recorded here is a full 64-character SHA256;
5. no container image is referenced by a floating tag.

A drift between any two of those surfaces is a validator failure, not a formatting nit.

---

## 6. Honest status

- The pinned toolchain **is installed and verified on the maintainer's development host.**
- It is **not** verified across other operating systems; Step 3 claims Linux development only.
- No performance measurement, no device-matrix testing, and no production build has been performed.
- Application CI status is recorded in [`docs/STATUS.md`](../STATUS.md) and is not claimed here.
