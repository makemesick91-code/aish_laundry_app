# Step 3 Corrective — Post-Merge Closure

**Merge commit:** `0f065a330e085228aaeed086f620d8752291e0af`
**Pull request:** #19 — `fix/step-03-auth-runtime-wiring` → `main`, state **MERGED**, merged 21 July 2026 15:32 UTC
**Merged head:** `6212877260bb85f68a3ca037048d88432a8034e5` (11/11 authoritative CI green)
**Evidence anchor:** `a7350ba97feb59249801772b05271a6e74da0b86`
**Merge method:** merge commit, matching every prior merge on `main`. No squash, no rebase — a rewrite
would have invalidated the SHA mapping the evidence is bound to.

## Accepted evidence indirection

This is recorded as **accepted evidence indirection, not full five-way equality.** A file cannot contain
the hash of the commit that introduces it, so the evidence pack is bound to the final CODE commit and
the head that CI verified adds documentation only:

```
$ git diff --name-only a7350ba 6212877
evidence/step-03-corrective-auth-runtime/README.md
```

Nothing under `apps/`, `packages/`, `backend/` or `scripts/`. Local HEAD, remote branch, PR head and
the CI-tested SHA were all exactly `6212877…` at merge time.

## Post-merge verification, from a FRESH CLONE at the merge SHA

A new clone of the remote, checked out at `0f065a3`, pristine tree, dependencies resolved from scratch.

```
=== A. HOST GATES ===
dart format                    Formatted 116 files (0 changed)
dart analyze                   No issues found!
packages/core                  +18   packages/domain          +12
packages/networking            +34   packages/auth            +34 ~8
packages/local_storage         +11   packages/design_system   +31
packages/observability         +13   apps/ops_android         +34
apps/customer_android          +26   apps/admin_web           +26
                                            all: All tests passed!

=== B. GOVERNANCE VALIDATORS ===
validate-runtime-scope   PASS      validate-status     PASS
validate-decisions       PASS      validate-secrets    PASS

=== C. THE THREE PRODUCTION COMPOSITIONS ===
ops_android  +6 passed    customer_android  +6 passed    admin_web  +6 passed
   — each resolves a CONCRETE BackendAuthService, no throwing stub anywhere

=== D. GO TAGS ===
GO tags present : 4
tag object      : 8b37230ed8df8da343a1546fd949d8a41329fbdf
peels to        : 0e2554338812b05eba8411afeb099212b05f9761

=== E. ON-DEVICE, real Android 34 emulator + real Keystore + real backend ===
ops_android       14/14 All tests passed!
customer_android  14/14 All tests passed!

=== F. HOST E2E vs RUNNING BACKEND ===
8/8 All tests passed!

=== G. CONSOLE WEB IN REAL CHROME (build from the clean checkout) ===
static scan: no browser token storage, credential assignment, or dev value found
booted-in-chrome=yes
storage-after-boot={"local":[],"session":[]}
login-status=200
authenticated-me=200|no-token-in-body
storage-after-auth={"local":[],"session":[]}
script-readable-cookie-names=["XSRF-TOKEN"]
api-calls=["401 /api/v1/auth/me","200 /api/v1/auth/login","200 /api/v1/auth/me"]
unimplemented-provider-errors=0
```

## One thing that failed first, and why it is not a defect

Section F initially failed four tests. The cause was not a regression: the two on-device suites had
just performed roughly thirty sign-ins back to back, and the backend's authentication rate limiter had
engaged. A direct probe confirmed it:

```
$ curl -X POST .../auth/login   ->  http=429   error code: RATE_LIMITED
```

That is **Rule 38 hard rule 6 working as designed**, and the client behaved correctly throughout:
`rateLimited` maps to a TRANSIENT consequence, so no credential was deleted and no session was
terminated — exactly the fail-safe path. After clearing the development limiter, section F passed 8/8.

This is recorded rather than quietly re-run, because "the suite passed on the second attempt" is the
shape of a report that hides a flake. It was not a flake; it was a security control firing, and the
distinction is the point.

## What is verified, and what is still not

Verified on real platforms: unauthenticated startup, sign-in, invalid credentials, the token genuinely
landing in the Android Keystore, authenticated startup, tenant context reaching a real tenant-scoped
endpoint via `X-Tenant-Id`, a foreign tenant refused by the real server, logout clearing the platform,
an unauthorized session cleared, a transient network failure NOT deleting a good credential, bounded
startup when the keystore never answers, and the Console Web browser-storage prohibition.

Still NOT verified, and not claimed:

- **No physical device.** An x86_64 Android 34 emulator with KVM. A physical handset was attached to
  this workstation during the run and was deliberately left untouched — nothing was installed on it and
  no port forward was created to it. Hardware-backed key attestation remains uncovered.
- **No cross-origin CORS preflight.** Console Web and the API were served from one origin.
- **No widget-level UI drive on Console Web.**
- **No restart proof for `customer_android`** — instrumentation scaffolding exists on `ops_android`
  only. Its 14/14 on-device run is real.
- **No deployment exists.** Nothing here authorises one.

## Governance state after merge

- Step 3 remains **`GO WITH ACCEPTED DEVIATION`**. Its tag was not created, moved, or replaced.
- Exactly **four** historical GO tags remain, unchanged.
- **No Step 4 GO tag exists** and none was created.
- Step 4 remains `IN PROGRESS`; PR #18 is unmerged.
- The corrective classification is now formalised as
  [`DEC-0032`](../../docs/decisions/DEC-0032-step-03-post-go-corrective-auth-runtime-wiring.md),
  carried on the Step 4 branch so that it lands before PR #18 merges. Master Source moved 1.4.1 → 1.4.2
  (PATCH) solely for the §31 index entry the decisions validator requires, with the checksum
  regenerated by tool from the final bytes.
- An unrelated guard-scope issue is recorded separately in
  [`GOVERNANCE-FOLLOW-UP.md`](GOVERNANCE-FOLLOW-UP.md). The destructive-operations guard was not
  edited, weakened, or bypassed at any point.
