# DEC-0039 — Step 7 Runtime Scope Transition

**ID:** DEC-0039
**Title:** Step 7 Runtime Scope Transition
**Status:** ACCEPTED
**Date:** 25 July 2026

---

## Context

`scripts/validate-runtime-scope.py` refuses, by construction, to let a Step 7 business table exist.
Since DEC-0037 it carries a `STEP7_PLUS_FEATURE_TOKENS` map — the labels owned by Step 7 or later — and
rejects any migration filename, `Schema::create` argument, route path segment, Eloquent model class
name, or module directory whose identifier matches one of those tokens. Among them:

```
"tracking portal":       {"tracking_portal", "public_tracking"}
"tracking token":        {"tracking_tokens"}
"WhatsApp":              {"whatsapp", "wa_provider", "whatsapp_messages"}
"notification provider": {"notification_providers", "notification_dispatch"}
```

That is exactly what Step 7 — Customer Tracking and WhatsApp — must build. The guard is not an obstacle
to work around; it is doing precisely the job DEC-0024 gave it, and it keeps doing that job until a
decision record moves the boundary.

**Rule 36, hard rule 8 governs how that boundary moves:** the scope guard "may only be narrowed, never
silently widened. Widening the approved runtime roots, adding a runtime language, permitting a further
business-feature label, or authorizing deployment requires its own decision record naming what it
supersedes." DEC-0037's supersession policy says the same, and adds: "**Editing
`STEP7_PLUS_FEATURE_TOKENS` to unblock work is a governance breach, not a fix.** The step that owns a
label must itself have been authorised first." This record is that decision record.

Two facts bound what it may do.

1. **The canonical roadmap already authorises Step 7.** Master Source §24, `docs/ROADMAP.md`, and
   `docs/STATUS.md` all record **Step 7 — Customer Tracking and WhatsApp**. This record does not grant
   that authorisation and does not redefine it; it is the mechanical guard transition that lets the
   already-canonical Step 7 runtime pass the `classify` check. Step 7 remains, unchanged, "Customer
   Tracking and WhatsApp."

2. **DEC-0037 permitted Step 6 — and only Step 6.** It split the former single map by delivering step
   and permitted exactly the six production/QC/rework labels, leaving the tracking, WhatsApp,
   notification, pickup, delivery, courier, reminder, finance, loyalty, and subscription labels owned by
   Step 7 or later, "forbidden unconditionally at Step 6."

The question this record answers is therefore narrow: which of the guard's forbidden feature labels stop
being forbidden now that the canonical current step reaches 7, and which stay forbidden.

## Options considered

**Option 1 — remove `STEP7_PLUS_FEATURE_TOKENS` entirely now that Step 7 is under way.**
Rejected outright, for the same reason DEC-0030, DEC-0035, and DEC-0037 rejected removing their
predecessors. The map does not mean "Step 7 features"; it means "features belonging to Step 7 **or
later**". Thirteen of its labels are Step 8+ scope — pickup, delivery, courier routing, proof of
delivery, courier settlement, unclaimed laundry, the reminder ladder, storage fees, receivables, finance
reports, loyalty, commercial membership, and subscription billing. Deleting the map to unblock four
labels would silently unblock all of them at exactly the moment the repository first has customer-facing
runtime for forward leak to be tempting.

**Option 2 — add per-file suppressions or an allowlist of specific Step 7 paths.**
Rejected. Suppressions accumulate, are granted under deadline pressure, and are invisible in aggregate;
and they make the guard's answer depend on where a file sits rather than on what it is — the property
that made naive prose matching unusable in the first place.

**Option 3 — split the map again by delivering step and gate it on the canonical current step.**
**Adopted.** The four labels Step 7 delivers move into a `STEP7_FEATURE_TOKENS` set that is permitted
once the canonical current step reaches 7. Everything else moves into `STEP8_PLUS_FEATURE_TOKENS` and
stays forbidden. The boundary is derived from `_common.CANONICAL_CURRENT_STEP`, so it advances only when
a step actually starts under its own authorisation — never as a side effect of an edit to the guard.
This is the identical mechanism DEC-0030 used for Step 4, DEC-0035 used for Step 5, and DEC-0037 used for
Step 6, and using it again rather than inventing a new one is deliberate.

