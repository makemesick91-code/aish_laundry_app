# Step 4 — Laundry Master Data — Evidence Pack

**Status: `IN PROGRESS`.** This pack records what has been executed. It confers
nothing. `GO` is conferred by the repository owner and is never self-declared by
an agent (Rule 01).

## Binding SHA

Every output in this directory was produced from exactly this commit, with a
clean working tree:

```text
bfbf3bbf765540ada119ba17f349ca9ef32678a4
```

| Field | Value |
| --- | --- |
| Repository | `makemesick91-code/aish_laundry_app` (PUBLIC — AMENDMENT-0001, DEC-0016) |
| Branch | `feature/step-04-laundry-master-data` |
| Pull request | #18 |
| Capture SHA | `bfbf3bbf765540ada119ba17f349ca9ef32678a4` |
| Working tree at capture | clean (`git status --porcelain` empty) |
| Timezone | Asia/Jakarta; captured timestamps inside the outputs are UTC |
| Database | PostgreSQL 18.4, the only engine whose isolation result counts as evidence (Rule 43) |
| Flutter / Dart | 3.44.6 / 3.12.2, matching the recorded pins |
| PHP | 8.5.4 |

**Evidence produced at one SHA does not carry over to another** (Rule 01,
DEC-0013). If the tree changes, every file here is stale and must be re-captured
before it may be cited.

## What was captured

| File | Command | Result |
| --- | --- | --- |
| [`verify-step-04.txt`](verify-step-04.txt) | `bash scripts/verify-step-04.sh` | **24 passed, 0 failed, 1 skipped** |
| [`adversarial.txt`](adversarial.txt) | `bash scripts/test-step-04-validators.sh` | **11/11 expectations met** |
| [`dec-0030-audit.txt`](dec-0030-audit.txt) | `python3 scripts/validate-dec-0030-labels.py` | **4/4 checks passed** |
| [`backend-suite.txt`](backend-suite.txt) | `cd backend && php artisan test` | **386 tests, 3568 assertions** |
| [`isolation-matrix.txt`](isolation-matrix.txt) | `cd backend && php artisan test --filter Step04IsolationMatrixTest` | **15 tests, 728 assertions** |
| [`schema-invariants.txt`](schema-invariants.txt) | `php backend/scripts/ci/assert-step04-invariants.php` | **2/2 checks passed** |
| [`ops-flutter.txt`](ops-flutter.txt) | `cd apps/ops_android && flutter test` | **78 tests passed** |

### The one skipped gate, named rather than folded away

`verify-step-04.sh` reports **1 skip**, and it is not hidden in the pass count:
the delegated Step 3 verifier itself skips the DEC-0026 scaffold-authorization
suite. That suite runs 38/38 only on a Step 3 feature branch; everywhere else it
is a deliberate, owner-approved, visible exit-78 skip (Rule 49, DEC-0026). A gate
that could not run has verified nothing, and this pack says so rather than
counting it.

## The hard gates

**Tenant isolation (Rule 02, Rule 48).** `Step04IsolationMatrixTest` exercises
all six access paths Rule 48 hard rule 3 enumerates — direct ID, list, filter,
free-text search, export, and file URL — across the Step 4 aggregates. The export
and file-URL paths are recorded as `NOT APPLICABLE` and their ABSENCE is asserted
against the route table, rather than two rows being quietly omitted. Every denial
is paired with a positive control built from the same fixture, because a 404 from
a typo'd route looks identical to a 404 from tenant isolation.

**Financial integrity (Rule 04).** `assert-step04-invariants.php` reads
`information_schema` and confirms no money column uses an inexact numeric type —
4 money columns examined. It reads the live schema rather than migration source,
because a migration can be edited, superseded or bypassed by a manual DDL.

**Every business table carries `tenant_id`.** Same script: 14 of 15 listed Step 4
business tables exist, and every one that exists is tenant-scoped.

