# Step 3 — GO Closure Evidence

**Classification:** `GO WITH ACCEPTED DEVIATION`
**Recorded state:** `STEP 3 — COMPLETE / MERGED / GO TAGGED`
**Master Source:** v1.4.0 · checksum `160f62dcd033781c200e627175091273a3aea157f5d93a670474e8ab3e598831`

This document records the exact-SHA evidence behind the Step 3 GO tag. It is an
**evidence record only** — it creates no runtime, changes no rule, and moves no
tag. Every figure below was produced against the commit it names (DEC-0013).

> **Scope note.** This branch is documentation/evidence-only. It deliberately
> does **not** flip the Step 3 working-status line in `docs/STATUS.md`,
> `docs/ROADMAP.md`, or `.claude/rules/15` / `.claude/rules/49`. Advancing that
> line to GO is a coordinated governance change: `scripts/validate-roadmap.py`
> pins `CURRENT_STEP = 2` and requires Step 3 to remain `PLANNED`, and the two
> enforcement rules are application rules. Moving all of them together is a
> separate, owner-authorized PR — recorded here as the one open follow-up, not
> performed silently.

---

## 1. Merge lineage

| PR | Purpose | State | Merge commit |
|---|---|---|---|
| **#13** | Step 3 runtime, auth, tenancy, RBAC foundation (Phase A) | **MERGED** | `1acb99ffc6b8d36239c68dadb497ffc880f19608` |
| **#15** | DEC-0026 harness SKIPs on main / non-Step-3 branches | **MERGED** | `0e2554338812b05eba8411afeb099212b05f9761` |

