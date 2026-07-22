# DEC-0033 — Step 4 Independent Review Findings and Closure Conditions

**ID:** DEC-0033
**Title:** Step 4 Independent Review Findings SEC-01 … SEC-12, and the Conditions Under Which Each Closes
**Status:** ACCEPTED
**Date:** 22 July 2026

---

## Numbering history

DEC-0033 is a new identifier. It reuses nothing, renumbers nothing, and
supersedes nothing. DEC-0032 remains the record of the Step 3 post-GO corrective
auth wiring; this record is about the Step 4 review that found, among other
things, that DEC-0032's outcome had not been reflected in the current-state
prose.

---

## Context

An independent review of the Step 4 branch produced twelve findings, SEC-01
through SEC-12. They were not stylistic. Four of them describe controls that
were documented as holding and did not:

- a consent table protected by PostgreSQL RULEs, documented as a boundary
  "even a migration, an import, or a direct psql session cannot" cross, where
  the rule system never sees a table-level truncation and `DO INSTEAD NOTHING`
  refuses silently (SEC-12);
- a published-price-list allow-list that refused **everything**, because the
  optimistic-version trait's hook runs before the immutability check, so the
  concurrency counter looked like a forbidden business-field change on every
  save (SEC-04);
- twenty-four state-changing routes with no audit trail at all, which made
  "who changed this price, and when" unanswerable (SEC-10);
- `FR-024` and `FR-025` carried as delivered when the address table had no
  writer and the masking was a document (SEC-05).

Two properties recur across the set and are the reason this record exists rather
than a list of commits:

1. **A test can be green for a reason unrelated to what it claims.** SEC-04's
   guard refused every write, and every immutability test passed — because a
   guard that refuses everything refuses the forbidden cases too. A negative-only
   suite cannot establish that kind of contract.
2. **A document is not a control.** SEC-01, SEC-05 and SEC-12 each describe a
   claim that was true when written, or true in intent, and that nothing
   connected to the repository or to the engine.

## Options considered

1. **Waive the lower-severity findings and close Step 4.** Rejected. No waiver
   was authorised for any of SEC-04 … SEC-12, and the two findings that look
   lowest-severity — SEC-07's unused `put()` and SEC-09's sort — are both cases
   where the defect becomes live the moment somebody builds the caller, which is
   exactly when nobody re-checks.
2. **Defer FR-024/FR-025 to Step 5.** Rejected. They are mandatory Step 4 scope
   (Master Source §24, Rule 50). A table without a writer is not partial
   delivery; it is non-delivery with the furniture arranged.
3. **Invent a permission so the AREA masking context could be exercised over
   HTTP.** Rejected, and recorded here because it was tempting. It would have
   manufactured runtime evidence for a configuration the product does not ship,
   and the evidence would have described a fixture rather than the system.
4. **Remediate every finding and bind each to executed evidence.** Accepted.

## Decision

1. **All twelve findings require remediation before merge.** No finding may
   close as `ACCEPTED_RESIDUAL` on the strength of being hard, low-severity, or
   unreachable today.

2. **FR-024 and FR-025 are mandatory Step 4 scope.** A database table without a
   writer is incomplete implementation, not partial delivery. FR-024/FR-025 may
   be marked `COMPLETE_AND_VERIFIED` only after backend, both client surfaces,
   and a runtime proof against a live backend all exist and are evidenced.

3. **Address masking is enforced SERVER-SIDE**, as an allow-list projection
   assembled from named fields — never as a filtered copy of a full record. A
   filter holds the full value and must remember to drop it, so a new column is
   exposed by default; an allow-list omits by construction. Client visibility is
   never the security boundary.

4. **The AREA masking context is `DORMANT_GOVERNED_CONTEXT`.** No shipped role
   holds `customer.view` without `customer.manage`, so AREA is currently
   unreachable over HTTP. This is a fact about Step 4's permission topology, not
   an implementation failure, and it is accepted **only** because all five of
   the following hold:
   - no shipped role requires AREA today;
   - the projection behaviour is directly tested;
   - a permission-topology test fails the moment a role gains
     view-without-manage, so the branch cannot go live untested;
   - no permission was invented to manufacture runtime evidence;
   - the documentation distinguishes **implemented projection capability** from
     **currently reachable role topology**, rather than letting the first imply
     the second.

