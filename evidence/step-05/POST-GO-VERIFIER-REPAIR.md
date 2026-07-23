# Step 5 — Post-GO Verifier-Tooling Defect Repair

**Classification: corrective (verifier tooling only).** This pack records a defect
found in the **local exact-SHA verifier chain** after Step 5 `GO`, its diagnosis,
its repair, and the clean re-verification. **It changes no product code, no
Master Source, no runtime-scope guard, no canonical status, and does not touch the
immutable Step 5 `GO` tag.** The Step 5 `GO` decision is unaffected — see §5.

- Repair commit: `bde860d8abda603b03d1286f8cdbf5be0f000e6d`
- Branch: `fix/step-05-post-go-verifier-repair`
- Canonical `main` at diagnosis: `99e990875f3a1b362a1c4ce695c7106707658160`
- Step 5 GO tag (unchanged): `aish-laundry-step-05-pos-order-payment-foundation-v1.0.0-go`
  → object `fd85f93ca041b95985eeea2a8e300b88a76f4728` → peels to merge commit
  `f0524b3a07f5306ec8b5c0584f94f865ec9f9346`
- Timestamp: 23 July 2026, Asia/Jakarta

---

## 1. The reported failure

A post-GO run of `scripts/verify-step-05.sh` on canonical `main`
(`99e9908`) reported **PASS 19 / FAIL 3 / SKIP 0**. The displayed
`Verification exit code: 0` was **not** the verifier's exit code — it was the exit
code of a *subsequent* `sha256sum -c` command in the same shell line. Treated
correctly, the verifier **FAILED**. The three failing gates were:

1. Step 0–4 regression through `verify-step-04.sh`;
2. `verify-step-05.sh` still expecting Master Source version `1.4.6`;
3. live schema within Step 5 scope.

## 2. Diagnosis — three verifier-tooling defects, not product defects

All three were introduced when the canonical step advanced 4→5 (DEC-0035) and Step
5 reached `GO`, and none is a product, guard, CI, or schema defect. Crucially,
**CI never runs the `verify-step-0X.sh` chain** — CI runs `validate-governance.sh`
and the `classify` / `runtime-foundation` / `authentication-rbac` /
`tenant-isolation` workflows — so this local-verifier staleness was invisible to
the 15/15 merge-SHA CI that Step 5 `GO` actually rests on.

### Defect #2 — stale hardcoded version pin (deterministic)
`verify-step-05.sh:81` asserted the literal `Document version: 1.4.6`. DEC-0036
advanced the Master Source to `1.4.7` and the GO closure to `1.4.8`, so the gate
became a **false FAIL**. (At the GO-tag commit `f0524b3` the Master Source was
already `1.4.7`, so this gate failed there too — see §5.)

### Defect #1 — two Step-3-era adversarial harnesses left non-step-aware
`verify-step-04.sh` → `verify-step-03.sh` failed on exactly two gates:

- `test-step-03-validators.sh` — 12 of its "Step 4+ must be rejected" mutations
  (M6/7/8/9/14/15/16/32/33/34/35) fired on **Step 5 features** (POS, payment,
  order, QRIS, receipt/nota, kasir). DEC-0035 **authorised** those labels, so the
  runtime-scope guard **correctly** stays green; the harness's expectation was
  stale. The forward-leak fixture M29 still claimed "Step 5 IN PROGRESS", which is
  no longer a leak now that Step 5 is `GO`.
- `test-status-advancement.sh` — 5 fixtures (M3/M4/M16/M20/M23) `sed`-matched a
  status value that has since advanced (`STEP_05_STATUS=PLANNED`,
  `STEP_04_STATUS=IN_PROGRESS`). The `sed` silently matched nothing, so the
  validator correctly passed the **unmutated** tree, reported as
  "validator PASSED a mutated tree".

The Step 5 implementation made `test-step-04-validators.sh` step-aware and added
`test-step-05-validators.sh`, but **missed** these two Step-3 harnesses.