## Decision

1. **Exactly four feature labels become permitted, effective from canonical step 7:** `tracking portal`,
   `tracking token`, `WhatsApp`, and `notification provider`. Each traces to a Step 7 requirement:
   high-entropy hashed tracking-token issuance and its independence from the order number (FR-086,
   FR-087), token revocation and expiry (FR-088), the public portal content set, exclusions, OTP-gated
   sensitive actions, and `noindex` (FR-089 … FR-092), the notification provider abstraction and official
   WhatsApp automated path with a manual deep-link fallback (FR-093, FR-094, FR-095), transactional vs
   marketing separation with consent (FR-096), quiet-hours enforcement (FR-097), message deduplication
   (FR-098), and messaging decoupled from order state (FR-099). No token in any of the four sets is
   changed.

2. **Every other label remains forbidden and is moved into `STEP8_PLUS_FEATURE_TOKENS`**, with no change
   to its tokens: pickup, delivery, courier routing, proof of delivery, courier settlement, unclaimed
   laundry, reminder ladder, storage fee, receivables, finance reports, loyalty, commercial membership,
   and subscription billing.

3. **The tracking token becomes permitted while the external-courier guest link stays forbidden, and the
   distinction is deliberate.** FR-086 … FR-088 make the customer's public tracking token Step 7 runtime.
   The external-ojek guest link (Rule 09, Step 8) is a different credential granting a courier scoped
   access to one assignment; it is not a `tracking_tokens` row and stays rejected — `pickups`,
   `deliveries`, `delivery_proofs`, and `courier_settlements` remain in the retained band.

4. **Notifying a customer over WhatsApp is Step 7; deciding WHEN to chase unclaimed laundry is Step 9.**
   FR-093 … FR-099 make the provider-abstracted notification subsystem — templates, consent, quiet
   hours, dedup, bounded retry, decoupled from order state — Step 7's obligation. The H+1/H+3/H+7/H+14
   reminder LADDER, its aging computation over the first-ready timestamp, its follow-up task, and the
   unclaimed-laundry dashboard are Step 9 and stay forbidden — `reminders`, `reminder_stages`,
   `reminder_schedules`, `unclaimed_laundry`, and `storage_fees` remain in the retained band. Sending a
   message is Step 7; the ladder that schedules the unclaimed-laundry campaign is Step 9.

5. **The customer-visible status projection reads Step 5/6 state; it does not schedule pickup or
   delivery.** FR-089 lets the portal show current status and history, including the immutable first
   `READY_FOR_PICKUP` fact recorded in Step 6. Requesting a pickup or a delivery schedule change from the
   portal is Step 8 — `pickups`/`pickup_requests`/`deliveries` stay rejected. Reading readiness is
   Step 7; acting on it with a courier is Step 8.

6. **The boundary is derived, never hardcoded.** The guard reads `_common.CANONICAL_CURRENT_STEP`, raised
   from 6 to 7 in this same change. Below 7 the Step 7 labels remain forbidden exactly as before, so this
   record cannot retroactively permit anything in a Step 0–6 tree, and Step 8's own labels cannot be
   unblocked by editing this guard — they need Step 8's own authorisation and its own record.

7. **Nothing else about the guard is relaxed.** Approved runtime roots are unchanged. No runtime language
   is added. Deployment-artifact detection, credential detection, personal-data detection, symlink-escape
   detection, and status-claim honesty checks are untouched. **Deployment remains `ABSENT` and is not
   authorized by any part of this record.**

8. **Structural detection remains structural.** Renaming a Step 8+ feature to evade token matching is the
   same violation as building it under its plain name (Rule 36, hard rule 4), and permitting four labels
   does not soften that.

9. **The required CI status check context remains exactly `classify`** and is not renamed (Rule 36, hard
   rule 5).

