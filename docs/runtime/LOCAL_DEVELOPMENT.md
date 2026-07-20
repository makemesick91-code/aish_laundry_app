# Local Development

**Status:** `IN PROGRESS` — Step 3, Phase A
**Scope:** local development only. Nothing here contacts a staging or production environment.

---

## 1. Prerequisites

Install the pinned toolchain first — see [`TOOLCHAIN.md`](./TOOLCHAIN.md). Then:

```bash
bash scripts/bootstrap-step-03.sh
```

The bootstrap script is **additive and checksum-verified**: it downloads the pinned Flutter SDK only
if absent, verifies its SHA256 against the vendor manifest before extracting, and refuses to
overwrite an existing SDK. It requires no `sudo` and makes no destructive system-wide change.

## 2. Start the development services

```bash
bash scripts/start-dev-services.sh    # PostgreSQL 18.4 + Redis 8.2, waits for real health
bash scripts/check-dev-services.sh    # proves connectivity by executing a query and a PING
bash scripts/stop-dev-services.sh     # stops containers, PRESERVES data
```

Verified endpoints:

| Service | Address | Pinned version |
|---|---|---|
| PostgreSQL | `127.0.0.1:55433` | 18.4 |
| Redis | `127.0.0.1:56379` | 8.2 |

Both bind to **loopback only**. Publishing them on `0.0.0.0` would expose a database to the local
network, which matters on shared or public Wi-Fi.

### Why these ports

`55433` rather than the more obvious `55432`, and `56379` rather than `6379`, because unrelated
pre-existing containers already hold the common ports on the maintainer's host. This project does
not stop, reconfigure, or take ports from containers it does not own.

### Registry note

Images are pulled through **`mirror.gcr.io`**, Google's official pull-through cache for Docker Hub
official images, because `registry-1.docker.io` is not reachable from this development network
(verified: both IPv4 and IPv6 time out, while `auth.docker.io` resolves normally).

Both images are **digest-pinned**, so the mirror is a transport rather than a trust anchor — a
substituted image cannot be delivered silently.

The pinned versions were **not** downgraded to match whatever was cached locally.
`postgres:17-alpine` and `redis:7-alpine` were already present on the host; using them would have
made the pin a fiction and any "PostgreSQL 18.4 is authoritative" claim false.

## 3. Destroying development data

```bash
bash scripts/reset-dev-data.sh --yes-destroy-development-data
```

This is the only script that deliberately destroys data, so it **fails closed**:

1. refuses unless `APP_ENV` is a development value;
2. refuses if any of `APP_ENV`, `DB_HOST`, `REDIS_HOST`, `DATABASE_URL`, `REDIS_URL` contains a
   `prod` / `staging` / `live` marker;
3. refuses unless `DB_HOST` is loopback;
4. requires the explicit confirmation flag;
5. removes only the volumes declared by this compose project;
6. never uses `rm -rf` against a path, so a variable expanding to empty cannot delete anything.

It cannot reach a production database, because it has no way to address one.

## 4. Environment files

A working local checkout needs **two** ignored environment files, not one:

```bash
bash scripts/bootstrap-env-files.sh
```

That is the canonical command. It creates each destination only when it is absent, never overwrites
an existing file, validates both templates before copying, and prints file *status* only — never file
content and never `DB_PASSWORD`. Running it twice is safe.

The equivalent manual sequence is:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
```

| File | Created from | Read by |
|---|---|---|
| `.env` | [`.env.example`](../../.env.example) | local infrastructure and bootstrap tooling |
| `backend/.env` | [`backend/.env.example`](../../backend/.env.example) | **Laravel** |

**Copying only the root file is not enough.** Laravel reads `backend/.env`. Before
[DEC-0027](../decisions/DEC-0027-local-development-environment-bootstrap-and-template-contract.md)
this document instructed only the root copy, so a fresh clone followed exactly as written produced no
`backend/.env` at all and the backend could not authenticate to PostgreSQL. The defect stayed
invisible on the maintainer's host because an ignored `backend/.env` already existed there — **local
success built on a pre-existing ignored file is not fresh-clone evidence.**

### Which port, and where

| Context | Host | Port |
|---|---|---|
| Backend running on the host (the documented path) | `127.0.0.1` | **`55433`** |
| PostgreSQL's own listener inside the container | — | `5432` |

`infrastructure/docker-compose.dev.yml` publishes `127.0.0.1:55433:5432`. The internal `5432` is not
interchangeable with the published `55433`: replacing it inside the compose file would break the
container, and GitHub Actions service containers legitimately use `5432` on their own network.

`scripts/validate-dev-environment-contract.py` enforces that `.env.example` and `backend/.env.example`
carry identical `DB_*` values and that this bootstrap path exists. Template drift is a CI failure, not
a discovery made later during a fresh clone.

**Neither `.env` nor `backend/.env` is ever committed**; both are git-ignored, and an existing local
file is always preserved so your own overrides survive. The committed templates carry **fictional,
local-development-only** values that grant access to nothing beyond a throwaway local service.
Production and staging credentials are managed separately and are out of scope for this repository.
This repository is `PUBLIC`; a committed credential is compromised at push time and rotation — not
deletion — is the first remediation (Rule 03, Rule 23).

## 5. What is verified, and what is not

**Verified by executed command** at the SHA recorded in the Step 3 evidence pack:

- development PostgreSQL reports `server_version = 18.4`, accepts a connection, and completes a query
  round-trip;
- development Redis answers `PING` with `PONG`, reports `redis_version = 8.2.7`, and completes a
  write/read/delete cycle;
- the stop/start cycle is reproducible;
- every refusal path of the reset guard actually refuses.

**Backend runtime is `PRESENT — STEP 3 FOUNDATION ONLY`** and the Flutter workspace is `PRESENT`. The
earlier text here — "no application has connected to either service — the backend runtime is still
`ABSENT`" — became false once the runtime was introduced under DEC-0024 and is corrected by DEC-0027.

**Not claimed by this document:**

- migration, test, and build results are **not asserted here**. Every executed result lives in the
  Step 3 evidence pack under [`../../evidence/step-03/`](../../evidence/step-03/), bound to the exact
  40-character commit SHA it was produced at (Rule 01, DEC-0013). A result quoted without its SHA is
  not evidence, so this page quotes none;
- **Step 4+ laundry business functionality remains `NOT IMPLEMENTED`** — no POS, order, payment,
  production, tracking, delivery, reminder, or reporting capability exists;
- deployment remains `ABSENT`. Nothing here deploys anything.

Connectivity is not correctness. These services exist so that later Step 3 phases can run
tenant-isolation tests against the **authoritative** engine; SQLite is never an acceptable substitute
for that evidence.
