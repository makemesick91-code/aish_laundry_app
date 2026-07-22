# DEC-0034 — A Step 3 Security Correction Carried in the Step 4 Pull Request

**ID:** DEC-0034
**Title:** Password-Reset Token Disclosure: a Step 3 Post-GO Security Correction Co-Delivered in PR #18
**Status:** ACCEPTED
**Date:** 22 July 2026

---

## Numbering history

DEC-0034 is a new identifier, verified unused before allocation. It reuses
nothing and renumbers nothing. DEC-0032 records the Step 3 post-GO corrective
auth wiring; this record covers a *different* Step 3 defect found during Step 4's
independent review.

---

## Context

Step 4's closure review found that `PasswordResetController::deliverResetLink`
wrote a **plaintext password-reset token** into a log message. The token was put
in the message string rather than the context array specifically so the redaction
processor would not scrub it, and the code said so:

> "Note the token is placed in the message STRING, never in the log context
> array — the redaction processor scrubs context keys, and relying on it to carry
> a secret would be depending on a control to fail safely rather than not
> creating the exposure."

The first half of that reasoning is correct. The conclusion drawn from it was
wrong: the answer to "a redactor should not be trusted to carry a secret" is not
to route around the redactor, it is to not put the secret in a log. **Rule 46
hard rule 2 admits no exception** — password-reset tokens are never written to
logs, "at any log level, temporarily or permanently." A local development
transport is not an exception; the rule names none.

Two consequences made this worse than a lint-level slip:

1. **Four tests recovered the token by grepping the log.** They depended on the
   exposure. A test that reads a secret out of a log is asserting the secret is
   in the log, and it would have failed the moment anybody fixed the defect —
   which is what happened.
2. **946 lines carrying full tokens accumulated** in the local, untracked log
   across two days of test runs. Untracked, so never a public-repository
   disclosure; purged to 0 nonetheless, because Rule 03 and Rule 46 require
   purging affected log data where possible.

The first remediation was itself insufficient. It moved the token out of the log
and into a file, and left the file writer reachable in production — so a
deployment would have dropped a live bearer-token URL onto disk on every reset.
"Deployment is `ABSENT`, so it cannot happen" is circumstance, not a control:
precisely the reasoning the SEC-12 remediation had rejected hours earlier.

**This code belongs to Step 3.** It was written under Step 3's authorization,
merged before Step 3's `GO`, and is covered by Step 3's evidence pack.

## Options considered

1. **Leave it and record it for a later step.** Rejected. A plaintext credential
   in a log is a Rule 46 violation with no severity threshold attached, and the
   step that found it is the step that should not ship past it.
2. **Extract it into a separate Step 3 corrective pull request**, as PR #19 did
   for the auth wiring. Rejected on balance. That pattern was right for DEC-0032,
   which changed the production composition of three applications and was a
   prerequisite for Step 4 functioning at all. This change is ~40 lines in one
   controller plus its tests, it is already integrated and verified inside PR
   #18, and extracting it now would mean reverting it here, re-verifying both
   branches, and re-running authoritative CI twice — increasing the number of
   states in which the defect is un-fixed, to satisfy a boundary rather than a
   risk.
3. **Reopen or re-tag Step 3.** Rejected outright. The Step 3 `GO` tag is
   immutable (Rule 11). A defect found later does not retroactively unmake a
   `GO` conferred against the evidence that existed at the time.
4. **Co-deliver in PR #18 under an explicit accepted deviation.** Accepted.

## Decision

1. **The password-reset token-logging correction is co-delivered in PR #18** as a
   minimal, scoped security correction, classified
   `STEP_3_POST_GO_SECURITY_CORRECTION_DISCOVERED_DURING_STEP_4`.

2. **The defect belongs historically to Step 3.** Step 4's traceability records
   it as a cross-step correction and does not claim it as Step 4 scope. Step 3's
   original evidence is **not rewritten**: it was accurate about what was
   verified at the SHA it covered, and this defect was not among the things it
   claimed.

3. **The Step 3 `GO` tag is immutable and is not moved, deleted, re-pointed, or
   re-cut.** This record is the correction's home, exactly as DEC-0032 was.

4. **Step 4 merge-readiness depends on this correction remaining verified.** If
   the production refusal or the no-logging property regresses, Step 4 is not
   merge-ready regardless of its own findings.

5. **This authorization is narrow.** It permits *this* correction in *this* pull
   request because it is a security fix found by the review that gates the merge.
   It does not license unrelated cross-step work, refactoring of Step 3 code, or
   any further scope movement. A future cross-step change needs its own decision.

## Consequences

### Positive consequences

- A plaintext credential no longer reaches any log, at any level.
- Production cannot write the token to disk either: the local transport refuses
  outright rather than skipping silently, so a caller is never told a link was
  sent when it was not.
- Four tests that depended on a security defect now use a transport seam instead.
- The 946-line local token residue is gone.

### Negative consequences / trade-offs

- PR #18 now contains a change outside Step 4's scope. That is a real cost:
  a reviewer reading the diff sees Step 3 code in a Step 4 pull request, which
  is exactly the confusion step boundaries exist to prevent. It is mitigated by
  this record and by the traceability entry, not eliminated.
- The local transport is now a file rather than a log line, which is marginally
  less convenient for a developer tailing output.
- The correction is bound to Step 4's CI run. If Step 4's merge is deferred, the
  fix is deferred with it — which is why the defect and its status are recorded
  here rather than only in a commit message.

## Verification

- No production path writes the token to a log, an exception, an event string,
  an audit record, or debug output; asserted by
  `LogRedactionTest::test_f6_a_password_reset_token_never_reaches_the_log`,
  which carries a positive control proving the link *was* delivered.
- The local transport refuses in production; asserted by
  `test_f7_the_local_reset_transport_refuses_to_run_in_production`.
- Both properties adversarially confirmed: restoring token logging fails F6, and
  removing the production gate fails F7.
- Local log residue purged, 946 → 0, verified by count.
- No token or token-bearing URL appears anywhere under `evidence/`.

## Supersession policy

Superseded only by a later accepted decision naming it explicitly — for example
one introducing an approved delivery provider, which would replace the local
transport entirely and make the production refusal unreachable rather than
merely correct.

## Related Master Source sections

§15.6 (security and privacy), §24 (roadmap and step lock), §25.1 (governance).

## Rule references

Rule 01 (status and evidence), Rule 03 (security and privacy, hard rule 20),
Rule 11 (immutable tags), Rule 12 (autonomous execution and scope), Rule 46
(runtime observability, hard rule 2).