## What this pack does NOT establish

Stated plainly, because a pack that only lists successes reads as broader
assurance than it has.

1. **No client has ever reached the live backend.** No concrete `AuthService`
   exists outside `packages/testing`, and no `main.dart` overrides
   `authServiceProvider`, so all three Flutter apps throw on the startup frame at
   a real launch. Client-side Step 4 evidence is widget- and contract-level only:
   real `ApiClient`, real envelope decoding, scripted HTTP transport. **No Step 4
   claim here asserts an end-to-end verified counter flow.** See
   [`../../docs/STATUS.md`](../../docs/STATUS.md) §2.
2. **No Android or web artefact was rebuilt for this capture.** The debug-APK and
   release-web results recorded for Step 3 stand at their own SHA and are not
   restated here as Step 4 results.
3. **No deployment, no device execution, no performance measurement, no
   accessibility audit, no UAT.** Deployment remains `ABSENT`.
4. **Independent human review is `ABSENT`** (DEC-0017). The compensating controls
   — the ruleset, exact-SHA CI, deterministic and adversarially tested
   validators, and recorded internal re-verification — are load-bearing and are
   **not** equivalent to a second human reader. Nothing in this pack was
   independently reviewed; it was internally re-verified under single-maintainer
   governance.
5. **Authoritative CI on this exact SHA is recorded separately** and is not part
   of this capture. Checkpoint CI runs `29810312527`–`29810312616` belong to
   `1637fa01151afe0460e8177cfe8c1b8cde172fed` and are **stale** as final Step 4
   evidence.

## Defects found and fixed during this capture

Recorded because a pack that hides its own corrections is not evidence.

| # | Defect | Where |
| --- | --- | --- |
| 1 | `OpsAsyncSection` compared method tear-offs to detect a changed query, so it reloaded forever | Ops surface |
| 2 | `_SatelliteList` started its future inside `build`, firing a request per rebuild | Ops surface |
| 3 | `setState(() => _future = ...)` returns the assigned Future, which Flutter rejects | Ops surface |
| 4 | The consent row overflowed 58 px on a 400 dp viewport, clipping the status label | Ops surface |
| 5 | `MasterDataRepository._object` cast blindly, throwing an opaque `TypeError` on a shape mismatch | Shared repository |
| 6 | The DEC-0030 audit missed a CamelCase model name (`OutletPrinterReceiptTemplate`) | Validator |
| 7 | The audit's placeholder check vouched for every future route if one placeholder survived anywhere | Validator |
| 8 | Two verifier gates failed for an environment reason (`$_ENV` unset in PHP CLI), reporting a schema defect that did not exist | Verifier |
| 9 | A verifier gate flagged the tests that assert an unmask control's absence | Verifier |
| 10 | A verifier gate flagged the fabricated sequential phone numbers Rule 45 permits | Verifier |
| 11 | The Step 4 gate read the Step 3 verifier's documented exit-2 skip as a failure | Verifier |
| 12 | `test_the_staff_projection_does_not_expose_a_phone_number` asserted "no substring `08`" and failed randomly when a generated UUID contained `08` | Backend test |

Items 6–7 were found by the adversarial harness on its first run, which is what
the harness exists for. Item 12 explains a one-off `staff assignment` failure
observed mid-verification; it was a flaky test, not a product defect, and it is
now matched by phone SHAPE with its own guard pinned in both directions.

## Sanitisation

These outputs were sanitised before commit: ANSI escape sequences were stripped
so the files are readable as text. No other transformation was applied — no
truncation, no reordering, no re-wording of a result.

Every datum appearing in these outputs is fictional: generated UUIDs, `contoh
.invalid` and `contoh-fiktif.id` hosts, and sequential placeholder phone numbers
that cannot reach a subscriber. This repository is PUBLIC and a fixture copied
from reality is a permanent disclosure (Rule 23, Rule 45).
