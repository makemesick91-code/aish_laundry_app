# Step 7 — Canonical Verifier Output (captured)

**Step:** 7 — Customer Tracking and WhatsApp
**Status:** `IN PROGRESS` — **`GO` is NOT conferred and is the repository owner's to give** (Rule 01)
**Command:** `bash scripts/verify-step-07.sh`
**Environment:** Linux; PHP 8.4 + Composer; PostgreSQL 18.4 and Redis 8.2.7 loopback-bound
development services; Flutter/Dart from the pinned SDK; working tree clean.

---

## 0. How to read the SHA below

The captured run is bound to the exact 40-character commit it executed against. **That commit is the
PARENT of the commit that adds this file**, because a run cannot capture its own output into the tree
it is measuring. This is the same pattern used for the Step 3 post-tag evidence and the Step 4/5/6
closure packs: the evidence names the SHA it measured, and adding the evidence advances the SHA by one
commit.

The candidate SHA offered for review — and the one CI runs against — is recorded in the pull request
and is the tip of `feature/step-07-runtime-customer-tracking-whatsapp`. Evidence produced at one SHA
never carries over to another (Rule 01, DEC-0013); what carries over is the *tree*, and the only
difference between the measured commit and the candidate is this document plus the pull-request body.

## 1. The captured run

Recorded verbatim below, with ANSI colour stripped and nothing reworded, reordered, or truncated in a
way that changes its meaning (Rule 01, evidence rule 5).

```text
========================================================================
STEP 7 CANONICAL VERIFICATION — CUSTOMER TRACKING AND WHATSAPP
========================================================================
  commit    : 45573c33a9572565dfdcf4efe21b0741a40e9f96
  timestamp : 2026-07-25T16:56:48Z (UTC)
  authorised: canonical roadmap (Master Source §24), guard transition DEC-0039

== 1. Step 0-6 regression (delegated, not restated) ==
  PASS  Step 0-6 regression (verify-step-06.sh)

== 2. Step 7 authorization and governance ==
  PASS  DEC-0039 present and ACCEPTED
  PASS  DEC-0040 present and ACCEPTED
  PASS  DEC-0041 present and ACCEPTED
  PASS  Master Source header matches the pinned canonical version
  PASS  MASTER_SOURCE checksum matches
  PASS  Rule 50 (Step 4 status) present
  PASS  Step 7 requirement matrix present
  PASS  Step 7 evidence pack present
  PASS  governance validator suite
  PASS  runtime scope guard (classify)
  PASS  DEC-0039 label audit
  PASS  DEC-0041 portal-stack boundary audit
  PASS  DEC-0037 label audit (step-aware)
  PASS  DEC-0035 label audit (step-aware)
  PASS  Step 7 validator adversarial harness
  PASS  verify-step-07 adversarial harness
  PASS  no float in any money path

== 3. Step 7 backend runtime (customer tracking + notification) ==
  PASS  Step 7 Tracking module present
  PASS  Step 7 Notification module present
  PASS  Step 7 public portal views present
  PASS  Step 7 backend test suites present
  PASS  live schema within Step 7 scope
  PASS  Step 7 tracking backend suite (token lifecycle, portal projection, public API, OTP, isolation, RBAC)
  PASS  Step 7 notification backend suite (outbox, dedup, consent, quiet hours, provider abstraction, FR-099 decoupling)

== 4. Step 7 public tracking portal and operator surface ==
  PASS  operator tracking UI present
  PASS  operator tracking UI test present
  PASS  operator tracking/notification UI widget tests

== 5. Public repository safety and working tree ==
  PASS  secret scan
  PASS  public repository safety (canonical scan)
  PASS  working tree clean

========================================================================
STEP 7 VERIFICATION SUMMARY
========================================================================
  commit : 45573c33a9572565dfdcf4efe21b0741a40e9f96
  PASS 31   FAIL 0   SKIP 0
------------------------------------------------------------------------
```

