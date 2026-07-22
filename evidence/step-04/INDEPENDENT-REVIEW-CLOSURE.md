# Step 4 — Independent Review Closure

**Evidence anchor SHA:** `6abd3fdc918a740ea400819a23e9b0cc371778f5`
**Status:** three review rounds executed; every finding closed or explicitly
carried, with its residual boundary stated.

---

## 1. Why there were three rounds

Round 1 found twelve findings (SEC-01 … SEC-12). Round 2 **refuted a fix from
round 1**. Round 3 **refuted a fix from round 2**.

That progression is the useful part of this document. Each refutation was the
same shape:

> a control documented as absolute, with an unenumerated bypass, and a green
> test that proved only the narrower case.

It happened three times, to three different controls, written by the same agent
who had just described the pattern. The countermeasure that finally worked was
not more care — it was **moving the guard to a layer the bypass cannot reach**,
and **making the test assert the state that discriminates**.

| Round | Refuted | The bypass |
|---|---|---|
| 2 | SEC-12's first fix | `CREATE TRIGGER` yields `ENABLE ORIGIN`; `session_replication_role='replica'` skips it |
| 3 | N2's fix | Eloquent fires no model events for query-builder deletes |
| 3 | N5's fix | The replacement file transport was reachable in production |

---

## 2. Findings

### SEC-01 … SEC-12

| ID | Sev | Original defect | First fix insufficient? | Final remediation | Adversarial proof | Status |
|---|---|---|---|---|---|---|
| SEC-01 | HIGH | STATUS.md claimed no concrete `AuthService` after PR #19 merged | — | section-aware validator, 4 independent signals, both directions | 6 mutations + 3 legitimate cases, 10/10 | `FIXED_AND_VERIFIED` |
| SEC-02 | HIGH | evidence pack bound to a stale SHA | — | full rebuild at the anchor SHA; recapture reduced to one command | capture script refuses a dirty tree | `FIXED_AND_VERIFIED` |
| SEC-03 | HIGH | composition guard blind to inferred-type providers | — | pattern matches the initializer, not the annotation | harness 9/9 incl. mutation 5b | `FIXED_AND_VERIFIED` |
| SEC-04 | HIGH | published-price allow-list refused **everything** | — | `SYSTEM_MANAGED` split from the caller-facing list | 2 mutations | `FIXED_AND_VERIFIED` |
| SEC-05 | HIGH | FR-024/FR-025 had no writer and no enforced masking | — | writer, allow-list projection, both surfaces, 18-step runtime proof | 9 mutations | `FIXED_AND_VERIFIED` |
| SEC-06 | HIGH | escalation test never reached the guard | — | actor corrected; premise asserted | positive control | `FIXED_AND_VERIFIED` |
| SEC-07 | MED | `ApiClient.put()` dropped `expectedVersion` | — | forwarded; every verb parameterised | forwarding removed → fails | `FIXED_AND_VERIFIED` |
| SEC-08 | HIGH | suspended membership could receive a role | — | lifecycle guard in the registry, both grant paths | registry + UI mutations | `FIXED_AND_VERIFIED` |
| SEC-09 | MED | printer sort produced HTTP 500 | — | per-collection allow-list + live-schema class test | widened list → fails | `FIXED_AND_VERIFIED` |
| SEC-10 | HIGH | 24 mutating routes unaudited | — | router-driven gate, both directions | new route + removed `record()` | `FIXED_AND_VERIFIED` |
| SEC-11 | MED | `GET /proof-policy` wrote a row | — | read/ensure split | `save()` restored → fails | `FIXED_AND_VERIFIED` |
| SEC-12 | HIGH | consent RULEs missed truncation, refused silently | **YES** — replacement triggers were `ENABLE ORIGIN` and bypassable | `ENABLE ALWAYS` + behavioural replica-mode test + `tgenabled='A'` assertion | migration neutered → both tests fail | `FIXED_AND_VERIFIED` |

### N1 … N6 (round 2)

| ID | Sev | Defect | Final remediation | Status |
|---|---|---|---|---|
| N1 | HIGH | `verify-step-04` failed at HEAD; a passing count from an earlier SHA had been reported forward | two derived validators unpinned; every Master Source bump now moves **and re-runs** all derived validators | `FIXED_AND_VERIFIED` |
| N2 | MED | published price list could be soft-deleted | **first fix insufficient** — see NEW-01 | `FIXED_AND_VERIFIED` (via NEW-01) |
| N3 | LOW | `supersedes_price_list_id` had no foreign key | composite FK carrying `tenant_id` | `FIXED_AND_VERIFIED` |
| N4 | LOW | four audit actions with no emitter | declared dormant with reasons; gate checks the reverse direction | `FIXED_AND_VERIFIED` |
| N5 | HIGH | plaintext reset token written to a log | **first fix insufficient** — see NEW-02 | `FIXED_AND_VERIFIED` (via NEW-02) |
| N6 | MED | `internal_notes` emitted at every masking context including `NONE` | context-gated server-side; key absent, not null | `FIXED_AND_VERIFIED` |