10. **A permitted label is not an implemented feature.** `tracking_tokens` ceasing to be a forbidden
    token means a `tracking_tokens` table may now legally exist. It says nothing about whether one does,
    whether the token is stored hashed and never in plaintext, whether it is tenant-scoped, whether the
    portal projection excludes the full address, whether OTP gates sensitive actions, or whether any test
    passes. `classify` reports scope classification only and executes no application test (Rule 36, hard
    rule 6). The tenant-isolation and financial-integrity hard gates apply in full to every permitted
    label (Rule 04, Rule 48); the notification subsystem must never mutate an order's state or the
    integer-Rupiah ledger Step 5 captured (Rule 04, Rule 08, Rule 19; FR-099).

11. **The residual audits move with the boundary.** `validate-dec-0035-labels.py` and
    `validate-dec-0037-labels.py` are made canonical-step-aware so they no longer treat the four
    now-permitted Step 7 labels as forbidden from Step 7 (a false failure on authorised runtime), and a
    new `validate-dec-0039-labels.py` audits the Step 7 residual: each permitted label still traces to a
    PRD requirement (FR-086 … FR-099), and every Step 8+ label stays structurally absent. No token
    protection is removed — it moves to the step-appropriate auditor.

## Consequences

Step 7 can build the customer-tracking and notification foundation it was authorised to build, and the
forward-leak guard survives intact for Steps 8 through 14.

### Positive consequences

- The guard's strength against Step 8+ leakage is unchanged: thirteen labels remain forbidden, now
  labelled by the step that owns them rather than lumped under a name that stopped being accurate.
- The permitted set is enumerable and reviewable in one place, rather than scattered across suppressions.
- Tying the boundary to `CANONICAL_CURRENT_STEP` means Step 8's own labels cannot be unblocked by editing
  this guard — they need Step 8's own authorization and its own record, exactly as Step 7 needed this one
  and Step 6 needed DEC-0037.

### Negative consequences / trade-offs

- Four labels of outright token protection are genuinely given up. If a Step 8 pickup table were named
  `tracking_tokens`, the token check would no longer catch it — the tenant-isolation, financial-integrity,
  and review gates would have to. This record does not pretend that residual risk is zero; it is stated
  and accepted, and `validate-dec-0039-labels.py` narrows it by asserting no Step 8+ token appears in any
  structural position.
- The tracking token permitted while the external-courier guest link is forbidden, and WhatsApp
  notification permitted while the reminder ladder is forbidden, are fine distinctions a future
  contributor could get wrong in either direction. They are written down here and covered by the residual
  audit, but they are sharp edges — the same shape as `rework` vs `delivery` was under DEC-0037.
- The guard is now conditional in five bands rather than four. A conditional guard is one a reader can
  misread; the residual auditors and the adversarial fixtures are the compensating control, and they are
  load-bearing rather than supplementary.

## Verification

Verified on `feature/step-07-customer-tracking-whatsapp`, branched from the post-Step-6 canonical `main`
at `cfa7cf7399cd9769b522dc31d03edae09349a823`:

- `scripts/validate-runtime-scope.py` carried `STEP7_PLUS_FEATURE_TOKENS` with the tracking/WhatsApp and
  Step 8+ labels, and rejected every Step 7 table before this record. After the split it classifies the
  current tree within scope (5/5 checks) with `CANONICAL_CURRENT_STEP = 7` and reports the forbidden band
  as "Step 8+".
- Rule 36 hard rule 8 and DEC-0037's supersession policy were read directly and are quoted above.
- FR-086 … FR-099 were read from `docs/product/PRODUCT_REQUIREMENTS.md` and are the sole basis for which
  four labels move; `validate-dec-0039-labels.py` re-asserts that tracing on every run.
- `validate-dec-0035-labels.py` and `validate-dec-0037-labels.py` (both made step-aware) and
  `validate-dec-0039-labels.py` all pass at `CANONICAL_CURRENT_STEP = 7`, and `validate-dec-0030-labels.py`
  continues to pass.

The adversarial-fixture result demonstrating that each of the thirteen retained Step 8+ labels is still
rejected, and that the four permitted labels are accepted, is recorded in the Step 7 evidence pack under
`evidence/step-07/` bound to the exact commit it was produced from (Rule 01, DEC-0013). This record
quotes no result it did not produce.

## Requirement references