## 2. What this output does and does not establish

**Establishes.** Every gate named above executed and returned the status shown. `SKIP 0` means no gate
was passed over: both transitional skips from the DEC-0039 governance transition — the tracking
backend suite and the portal/operator UI — are now mandatory gates that ran and passed.

The total moved from `PASS 28` to `PASS 31` because the DEC-0040/DEC-0041 ratification added three
mandatory gates: `DEC-0040 present and ACCEPTED`, `DEC-0041 present and ACCEPTED`, and the
`DEC-0041 portal-stack boundary audit`. The earlier `PASS 28` figure belonged to
`ca3476ae31bf718b78523dc982948c54626413aa` and is **not** re-asserted here — evidence produced at one
SHA never carries over to another (Rule 01, DEC-0013).

**Does not establish.**

- That Step 7 is `GO`. It is `IN PROGRESS`; `GO` is conferred by the repository owner after merge and
  is never self-declared by an agent (Rule 01).
- That any message was delivered to any real customer. No live WhatsApp send occurred; the official
  adapter is unverified against a live provider because credentials do not exist in this repository
  (see [`RUNTIME-VERIFICATION.md`](RUNTIME-VERIFICATION.md) §6).
- That anything is deployed. Deployment remains `ABSENT`.
- That an accessibility audit or a performance measurement was performed. Neither was.
- That live WhatsApp delivery works. `SENT` means a provider accepted a message; no delivery receipt
  is held and none is claimed.

**The two open questions ARE now answered**, and this is the one line in this section that changed
direction. The previous capture said *"OQ-014 and OQ-018 await an owner decision"*; that was true when
written and stopped being true on 26 July 2026, when the repository owner resolved OQ-018 as
[DEC-0040](../../docs/decisions/DEC-0040-oq-018-user-initiated-security-transaction-quiet-hours-exemption.md)
and OQ-014 as
[DEC-0041](../../docs/decisions/DEC-0041-oq-014-laravel-blade-as-the-public-tracking-portal-stack.md).
Both records are ACCEPTED and both are now checked by mandatory gates in the run above. **Resolving an
open question is not conferring `GO`**, and this document asserts no `GO`.

## 3. Governance note on the gates that changed during this sprint

Four forward-boundary checks were failing not because Step 7 was wrong but because they still asserted
that Step 7 could not exist. Each was moved to the Step 8+ band by the mechanism the repository
already uses, and each is recorded rather than quietly adjusted:

| Gate | Why it failed | Fix |
|---|---|---|
| `validate-dec-0030-labels.py` | still forbade `tracking_token`/`public_tracking` | made step-aware (forbidden below Step 7, audited by `validate-dec-0039-labels.py` from Step 7) — the identical mechanism DEC-0039 §11 applied to the 0035 and 0037 audits |
| `verify-step-04.sh` route/endpoint boundary | final band still forbade `tracking`/`whatsapp` | added a `< 7` band preserving the old assertions exactly, plus a Step-7+ band forbidding only Step 8+ tokens |
| `ServiceCatalogSurfaceTest` forward-boundary route test | still listed `tracking`/`whatsapp` | moved to the Step 8+ token set, matching its own documented intent of guarding "the CURRENT forward boundary" |
| `analyze-step-03-relationships.py` public allow-list | the three portal routes are unauthenticated by design and were not allow-listed | added with the reasoning recorded in the source: the token IS the credential, and four tested properties are what make exposure safe |

**None of these is a widening of the scope guard.** The labels and routes were already in the
permitted band of the canonical guard (`validate-runtime-scope.py`) by an accepted decision record
(DEC-0039); what changed is that stale auditors stopped contradicting it. Below Step 7 every one of
them behaves exactly as before, so none can false-pass in an earlier tree. Editing a guard to unblock
work would be a governance breach (Rule 36 hard rule 8); editing a stale auditor to agree with an
accepted record is the opposite, and the distinction is why each change is listed here.