### NEW-01 … NEW-05 (round 3)

| ID | Sev | Defect | Why the first fix was insufficient | Final remediation | Status |
|---|---|---|---|---|---|
| NEW-01 | MED | `PriceList::where(...)->delete()` soft-deleted a published list | the guard was a `static::deleting` **model event**; Eloquent fires none for query-builder deletes. The docblock claimed "softly or otherwise" while the test exercised the instance path alone | engine-level `BEFORE DELETE` + `BEFORE UPDATE` triggers, `ENABLE ALWAYS` | `FIXED_AND_VERIFIED` |
| NEW-02 | MED | the reset-link file writer was production-reachable | the fix moved the token out of a log into a file and left the write ungated. "Deployment is ABSENT so it cannot happen" is circumstance, not a control | explicit production refusal — never a silent skip, so no caller believes a link was sent | `FIXED_AND_VERIFIED` |
| NEW-03 | LOW | 946 token-bearing lines in the local log | — | purged 946 → 0; file was **untracked**, so never a public-repository disclosure | `FIXED_AND_VERIFIED` |
| NEW-04 | LOW | the local test suite shares the developer database | — | accepted **after proving CI isolation** across six conditions | `ACCEPTED_OPERATIONAL_RESIDUAL` |
| NEW-05 | INFO | a duplicate `UNIQUE (tenant_id, id)` was added | — | duplicate removed; `down()` no longer drops a constraint it never created | `FIXED_AND_VERIFIED` |

---

## 3. The vacuous-proof precondition rule

Round 3's reviewer hit this in its own protocol: the consent fixture failed a
check constraint, the table was left **empty**, and the row-level UPDATE and
DELETE refusals then "passed" while proving nothing — only the statement-level
truncation trigger caught anything. The reviewer noticed and redid the run.

A suite has no such instinct. So `assertStatementRefused` now:

1. asserts the table is non-empty **before** the prohibited statement;
2. requires an explicit refusal with SQLSTATE `23001`;
3. asserts the row count is unchanged **after** it.

A refusal test against an empty table is not a weaker test — it is a different
test that happens to be green. Adversarially confirmed by reproducing the
reviewer's accident: omitting the fixture now fails with `PRECONDITION FAILED`.

---

## 4. Residual boundaries, stated where they hold

- **SEC-12 / NEW-01.** No control enforced inside the database bounds a role that
  may rewrite the schema. The development application role **is** the superuser
  and **is** the table owner, so the earlier "bounded to the application role,
  not superusers" claim bounded nothing. What is removed is the bypass needing
  neither privilege nor DDL. A real boundary requires a non-owner,
  non-superuser application role — `docs/deployment/DATABASE_ROLE_PREREQUISITE.md`,
  marked `REQUIRED_FOR_FUTURE_DEPLOYMENT` / `NOT_YET_PROVISIONED` /
  `NOT_CLAIMED_AS_CURRENT_CONTROL`.
- **FR-025 AREA context.** `DORMANT_GOVERNED_CONTEXT`. No shipped role holds
  `customer.view` without `customer.manage`, so AREA is unreachable over HTTP.
  Tested directly against the projection; a topology test fails the moment it
  becomes reachable. No permission was invented to manufacture runtime evidence.
- **FR-025 portal clause.** Satisfied **structurally**, not behaviourally: no
  Step 4 projection can reach an unauthenticated caller because no
  unauthenticated Step 4 route exists. Step 7 must prove it against a real portal.
- **Membership suspension** has no command surface; the audit action and policy
  exist and nothing writes either. Asserted by a test so it cannot be assumed.
- **Audit depth.** 7 of 42 mutating routes are proven end-to-end; the rest are
  proven *declared*. Named "representative writes" and disclosed in the test.
- **NEW-04.** Local developer and reviewer reliability only; CI is isolated.

---

## 5. Preserved history

Deleting any of this would make the final results look right-first-time.

1. Three invalid first-run verifications — `invalid-first-run-verifications.md`.
2. A test-count claim of 411 where the verified figure was 413 — `corrections.md`.
3. Two failed runtime-proof attempts, both **verification setup errors, not
   product defects**: a trailing slash in a base URL, and tenant identifiers read
   before a reseed.
4. A mutation that did **not** discriminate — flipping the customer-detail
   projection default `NONE`→`FULL` passed the entire suite, so the "fails
   closed" claim was unproven until given its own test.
5. Three insufficient first remediations (SEC-12, N2, N5), each with the bypass
   that defeated it.
6. A verifier count reported forward across a Master Source bump (N1).
7. The reviewer's own vacuous fixture, and the rule written because of it.