### Defect #3 — unguarded live-schema gate (environment-fragile)
`verify-step-05.sh:96` ran the PostgreSQL schema-scope check **unguarded**. When
the dev DB is unreachable it hard-**FAILs** — reading like a schema violation —
where `verify-step-03.sh` guards the equivalent gate behind `check-dev-services`
and reports a visible **SKIP**. On a DB-up machine the gate passes; the fragility
is the false FAIL when the DB is down.

## 3. The repair (commit `bde860d`)

Three files, verifier tooling only:

- **`scripts/verify-step-05.sh`**
  - version gate now **derives** the expected version from the single authoritative
    pin (`validate-master-source.py` `VERSION`) and asserts the Master Source header
    matches it — it can never go stale again;
  - the two DB-dependent gates (live schema, Step 5 backend suite) are **guarded**
    behind `check-dev-services`: a visible SKIP when the dev DB is down, a real gate
    when it is up (a gate that could not run has verified nothing — Rule 01).
- **`scripts/test-step-03-validators.sh`** — made step-aware via a `red_step5`
  helper (real reject while step < 5; a visible, uncounted "superseded by DEC-0035 —
  covered in test-step-05-validators.sh" skip at step 5), mirroring
  `reject_step5_token` in `test-step-04-validators.sh`; forward-leak fixtures moved
  to Step 6. **Step 6+ rejection coverage is fully retained** (M10–13, M36, M37,
  M30).
- **`scripts/test-status-advancement.sh`** — re-anchored the 5 stale fixtures:
  forward-leak Step 5 → Step 6; Step 4 revert now `GO → PLANNED` (caught as a
  human/machine disagreement).

**No adversarial coverage was removed.** The Step 5 accept/reject coverage lives in
`test-step-05-validators.sh` (12/12), the retained harnesses still reject every
Step 6+ leak, and the boundary simply moved forward to match the guard — the exact
DEC-0030/DEC-0035 pattern already used for `test-step-04-validators.sh`.

## 4. Clean re-verification (exact SHA `bde860d`, dev DB up, Flutter on PATH)

`bash scripts/verify-step-05.sh` → **exit 0**, captured verbatim in
[`verify-step-05-post-repair.txt`](verify-step-05-post-repair.txt):

```
STEP 5 VERIFICATION SUMMARY
  commit : bde860d8abda603b03d1286f8cdbf5be0f000e6d
  PASS 22   FAIL 0   SKIP 0
```

Both repaired harnesses, re-run standalone, byte-identical before/after:

- `test-step-03-validators.sh` — **36/36 expectations met, 0 failed**
- `test-status-advancement.sh` — **30/30 expectations met, 0 failed**

The three previously-failing gates now read:
`PASS  Step 0-4 regression (verify-step-04.sh)` ·
`PASS  Master Source header matches the pinned canonical version` ·
`PASS  live schema within Step 5 scope`.

## 5. Why the Step 5 `GO` is unaffected

The Step 5 `GO` tag peels to the **merge commit** `f0524b3`, whose **15/15
authoritative CI checks** were green (`validate-governance`, `classify`,
`runtime-foundation` with the full Flutter build+test, `authentication-rbac`,
`tenant-isolation`, `secret-scan`, `Documentation / links`, …), and the real Step
5 backend and Flutter suites pass. **None of those CI workflows invokes
`verify-step-0X.sh`.** The defects repaired here were in the **local exact-SHA
verifier scripts**, which false-FAILed on tooling staleness, not on any product,
guard, schema, or CI regression. The immutable `GO` tag is **not moved, deleted,
recreated, or retargeted**; the canonical status remains `GO`; the correction to
the earlier "exit 0" reading is recorded openly in
[`GO-CLOSURE.md`](GO-CLOSURE.md) §7 (Rule 01: correct a claim visibly, never
silently).

## 6. Scope discipline

This is a closure-verification repair. It is **not** a new product step, **not**
Step 6 authorization, and **not** a Master Source change (no version bump, no
checksum change, no decision record — nothing product-defining changed). It edits
only verification tooling to track the already-canonical state, which Rule
12/autonomous-execution permits and Rule 11 routes through a pull request rather
than a direct push to `main`.
