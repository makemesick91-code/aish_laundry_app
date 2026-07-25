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
%%CAPTURED_OUTPUT%%
```

## 2. What this output does and does not establish

**Establishes.** Every gate named above executed and returned the status shown. `SKIP 0` means no gate
was passed over: both transitional skips from the DEC-0039 governance transition — the tracking
backend suite and the portal/operator UI — are now mandatory gates that ran and passed.

**Does not establish.**

- That Step 7 is `GO`. It is `IN PROGRESS`; `GO` is conferred by the repository owner after merge and
  is never self-declared by an agent (Rule 01).
- That any message was delivered to any real customer. No live WhatsApp send occurred; the official
  adapter is unverified against a live provider because credentials do not exist in this repository
  (see [`RUNTIME-VERIFICATION.md`](RUNTIME-VERIFICATION.md) §6).
- That anything is deployed. Deployment remains `ABSENT`.
- That an accessibility audit or a performance measurement was performed. Neither was.
- That the two open questions are answered. OQ-014 and OQ-018 await an owner decision.

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