5. **A verification must prove the control it names.** A test that passes
   incidentally is invalid evidence. Where a property could be satisfied for an
   unrelated reason, the remediation carries an adversarial mutation showing the
   test fails when the control is removed. Where a control was found to be
   unexercised — the fail-closed default on the customer-detail projection, which
   a NONE→FULL mutation passed the entire suite — it was given its own test
   rather than left as a comment.

6. **Test-count corrections remain historical.** The SEC-04 commit claimed
   411/411 when the verified figure was 413/413. History on a pushed branch is
   not rewritten; the correction sits in `evidence/step-04/corrections.md`
   alongside the original. The direction was understatement, and it is disclosed
   regardless: a verification claim bound to a SHA is either accurate or it is
   not.

7. **The consent protection is bounded to the application database role.**
   `customer_consents` now refuses `UPDATE`, `DELETE` and table truncation
   through raising triggers, which fire for the table owner and for a superuser
   alike. It does **not** claim protection against schema-level destruction or
   trigger disablement by an actor holding ownership or superuser rights — that
   is the same authority that installed the trigger. The claim is stated at the
   boundary it actually holds.

8. **The destructive-operations guard false positives are a separate governance
   follow-up.** Seven blocks are now recorded across two classes: command text
   that describes a destructive operation without performing one, and overly
   broad path patterns. None justified bypassing the guard, none was worked
   around with `--no-verify`, and the guard was not edited. The recorded cost is
   pressure toward less precise writing in commit messages, test names and
   migration comments — the artefacts where precision matters most.

## Consequences

### Positive consequences

- Four controls that were documented as holding now actually hold, and each has
  a mutation proving its test discriminates.
- Every state-changing Step 4 route is audited, enforced against the live router
  in both directions, so a new write route cannot be added unaudited quietly.
- FR-024/FR-025 exist as running code on two surfaces with a runtime proof.
- STATUS.md's authentication claims are tied to the repository by four
  independent signals, in both the absence and the completion direction.

### Negative consequences / trade-offs

- The audit trail now records more, including price before/after values. That is
  deliberate — a pricing dispute asks exactly that — but it is more data with a
  retention obligation attached.
- The AREA masking branch is built and tested but not exercised by any shipped
  role. It carries maintenance cost for a configuration that does not yet exist.
  Building it later would mean retrofitting a privacy control into a surface
  already in use, which is why the cost is accepted.
- Address writes are now possible from two surfaces, which widens the surface a
  future defect could reach.
- The consent triggers make a legitimate administrative correction harder. That
  is the intended direction, and the sanctioned path is the migration's `down()`.

## Verification

Each finding's evidence is recorded in `evidence/step-04/` and in the commit that
closed it. Every remediation carries at least one adversarial mutation showing
its test fails when the control is removed. No finding closed on the strength of
a test that had only ever been run against correct input.

The runtime proof for FR-024/FR-025 ran on an Android emulator against a live
Laravel backend, PostgreSQL 18.4 and Redis, through the production composition —
which overrides the environment and nothing else, so a dependency wired only in a
test fails there exactly as it would on a real launch.

## Supersession policy

This record is superseded only by a later accepted decision that names it
explicitly. Reopening any finding requires evidence of an actual regression, not
a preference about how it was closed. The `DORMANT_GOVERNED_CONTEXT`
classification in decision 4 lapses automatically the moment a role gains
`customer.view` without `customer.manage`: the permission-topology test fails,
and HTTP-level masking coverage must be added before it can pass again.

## Related Master Source sections

§15.4 (customer master data), §22 (MVP boundary), §24 (roadmap and step lock),
§25.1 (governance and approval).

## Rule references

Rule 01 (status and evidence), Rule 02 and Rule 48 (tenant isolation), Rule 04
(financial integrity), Rule 32 (security and privacy UX), Rule 43 (database and
migrations), Rule 46 (runtime observability), Rule 47 (adversarial validator
gates), Rule 50 (Step 4 status).
