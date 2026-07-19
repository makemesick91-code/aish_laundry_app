# Toolchain Sources — Provenance Record

**Status:** `IN PROGRESS` — Step 3, Phase A
**Companion to:** [`TOOLCHAIN.md`](./TOOLCHAIN.md)

Every pinned version in the toolchain must be traceable to an **official primary source**. This file
records which source was consulted for each pin, so a reader can re-verify independently rather than
trusting this repository's word for it.

---

## 1. What counts as an official primary source

**Accepted:**

- the vendor's own machine-readable release manifest or package registry API;
- the vendor's own documentation on the vendor's own domain;
- the output of the tool itself (`--version`) on the verified host.

**Not accepted, ever:**

- a blog post, tutorial, Stack Overflow answer, or aggregator site;
- a mirror or third-party redistribution not operated by the vendor;
- an AI model's recollection of a version number.

Rule 01 applies directly: a version adopted without a source is an **unverified claim** and must be
labelled as such rather than presented as a pin.

---

## 2. Source register

### Flutter SDK and Dart SDK

| Field | Value |
|---|---|
| Pinned | Flutter 3.44.6 / Dart 3.12.2 |
| Source | `https://storage.googleapis.com/flutter_infra_release/releases/releases_linux.json` |
| Operator | Google — the canonical Flutter release infrastructure |
| Method | Read `current_release.stable`, matched the release entry by hash, read `version`, `dart_sdk_version`, `release_date`, `archive`, and `sha256`. |
| Integrity | Archive SHA256 published **by the same manifest** and re-verified locally after download. |
| Release date | 2026-07-09 |

The manifest is the same artefact the official `flutter` installer consumes. Adopting the manifest's
`current_release.stable` and then **freezing it** gives a pin that is both current and reproducible.

### Laravel framework and Laravel Sanctum

| Field | Value |
|---|---|
| Pinned | `laravel/framework` 13.20.0, `laravel/sanctum` 4.3.2 |
| Source | `https://repo.packagist.org/p2/laravel/framework.json`, `https://repo.packagist.org/p2/laravel/sanctum.json` |
| Operator | Packagist — the canonical Composer registry Laravel publishes to |
| Method | Read the version list, filtered to stable (no prerelease suffix), took the newest, and read its declared `require.php`. |
| Constraint captured | `laravel/framework` 13.20.0 requires `php: ^8.3`. |

### Host-verified tools

Resolved by executing the tool on the development host and capturing its own version output. The
full captured output is stored in the Step 3 evidence pack at the exact commit SHA.

| Tool | Pinned | Command |
|---|---|---|
| PHP | 8.5.4 (NTS) | `php --version` |
| Composer | 2.10.1 | `composer --version` |
| PostgreSQL client | 18.4 | `psql --version` |
| JDK | 21.0.10 (OpenJDK) | `java -version` |
| Docker Engine | 29.5.3 | `docker --version` |
| Docker Compose | v5.1.4 | `docker compose version` |
| Node.js | 22.22.1 | `node --version` |
| Python | 3.14.4 | `python3 --version` |

### Container images

| Image | Pinned tag | Registry |
|---|---|---|
| PostgreSQL | `postgres:18.4` | Docker Official Images |
| Redis | `redis:8.2` | Docker Official Images |

Images are referenced by an explicit version tag, never by `latest`. Digest pinning is recorded in
the Compose file where the local environment resolves one.

---

## 3. Re-verification obligation

1. This register is re-checked whenever a pin changes.
2. A pin is **never** advanced silently to track upstream. Upstream releasing 3.44.7 does not change
   this repository's pin; changing the pin is a recorded decision.
3. If a pinned version is **withdrawn or found vulnerable upstream**, that is a security event: it is
   recorded, the pin is advanced deliberately, and the reason is written down — the pin is not
   quietly edited.
4. Evidence for these lookups is bound to the exact commit SHA at which it was captured (DEC-0013).
   A source check performed at one SHA does not carry to another.

---

## 4. Honest status

The versions above were resolved by executing commands and reading vendor manifests during Step 3
Phase A. They reflect the state of those sources **at the time of capture**, recorded in the evidence
pack. This file asserts provenance — it does **not** assert that the pinned versions are free of
undiscovered vulnerabilities, and no such claim is made anywhere in Step 3.