- Authorized Step 3 candidate (PR #13 head): `bef63cfd7b79be8a4c412449f6f34d614bfd0acb`
- Canonical Step 3 merge SHA (main at tag time): `0e2554338812b05eba8411afeb099212b05f9761`
- PR #15 mergedAt: `2026-07-21T00:04:49Z` (squash, `--match-head-commit`)

## 2. The immutable GO tag

| Field | Value |
|---|---|
| Tag name | `aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` |
| Type | annotated (`tag`) |
| Tag object SHA | `8b37230ed8df8da343a1546fd949d8a41329fbdf` |
| Peeled commit SHA | `0e2554338812b05eba8411afeb099212b05f9761` |
| Local ≡ remote tag object | yes |
| Remote peeled ≡ merge SHA | yes |

The tag is immutable: it is never moved, deleted, recreated, or retargeted.

## 3. Main exact-SHA CI — 15/15 on `0e25543`

All 15 required contexts `success`, every `head_sha = 0e2554338812…`:
`validate · Documentation / links · Required Gate · secret-scan ·
Workflow / actionlint · classify · product-requirements · domain-model ·
threat-model · design-system · ux-foundation · accessibility-privacy ·
runtime-foundation · tenant-isolation · authentication-rbac`.

Representative main run IDs: Runtime Foundation `29789224486`, Runtime
Detection/classify `29789224548`, Security `29789224477`, Governance
`29789224499`, Tenant Isolation `29789224475`, Authentication and RBAC
`29789224535`. `runtime-foundation` executed real work (Flutter tests + Gradle
`assembleDebug` → `app-debug.apk`); `classify` reported scope only and claimed
no application test result.

## 4. Canonical verifier — `scripts/verify-step-03.sh`

| Environment | Result | DEC-0026 |
|---|---|---|
| Canonical `main` @ `0e25543` | **51 passed, 0 failed, 1 skipped** | SKIP (exit 78) — branch pin |
| Fresh-main clone @ `0e25543` | **51 passed, 0 failed, 1 skipped** | SKIP (exit 78) — path pin |
| Step 3 feature branch (candidate) | full suite runs | **38/38 PASS** |

The single permitted skip is DEC-0026 only; it is always shown as SKIP and never
as PASS. The 38/38 scaffold-authorization result is bound to the feature-branch
SHA; on `main` and in a fresh clone the guard's owner-approved pins make the
suite correctly un-runnable (accepted bounded exception).

## 5. Database, migrations, backend (fresh-main clone @ `0e25543`)

- PostgreSQL **18.4** (query round-trip); Redis **8.2.7** (PING + write/read/delete)
- Migrations: **19**, `migrate:fresh --seed` / `rollback` / re-apply all succeed
- Public tables: **20**; zero Step 4+ business tables
- Backend suite: **211 passed, 1328 assertions, 0 failed**
- `PolicyAuthorizationMatrixTest`: **9 passed, 35 assertions**

## 6. Security posture (merge SHA)

Security-critical suites on merged main: **121 passed, 913 assertions** —
tenant isolation, structural isolation, RBAC, authentication adversarial matrix,
Redis tenant partitioning, log redaction, policy authorization, session
management.

- CORS fails closed on wildcard+credentials; bearer/session tokens SHA-256 hashed
- Rate limiting per-identifier and per-IP on login and password-reset
- Authentication/recovery responses do not disclose identifier existence
- NULL-tenant audit entries invisible to tenant members; DeviceSession
  self-service carve-out verified both directions
- No browser token storage; generated Web build scan clean

**Open CRITICAL: 0 · Open HIGH: 0 · Cross-tenant exposure: 0.** Not independent
human review (single-maintainer, DEC-0017).

## 7. First-party relationship analysis

Graphify **0.8.35**; first-party corpus (`git archive` @ `0e25543`, 715 files,
no `vendor/` / caches / build): **9,174 nodes, 11,132 edges**.
`scripts/analyze-step-03-relationships.py`: **63/63** — zero protected routes
without authentication, zero tenant routes without tenant enforcement, zero
registered policies without test coverage, zero tenant-owned models without
`tenant_id`, zero allow-list orphans; cache-partitioning and session-ownership
suites resolve.

## 8. Client artifacts (fresh-main; debug builds, not byte-reproducible)

| Surface | applicationId | Size (bytes) | SHA-256 |
|---|---|---|---|
| Customer Android (debug) | `id.aishtech.laundry.customer` | 157,557,662 | `89e618e613b1a94c075c9f86ebbf8395a2aae373190be1c50d8a3f6a89828338` |
| Ops Android (debug) | `id.aishtech.laundry.ops` | 157,562,526 | `210884e346440660b05c873f3ce5ce9d16dbd051414e41cedf7561ad2c16ceab` |
| Admin Web (release build) | — | 42,471,310 | scan clean (no token storage / credential / dev value) |

## 9. Accepted deviations (recorded, not removed)

1. **DEC-0017** — single-maintainer approval substitution; no independent human
   review occurred. Automated/procedural controls are compensating, and are not
   equivalent to independent peer review.
2. **DEC-0026** — owner-approved path and branch pin. Feature candidate 38/38
   PASS; `main` and non-canonical clones expose an explicit exit-78 SKIP. Accepted
   bounded exception; the SKIP remains visible and is never represented as PASS.
3. **Runtime limitations** — Android artifacts debug-only; no release signing or
   keystore; no device/emulator execution; no cold-start/memory/performance
   measurement; no physical accessibility audit; no user UAT; Admin Web not
   deployed; `aishtech.id` ownership unverified; no staging or production
   deployment.

## 10. Governance state at tag time

- Ruleset `19164588`: active, strict, zero bypass actors, exactly 15 required
  contexts, rules `[deletion, non_fast_forward, pull_request, required_status_checks]`
- Step 0–2 GO tags: annotated, unmoved
- Deployment: **ABSENT** · Step 4: **PLANNED** · Dependabot PR #2: **OPEN, untouched**
- Repository visibility remains **PUBLIC** (AMENDMENT-0001, DEC-0016); every datum
  above is fictional or a non-secret artifact hash (Rule 23).