FR-086 … FR-099 (high-entropy hashed tracking-token issuance and independence from the order number,
token revocation and expiry, the public portal content set and exclusions, OTP-gated sensitive actions,
`noindex`, the notification provider abstraction, the official WhatsApp automated path and manual
deep-link fallback, transactional/marketing separation with consent, quiet-hours enforcement, message
deduplication, and messaging decoupled from order state). No requirement is created, changed, or
withdrawn; this record only permits the runtime that already-existing requirements call for.

## Threat references

The threat this record manages is scope leakage: Step 8+ business functionality entering the tree under
Step 7's authorization, either by plain naming or by renaming to evade detection. Mitigated by retaining
all thirteen Step 8+ labels, by deriving the boundary from `CANONICAL_CURRENT_STEP` rather than from an
edit to the guard, and by adversarial fixtures over both the permitted and the retained sets. Residual
risk is stated in the trade-offs above and is accepted, not eliminated. The tracking-token and portal
threat model (token guessing, enumeration, referrer/cache leakage, cross-tenant access, OTP brute force,
masking regressions) and the notification threat model (consent bypass, quiet-hours bypass, duplicate
messages, provider failure affecting order state, template injection) are Step 7 implementation concerns
carried in the Step 7 architecture and evidence, not resolved by this scope record.

## Rule references

- Rule 36 — runtime architecture and scope; hard rule 4 (structural detection), hard rule 5 (`classify`
  never renamed), hard rule 6 (presence is not correctness), hard rule 8 (widening requires a decision
  record naming what it supersedes).
- Rule 42 — Step 4+ backend scope; Step 8+ features remain `NOT IMPLEMENTED`.
- Rule 02 / Rule 39 / Rule 48 — tenant isolation; permitting a table never relaxes tenant scoping, and a
  public token resolves only its own order in its own tenant.
- Rule 03 / Rule 21 — security and data classification; the plaintext tracking token is `SECRET`, stored
  hashed, never logged.
- Rule 08 — notifications and WhatsApp; provider abstraction, quiet hours, dedup, opt-out, and a
  messaging failure never changing order state.
- Rule 04 / Rule 18 / Rule 19 — financial integrity and the order lifecycle; the notification subsystem
  reads amount-due and payment state but never mutates the ledger or an order's status.
- Rule 09 / Rule 10 — pickup/delivery (Step 8) and unclaimed laundry (Step 9) stay forbidden.
- Rule 47 — adversarial validator testing before a gate is relied upon.
- Rule 49 / Rule 50 — step status; the canonical roadmap authorises Step 7 and nothing later.

## Supersession policy

This record supersedes **only** the Step 7 portion of DEC-0037's runtime scope split — the four labels it
moves from `STEP7_PLUS_FEATURE_TOKENS` to `STEP7_FEATURE_TOKENS`. Every other part of DEC-0037, DEC-0035,
DEC-0030, and DEC-0024 remains in force unchanged: the six Step 6 labels stay permitted, the seven Step 5
labels stay permitted, the four Step 4 labels stay permitted, the approved runtime roots are untouched,
the guard-versioning split between `validate-runtime-scope.py` and `validate-no-runtime.py` stands, the
Step 0–2 guard boundary remains immutable, and the `classify` check name is unchanged.

Permitting any further feature label requires a new accepted decision record naming this one, and the
step that owns the label must itself have been authorised first. **Editing `STEP8_PLUS_FEATURE_TOKENS` to
unblock work is a governance breach, not a fix** (Rule 00, Rule 23). Narrowing the guard — moving a label
back to forbidden, or adding tokens to an existing label — needs no record, exactly as Rule 36 hard rule
8 provides.

This record does not renumber, replace, reinterpret, or expand the canonical roadmap. Step 7 remains
"Customer Tracking and WhatsApp"; DEC-0039 is only the auditable governance mechanism that transitions the
previously forbidden tracking/WhatsApp/notification runtime tokens into an explicitly Step-7-gated,
testable state.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §6 — architecture and the locked backend stack.
- §9 — the public tracking portal and its canonical security rules.
- §14 — notifications and WhatsApp.
- §24 — Roadmap; the Step 7 entry and the roadmap lock.
- §31 — Decision records.
- §32 — Changelog.
