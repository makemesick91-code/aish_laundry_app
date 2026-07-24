# DEC-0037 — Step 6 Runtime Scope Transition

**ID:** DEC-0037
**Title:** Step 6 Runtime Scope Transition
**Status:** ACCEPTED
**Date:** 24 July 2026

---

## Context

`scripts/validate-runtime-scope.py` refuses, by construction, to let a Step 6 business table exist.
Since DEC-0035 it carries a `STEP6_PLUS_FEATURE_TOKENS` map — the labels owned by Step 6 or later — and
rejects any migration filename, `Schema::create` argument, route path segment, Eloquent model class
name, or module directory whose identifier matches one of those tokens. Among them:

```
"production":      {"production_jobs", "produksi"}
"washing":         {"washing", "pencucian"}
"drying":          {"drying", "pengeringan"}
"finishing":       {"finishing", "penyelesaian"}
"quality control": {"quality_controls", "qc_inspections"}
"rework":          {"reworks", "pengerjaan_ulang"}
```

That is exactly what Step 6 — Production Operations — must build. The guard is not an obstacle to work
around; it is doing precisely the job DEC-0024 gave it, and it keeps doing that job until a decision
record moves the boundary.

**Rule 36, hard rule 8 governs how that boundary moves:** the scope guard "may only be narrowed, never
silently widened. Widening the approved runtime roots, adding a runtime language, permitting a further
business-feature label, or authorizing deployment requires its own decision record naming what it
supersedes." DEC-0035's supersession policy says the same, and adds: "**Editing
`STEP6_PLUS_FEATURE_TOKENS` to unblock work is a governance breach, not a fix.** The step that owns a
label must itself have been authorised first." This record is that decision record.

Two facts bound what it may do.

1. **The canonical roadmap already authorises Step 6.** Master Source §24, `docs/ROADMAP.md`, and
   `docs/STATUS.md` all record **Step 6 — Production Operations**. This record does not grant that
   authorisation and does not redefine it; it is the mechanical guard transition that lets the
   already-canonical Step 6 runtime pass the `classify` check. Step 6 remains, unchanged, "Production
   Operations."

2. **DEC-0035 permitted Step 5 — and only Step 5.** It split the former single map by delivering step
   and permitted exactly the seven POS/order/payment labels, leaving the production, tracking, pickup,
   delivery, reminder, finance, loyalty, and subscription labels owned by Step 6 or later, "forbidden
   unconditionally at Step 5."

The question this record answers is therefore narrow: which of the guard's forbidden feature labels stop
being forbidden now that the canonical current step reaches 6, and which stay forbidden.

## Options considered

**Option 1 — remove `STEP6_PLUS_FEATURE_TOKENS` entirely now that Step 6 is under way.**
Rejected outright, for the same reason DEC-0030 and DEC-0035 rejected removing their predecessors. The
map does not mean "Step 6 features"; it means "features belonging to Step 6 **or later**". Seventeen of
its labels are Step 7+ scope — the tracking portal and token, WhatsApp and notification provider,
pickup, delivery, courier routing, proof of delivery, courier settlement, unclaimed laundry, the
reminder ladder, storage fees, receivables, finance reports, loyalty, commercial membership, and
subscription billing. Deleting the map to unblock six labels would silently unblock all of them at
exactly the moment the repository first has production runtime for forward leak to be tempting.

**Option 2 — add per-file suppressions or an allowlist of specific Step 6 paths.**
Rejected. Suppressions accumulate, are granted under deadline pressure, and are invisible in aggregate;
and they make the guard's answer depend on where a file sits rather than on what it is — the property
that made naive prose matching unusable in the first place.

**Option 3 — split the map again by delivering step and gate it on the canonical current step.**
**Adopted.** The six labels Step 6 delivers move into a `STEP6_FEATURE_TOKENS` set that is permitted once
the canonical current step reaches 6. Everything else moves into `STEP7_PLUS_FEATURE_TOKENS` and stays
forbidden. The boundary is derived from `_common.CANONICAL_CURRENT_STEP`, so it advances only when a step
actually starts under its own authorisation — never as a side effect of an edit to the guard. This is the
identical mechanism DEC-0030 used for Step 4 and DEC-0035 used for Step 5, and using it again rather than
inventing a new one is deliberate.

## Decision

1. **Exactly six feature labels become permitted, effective from canonical step 6:** `production`,
   `washing`, `drying`, `finishing`, `quality control`, and `rework`. Each traces to a Step 6
   requirement: the canonical status set including `WASHING`/`DRYING`/`FINISHING`/`QUALITY_CONTROL`/
   `REWORK` and transition validity (FR-071, FR-072), stage progress recording and batch/item handling
   (FR-073, FR-074, FR-075), the first immutable `READY_FOR_PICKUP` transition and its aging-anchor
   immunity to rework (FR-076, FR-077), the `ISSUE` status and server-authoritative timestamps (FR-078,
   FR-080), offline production recording (FR-079), the server-side quality-control gate and defect
   evidence (FR-081, FR-083), and rework with reason, history, and reporting input (FR-082, FR-084,
   FR-085). No token in any of the six sets is changed.

