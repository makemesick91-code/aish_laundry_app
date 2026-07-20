# Step 3 evidence — DEC-0027 local development environment contract

**Scope:** the local-development environment template contract and its bootstrap path.
**Produced:** 20 July 2026, Asia/Jakarta.
**Environment:** Linux, PHP 8.5.4, Flutter 3.44.6 / Dart 3.12.2, PostgreSQL 18.4 and Redis 8.2 running
as the loopback-bound local development services.

**Sanitisation:** no credential, password value, token, or personal datum appears in this file. The
validator whose output is summarised here never prints `DB_PASSWORD`; password assertions are boolean
properties only.

---

## 1. What was observed by command

These were each observed directly, by executed command, in the session that produced this record.

### Baseline before the correction

| Observation | Result |
|---|---|
| Branch | `feature/step-03-runtime-auth-multitenancy-rbac` |
| `HEAD` at observation | `d6cee2d14b7eb203055df9fdb22776e9bad27d81` |
| `HEAD` equals PR #13 head | yes |
| Working-tree modifications | exactly one: `backend/.env.example` |
| `backend/.env` tracked | no — git-ignored via `backend/.gitignore:7`, untracked |

### The owner-applied diff to `backend/.env.example`

**Three lines changed, not five:**

| Key | Before | After |
|---|---|---|
| `DB_PORT` | `5432` | `55433` |
| `DB_DATABASE` | `replace_with_local_database_name` | `aish_laundry_dev` |
| `DB_USERNAME` | `replace_with_local_username` | `aish_dev` |

**`DB_HOST` and `DB_PASSWORD` were already correct and were not modified by this change.** Any
statement that the owner changed them is inaccurate.

### The defect the value correction did not fix

| Observation | Result |
|---|---|
| `docs/runtime/LOCAL_DEVELOPMENT.md` instruction, before | "Copy `.env.example` to `.env`." — root only |
| Instruction to create `backend/.env`, before | **none anywhere in committed documentation** |
| `scripts/bootstrap-step-03.sh` environment step, before | **none** — no `.env` handling at all |
| File Laravel reads | `backend/.env` |
| `backend/.env` on the maintainer host | present, ignored, pre-existing |

A fresh clone followed exactly as documented therefore produced no `backend/.env`. Local success was
built on a pre-existing ignored file, which is not fresh-clone evidence.

### Stale canonical status, observed

| Document | Stale claim | Contradicted by |
|---|---|---|
| `CLAUDE.md` §2 | Step 3–14 `PLANNED`; backend `ABSENT`; Flutter `ABSENT`; Application CI `NOT APPLICABLE` | `backend/composer.json`, `pubspec.yaml`, three runtime workflows, `docs/STATUS.md` |
| `.claude/rules/49` | backend `ABSENT`; Flutter `ABSENT`; CI `NOT APPLICABLE UNTIL…` | same |
| `docs/STATUS.md` §5 | "There is no application code, therefore there is nothing to build or test" | same document's own table declaring Application CI `ACTIVE` |
| `docs/STATUS.md` §5 | unit / integration / tenant-isolation suites `NOT APPLICABLE` | 15 test files under `backend/tests/` |

## 2. Executed results

All commands run from the working tree that became the commit this file is committed in.

| Gate | Command | Result |
|---|---|---|
| Environment contract | `python3 scripts/validate-dev-environment-contract.py` | **PASS** — 49/49 checks |
| Adversarial suite | `bash scripts/test-dev-environment-contract.sh` | **PASS** — 39/39 expectations met, 0 failed |
| Canonical status | `python3 scripts/validate-status.py` | **PASS** — 38/38 |
| Required files | `python3 scripts/validate-required-files.py` | **PASS** — 111/111 |
| Decision records | `python3 scripts/validate-decisions.py` | **PASS** |
| Markdown links | `python3 scripts/validate-markdown-links.py` | **PASS** — 1692 links resolve |
| Master Source checksum | `sha256sum -c MASTER_SOURCE.sha256` | **PASS** (unchanged this session) |
| Runtime scope | `python3 scripts/validate-runtime-scope.py` | **PASS** — 5/5 |
| Runtime CI validator | `python3 scripts/validate-runtime-ci.py` | **PASS** — 43/43 |
| Secret scan | `bash scripts/validate-secrets.sh` | **PASS** — 10/10 |
| Public repository safety | `bash scripts/validate-public-repository-safety.sh` | **PASS** — 14/14 |
| Destructive guard self-test | `--self-test` | **PASS** — 171/171 |
| DEC-0026 suite | `bash scripts/test-dec-0026-guard.sh` | **PASS** — 38/38 |
| Runtime-scope harness | `bash scripts/test-step-03-validators.sh` | **PASS** — 36/36 |
| Step 0 regression | `bash scripts/verify-step-00.sh` | **PASS** — 11/11 gates |
| Step 1 regression | `bash scripts/verify-step-01.sh` | **PASS** |
| Step 2 regression | `bash scripts/verify-step-02.sh` | **PASS** |
| Backend suite | `php artisan test` | **PASS** — 202 passed, 1293 assertions |
| Canonical verifier | `bash scripts/verify-step-03.sh` | 49 passed, 1 failed, 1 skipped — see below |

**The canonical verifier's single failure was `working tree clean`**, which is expected and correct
while the change is uncommitted: it is the gate asserting there is nothing left unstaged. It is
reported here as a failure rather than omitted.

**The single skip was `workflow lint`** — `actionlint` and `shellcheck` are not installed on this
host. A skip is not a pass. Workflow linting is covered by the `Workflow / actionlint` required CI
context at the candidate SHA, and no local workflow-lint result is claimed.

The environment harness verified the working tree byte-identical before and after its run and would
have refused to report success otherwise.

## 3. What is NOT established by this record

- **That the corrected bootstrap yields a working database connection from a genuinely clean
  checkout.** That is a fresh-clone claim requiring its own executed run bound to its own exact SHA.
  It has not been performed and is not asserted.
- **Exact-SHA CI results.** The runs above preceded the commit. The authoritative exact-SHA evidence
  is the CI run against the candidate commit, recorded separately.
- **Any Step 4+ capability.** POS, orders, payments, production, tracking, delivery, reminders,
  finance, and subscription remain `NOT IMPLEMENTED`.
- **Any deployment.** Deployment remains `ABSENT`. Nothing here deploys anything.
- **Step 3 `GO`.** Step 3 remains `IN PROGRESS`. `GO` is the repository owner's to confer.

## 4. Prior-session claims, not re-verified

The following were supplied as context and are recorded as
**REPORTED FROM A PRIOR SESSION — NOT RE-VERIFIED IN STAGE 1 OR STAGE 2A**:

- a fresh-clone run failing with `SQLSTATE[08006]` against an unresolved placeholder username;
- Graphify extraction figures (version 0.8.35, 9,218 files, 83,857 nodes);
- any earlier mutation-count or test-count figure not reproduced above.

None is relied upon by DEC-0027. The defect it records was established independently, by direct
observation of the documentation, the bootstrap script, and the git diff.
