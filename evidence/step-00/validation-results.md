# Validation Results — Step 0

## Canonical command

```bash
bash scripts/verify-step-00.sh
```

The script resolves the repository root from its own location, so it runs
correctly from any working directory. It was executed from `/tmp` during
development to prove cwd-independence. It exits non-zero if any gate fails and
does not swallow failures.

## Gate summary (local, feature branch)

```
GATE                       RESULT EXIT
-------------------------- ------ ----
required-files             PASS   0
master-source              PASS   0
decisions                  PASS   0
roadmap                    PASS   0
status                     PASS   0
pricing                    PASS   0
rules-traceability         PASS   0
no-runtime                 PASS   0
markdown-links             PASS   0
secrets                    PASS   0
destructive-guard          PASS   0
------------------------------------------------------------------------
GATES PASSED: 11 / 11
STEP 0 VERIFICATION: PASS
```

Exit code: `0`.

## Per-gate detail

| Gate | Checks | Notable assertions |
|---|---|---|
| `required-files` | all required Step 0 paths | Every document, all 16 rule files, all 15 decision records, hook, skill, templates |
| `master-source` | 34/34 | Version exactly `1.0.0`; baseline date `19 July 2026`; product name `Aish Laundry App`; no competing canonical product name; 25 required topic sections; ≥400 lines (actual **1574**); SHA-256 matches recorded digest |
| `decisions` | 46/46 | DEC-0001..DEC-0015 each present exactly once; no duplicate or missing IDs; each `ACCEPTED`; each carries all 12 required headings |
| `roadmap` | Steps 0–14 | No duplicate or missing step numbers; canonical titles; Steps 1–14 `PLANNED` |
| `status` | 23/23 | Step 1–14 `PLANNED`; no feature `IMPLEMENTED`; backend `ABSENT`; Flutter workspace `ABSENT`; deployment `ABSENT`; UAT `NOT STARTED`; application CI `NOT APPLICABLE` |
| `pricing` | 20/20 | All canonical plan figures consistent across all 8 files asserting them; no lifetime cloud plan offered |
| `rules-traceability` | 48/48 | All 16 rule files present; tenant gate in rule 02; financial gate in rule 04; pickup-delivery in 09; aging ladder in 10; offline in 07; WhatsApp in 08; git/CI in 11; autonomy in 12; pricing in 14; status in 15; traceability doc references every rule filename |
| `no-runtime` | 7/7 | No `pubspec.yaml`, `composer.json`, `artisan`, `.dart_tool/`, migrations, or application source in any language; no symlink escaping the repository |
| `markdown-links` | 399 links | All 399 relative markdown links resolve to existing paths; 0 broken |
| `secrets` | 10/10 | 61+ tracked files scanned; no private key, AWS key, GitHub token, or credential assignment found |
| `destructive-guard` | 16/16 | Hook present and executable; dangerous commands exit 2; safe commands exit 0 |

## Master Source checksum

```
9b9539d0eefa3c9bdbd403cf99139218b0c8aa17e9473d7b616f59d1513322fe  MASTER_SOURCE.md
```

Recorded in `docs/MASTER_SOURCE.sha256` and verified by
`scripts/validate-master-source.py` against the actual file content, not against
a stored copy of itself.

## Adversarial verification — the validators are not theatre

A green result is only meaningful if the suite can go red. Each of the following
tampers was injected, the suite was re-run, and the tamper was then reverted.

| Injected tamper | Detected by | Suite exit |
|---|---|---|
| Appended `\| Public tracking portal \| IMPLEMENTED \|` to `docs/STATUS.md` | `status` | 1 (non-zero) |
| Appended a trailing line to `docs/MASTER_SOURCE.md` (digest drift) | `master-source` | 1 (non-zero) |
| Injected an AWS access key and a `password=` assignment | `secrets` | non-zero |
| Injected `pubspec.yaml` and a `.php` file | `no-runtime` | non-zero |
| Injected `Starter Rp89.000 / 2 outlet / 9 staff` | `pricing` | non-zero |
| Injected a lifetime cloud plan offer | `pricing` | non-zero |
| Injected a 30-day trial (canonical is 14 days) | `pricing` | non-zero |

After reverting every tamper, `docs/MASTER_SOURCE.md` hashed back to the exact
recorded digest `9b9539d0…22fe` and the suite returned to 11/11 PASS with exit 0.

## Destructive guard verification

`.claude/hooks/guard-destructive-operations.sh --self-test` → **51/51 PASS**, exit 0.

Independently re-verified outside the script's own self-test, in both input modes:

| Command | Mode | Expected | Observed |
|---|---|---|---|
| `git push --force origin main` | bare arg + JSON | BLOCK | exit 2 |
| `rm -rf /` | bare arg + JSON | BLOCK | exit 2 |
| `git clean -fdx` | bare arg + JSON | BLOCK | exit 2 |
| `git reset --hard HEAD~3` | bare arg | BLOCK | exit 2 |
| `gh repo delete foo` | bare arg + JSON | BLOCK | exit 2 |
| `DROP DATABASE prod` | bare arg | BLOCK | exit 2 |
| `git tag -d v1` | bare arg | BLOCK | exit 2 |
| `flutter create .` | bare arg | BLOCK | exit 2 |
| `composer create-project laravel/laravel backend` | bare arg | BLOCK | exit 2 |
| `chmod -R 777 /` | bare arg | BLOCK | exit 2 |
| malformed JSON payload | JSON | BLOCK (fail closed) | exit 2 |
| `git status` | bare arg + JSON | ALLOW | exit 0 |
| `git push origin feature/step-00-...` | bare arg + JSON | ALLOW | exit 0 |
| `git cleanup-notes` | bare arg | ALLOW | exit 0 |
| `rm -rf build/` | bare arg | ALLOW | exit 0 |
| `git tag -a v1 -m x` | bare arg | ALLOW | exit 0 |
| `chmod -R 755 scripts` | bare arg | ALLOW | exit 0 |
| `flutter --version` | bare arg | ALLOW | exit 0 |
| `composer install` | bare arg | ALLOW | exit 0 |

### Defect found and fixed during verification

The guard originally read raw stdin as the candidate command. A real Claude Code
`PreToolUse` hook delivers a **JSON envelope**, not a bare string, so the entire
JSON blob was pattern-matched as one string and `git push --force origin main`
returned exit 0 — **the guard failed open in the only mode that matters in
production**. It would have been registered as an active guard while blocking
nothing.

Fixed by extracting `tool_input.command` from the JSON payload, and failing
**closed** (exit 2) when a JSON-shaped payload cannot be parsed. Both modes and
the malformed-payload case are verified in the table above, and the 51-case
self-test still passes with no regression.

## Hook activation

The guard is registered in `.claude/settings.json` as a `PreToolUse` hook matching
`Bash`. The file is valid JSON, is project-scoped, and does not modify or weaken
any user-level Claude configuration. `.claude/settings.local.json` is git-ignored
and is not committed.

## Known limitation, accepted

`TRUNCATE` matches on any word boundary, so a command whose text merely contains
the word "truncate" (for example `echo "truncate the log"`) will be blocked. This
is a false-positive source. Fail-closed behaviour was the stated preference for
destructive SQL, so the pattern is retained deliberately and recorded here rather
than silently loosened.
