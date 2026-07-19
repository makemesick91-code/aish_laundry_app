# Rule 13 — Testing and Definition of Done

## Purpose

To define what "done" means for each step of the Aish Laundry App roadmap, so that completion is a
verifiable state rather than an opinion. Backed by **DEC-0013 — Exact-SHA Evidence Before GO**.

## Universal Definition of Done gates

A step is not done until **all** of these hold:

1. **Master Source alignment** — the work matches `docs/MASTER_SOURCE.md`; any product change carries
   a version bump, refreshed checksum, and a decision record (Rule 00).
2. **Status honesty** — every status uses the approved vocabulary and reflects reality (Rule 01).
3. **Exact-SHA evidence** — verification output is captured with the full commit SHA, exact command,
   output, timestamp, and environment (Rule 01).
4. **Tenant isolation** — no cross-tenant read, write, list, search, export, or file access is
   possible; negative tests prove it (Rule 02).
5. **Security and privacy** — server-side authorization, no secrets committed, no credentials in
   logs, private files behind signed URLs (Rule 03).
6. **Financial integrity** — integer Rupiah, idempotent payments, no hard deletes, corrections by
   reversal, historical prices immutable (Rule 04).
7. **No false claims** — nothing in code, docs, PR text, or UI asserts capability that does not exist
   (Rule 01).
8. **Documentation updated** — including the status snapshot (Rule 15) when it changes.
9. **CI green at the exact SHA being reviewed**, with pinned actions and least-privilege permissions
   (Rule 11).
10. **Owner acceptance** — `GO` is conferred by the repository owner, never self-declared.

## Testing expectations by area

Applied when the relevant step is built; recorded now so no step ships weaker.

- **Step 3 — Auth, tenancy, RBAC**: negative tenant-isolation tests across every access path
  (direct ID, list, filter, search, export, file URL); permission enforcement server-side; session
  and device revocation actually revoking.
- **Step 5 — POS, order, payment**: idempotent retry produces exactly one payment; duplicate gateway
  callback rejected; refund/void permission and reason enforced; historical price immutability;
  shift-closing variance computed and surfaced.
- **Step 6 — Production operations**: status transition validity; the first-`READY_FOR_PICKUP`
  timestamp recorded once and immutable (Rule 10).
- **Step 7 — Tracking and WhatsApp**: token entropy, hashed storage, expiry and revocation; portal
  masking (never a full address); quiet-hours deferral; deduplication; opt-out honoured; messaging
  failure never changing order state.
- **Step 8 — Pickup and delivery**: proof required for every custody transfer; proof artifacts private
  and signed-URL only; guest link scoped, expiring, revocable, non-guessable; courier cash
  reconciliation variance recorded.
- **Step 9 — Unclaimed laundry**: aging anchored to first `READY_FOR_PICKUP` and not restarting;
  each ladder stage firing once; all nine dashboard fields present.
- **Offline (Ops app)**: retry after network loss creates no duplicate order or payment; queue
  survives app kill; tenant switch leaks no cached data; payment conflict surfaces rather than
  overwrites (Rule 07).
- **Step 13 — Security, performance, backup, recovery**: restore actually exercised, not assumed;
  rate limiting and brute-force protection verified.
- **Step 14 — Pilot and commercial launch**: UAT executed with recorded outcomes.

## Step 0 Definition of Done

Step 0 has **governance validators only**. There is no application, so there are no unit, widget,
integration, or end-to-end tests, and none may be claimed.

Step 0 is done when:

- `docs/MASTER_SOURCE.md` exists at version 1.0.0 with a correct, tool-generated checksum;
- all mandated decision records exist with every required heading;
- `CLAUDE.md` and all 16 files under `.claude/rules/` exist and are consistent with the Master Source;
- the destructive-operations guard exists, is executable, and its `--self-test` passes;
- all internal markdown links resolve to files that actually exist;
- the governance validator (`bash scripts/verify-step-00.sh`) passes, with output captured at the
  exact SHA;
- the status snapshot still reads: Step 0 `IN PROGRESS`, Steps 1–14 `PLANNED`, features
  `NOT IMPLEMENTED`, backend `ABSENT`, Flutter workspace `ABSENT`, deployment `ABSENT`, application
  CI `NOT APPLICABLE`, UAT `NOT STARTED`;
- the pull request is open against `main` and does **not** claim `GO`.

## Violation handling

- **A step declared done without exact-SHA evidence** — the declaration is void; re-run verification
  or withdraw the claim.
- **Tests claimed but not run, or output fabricated** — automatic **NO-GO** (Rule 01); every other
  claim from the same session is suspect until re-verified.
- **A hard gate (tenant isolation, financial integrity) untested** — the step is not done, regardless
  of feature completeness.
- **Any Step 0 claim of application tests, builds, deployment, or UAT** — remove immediately; these
  are `NOT APPLICABLE` / `ABSENT` / `NOT STARTED` and saying otherwise is a false claim.
- **`GO` self-declared by an agent** — revert the wording; `GO` is the owner's to give.