2. **Every other label remains forbidden and is moved into `STEP7_PLUS_FEATURE_TOKENS`**, with no change
   to its tokens: tracking portal, tracking token, WhatsApp, notification provider, pickup, delivery,
   courier routing, proof of delivery, courier settlement, unclaimed laundry, reminder ladder, storage
   fee, receivables, finance reports, loyalty, commercial membership, and subscription billing.

3. **`rework` becomes permitted while `delivery` begins the retained band, and the distinction is
   deliberate.** Rework re-processes an order inside the outlet after a failed quality-control
   inspection (FR-082). A **failed delivery** that returns the laundry to the outlet is Step 8 and stays
   rejected. A `reworks` or `pengerjaan_ulang` table is now Step 6 runtime; a `deliveries` or
   `delivery_requests` table is not.

4. **The first `READY_FOR_PICKUP` transition is a Step 6 production fact, and the aging computation over
   it is not.** FR-076 and FR-077 make the first-ready timestamp Step 6's obligation: recorded once,
   immutable, immune to a later return to `REWORK`. Computing H+1/H+3/H+7/H+14 from that timestamp, the
   reminder ladder, and the unclaimed-laundry dashboard are Step 9 and stay forbidden — `reminders`,
   `reminder_stages`, `unclaimed_laundry`, and `storage_fees` remain in the retained band. Recording the
   anchor is Step 6; consuming it is Step 9.

5. **The boundary is derived, never hardcoded.** The guard reads `_common.CANONICAL_CURRENT_STEP`, raised
   from 5 to 6 in this same change. Below 6 the Step 6 labels remain forbidden exactly as before, so this
   record cannot retroactively permit anything in a Step 0–5 tree, and Step 7's own labels cannot be
   unblocked by editing this guard — they need Step 7's own authorisation and its own record.

6. **Nothing else about the guard is relaxed.** Approved runtime roots are unchanged. No runtime language
   is added. Deployment-artifact detection, credential detection, personal-data detection, symlink-escape
   detection, and status-claim honesty checks are untouched. **Deployment remains `ABSENT` and is not
   authorized by any part of this record.**

7. **Structural detection remains structural.** Renaming a Step 7+ feature to evade token matching is the
   same violation as building it under its plain name (Rule 36, hard rule 4), and permitting six labels
   does not soften that.

8. **The required CI status check context remains exactly `classify`** and is not renamed (Rule 36, hard
   rule 5).

9. **A permitted label is not an implemented feature.** `production_jobs` ceasing to be a forbidden token
   means a `production_jobs` table may now legally exist. It says nothing about whether one does, whether
   it is tenant-scoped, whether the status lifecycle is enforced server-side, whether the first-ready
   timestamp is immutable, or whether any test passes. `classify` reports scope classification only and
   executes no application test (Rule 36, hard rule 6). The tenant-isolation and financial-integrity hard
   gates apply in full to every permitted label (Rule 04, Rule 48); production must not falsify the
   integer-Rupiah price snapshot Step 5 captured (Rule 04, invariant 11).

10. **The residual audits move with the boundary.** `validate-dec-0035-labels.py` is made
    canonical-step-aware so it no longer treats the six now-permitted Step 6 labels as forbidden from
    Step 6 (a false failure on authorised runtime), and a new `validate-dec-0037-labels.py` audits the
    Step 6 residual: each permitted label still traces to a PRD requirement (FR-071 … FR-085), and every
    Step 7+ label stays structurally absent. No token protection is removed — it moves to the
    step-appropriate auditor.

## Consequences

Step 6 can build the production-operations foundation it was authorised to build, and the forward-leak
guard survives intact for Steps 7 through 14.

### Positive consequences

- The guard's strength against Step 7+ leakage is unchanged: seventeen labels remain forbidden, now
  labelled by the step that owns them rather than lumped under a name that stopped being accurate.
- The permitted set is enumerable and reviewable in one place, rather than scattered across suppressions.
- Tying the boundary to `CANONICAL_CURRENT_STEP` means Step 7's own labels cannot be unblocked by editing
  this guard — they need Step 7's own authorization and its own record, exactly as Step 6 needed this one
  and Step 5 needed DEC-0035.

### Negative consequences / trade-offs

- Six labels of outright token protection are genuinely given up. If a Step 7 tracking table were named
  `production_jobs`, the token check would no longer catch it — the tenant-isolation, financial-integrity,
  and review gates would have to. This record does not pretend that residual risk is zero; it is stated
  and accepted, and `validate-dec-0037-labels.py` narrows it by asserting no Step 7+ token appears in any
  structural position.
- `rework` permitted while `delivery` is forbidden, and the first-ready anchor permitted while the aging
  ladder is forbidden, are fine distinctions a future contributor could get wrong in either direction.
  They are written down here and covered by the residual audit, but they are sharp edges — the same shape
  as `receipt` vs `production` was under DEC-0035.
- The guard is now conditional in four bands rather than three. A conditional guard is one a reader can
  misread; the residual auditors and the adversarial fixtures are the compensating control, and they are
  load-bearing rather than supplementary.

## Verification

Verified on `feature/step-06-production-operations`, branched from the post-Step-5 canonical `main` at
`9c23eca41f45963b61a04f936e69bf9b71997552`:

- `scripts/validate-runtime-scope.py` carried `STEP6_PLUS_FEATURE_TOKENS` with the production/QC/rework
  and Step 7+ labels, and rejected every Step 6 table before this record. After the split it classifies
  the current tree `STEP_3_RUNTIME_FOUNDATION_WITHIN_SCOPE` (5/5 checks) with `CANONICAL_CURRENT_STEP = 6`
  and reports the forbidden band as "Step 7+".
- Rule 36 hard rule 8 and DEC-0035's supersession policy were read directly and are quoted above.
- FR-071 … FR-085 were read from `docs/product/PRODUCT_REQUIREMENTS.md` and are the sole basis for which
  six labels move; `validate-dec-0037-labels.py` re-asserts that tracing on every run.
- `validate-dec-0035-labels.py` (made step-aware) and `validate-dec-0037-labels.py` both pass at
  `CANONICAL_CURRENT_STEP = 6`, and `validate-dec-0030-labels.py` continues to pass.

The adversarial-fixture result demonstrating that each of the seventeen retained Step 7+ labels is still
rejected, and that the six permitted labels are accepted, is recorded in the Step 6 evidence pack under
`evidence/step-06/` bound to the exact commit it was produced from (Rule 01, DEC-0013). This record
quotes no result it did not produce.

## Requirement references

FR-071 … FR-085 (the canonical status set and transition validity, stage progress recording, batch and
item-level handling, the first immutable `READY_FOR_PICKUP` timestamp and its rework immunity, the
`ISSUE` status, offline production recording, server-authoritative timestamps, the quality-control gate,
defect evidence, and rework with reason, history, and reporting input). No requirement is created,
changed, or withdrawn; this record only permits the runtime that already-existing requirements call for.

## Threat references

The threat this record manages is scope leakage: Step 7+ business functionality entering the tree under
Step 6's authorization, either by plain naming or by renaming to evade detection. Mitigated by retaining
all seventeen Step 7+ labels, by deriving the boundary from `CANONICAL_CURRENT_STEP` rather than from an
edit to the guard, and by adversarial fixtures over both the permitted and the retained sets. Residual
risk is stated in the trade-offs above and is accepted, not eliminated.

## Rule references

- Rule 36 — runtime architecture and scope; hard rule 4 (structural detection), hard rule 5 (`classify`
  never renamed), hard rule 6 (presence is not correctness), hard rule 8 (widening requires a decision
  record naming what it supersedes).
- Rule 42 — Step 4+ backend scope; Step 7+ features remain `NOT IMPLEMENTED`.
- Rule 02 / Rule 39 / Rule 48 — tenant isolation; permitting a table never relaxes tenant scoping.
- Rule 04 / Rule 18 — financial integrity; production must not mutate the historical price snapshot, and
  money stays integer Rupiah.
- Rule 19 — the canonical order status lifecycle; enforcement is Step 6's obligation for the production
  statuses.
- Rule 10 — unclaimed laundry; the first-ready timestamp is recorded here and consumed in Step 9.
- Rule 47 — adversarial validator testing before a gate is relied upon.
- Rule 49 / Rule 50 — step status; the canonical roadmap authorises Step 6 and nothing later.

## Supersession policy

This record supersedes **only** the Step 6 portion of DEC-0035's runtime scope split — the six labels it
moves from `STEP6_PLUS_FEATURE_TOKENS` to `STEP6_FEATURE_TOKENS`. Every other part of DEC-0035, DEC-0030,
and DEC-0024 remains in force unchanged: the seven Step 5 labels stay permitted, the four Step 4 labels
stay permitted, the approved runtime roots are untouched, the guard-versioning split between
`validate-runtime-scope.py` and `validate-no-runtime.py` stands, the Step 0–2 guard boundary remains
immutable, and the `classify` check name is unchanged.

Permitting any further feature label requires a new accepted decision record naming this one, and the
step that owns the label must itself have been authorised first. **Editing `STEP7_PLUS_FEATURE_TOKENS` to
unblock work is a governance breach, not a fix** (Rule 00, Rule 23). Narrowing the guard — moving a label
back to forbidden, or adding tokens to an existing label — needs no record, exactly as Rule 36 hard rule
8 provides.

This record does not renumber, replace, reinterpret, or expand the canonical roadmap. Step 6 remains
"Production Operations"; DEC-0037 is only the auditable governance mechanism that transitions the
previously forbidden production/QC/rework runtime tokens into an explicitly Step-6-gated, testable state.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §6 — architecture and the locked backend stack.
- §16 — financial integrity.
- §19 — the canonical order status lifecycle.
- §24 — Roadmap; the Step 6 entry and the roadmap lock.
- §31 — Decision records.
- §32 — Changelog.
