# Security Review — Step 1

**Step:** 1 — Product Requirement and Domain Model
**Review mode:** Independent internal re-verification under single-maintainer governance
**Independent human review:** **`ABSENT`** (DEC-0016)

---

## 0. What this review is, and is not

This is an **internal re-verification**, not a peer review and not an external audit. Governance
operates in single-maintainer mode; independent human approval is `ABSENT`. Saying so plainly is a
requirement of Rule 23, and this document never describes itself otherwise.

**Step 1 creates no runtime**, so there is nothing to attack. This review examines whether the
*documented model* would produce a secure system if implemented faithfully, and whether the Step 1
corpus itself leaks anything on a public repository. It makes **no claim** about the security of any
running system, because none exists.

| Item | Status |
|---|---|
| Penetration test | `NOT APPLICABLE` — no runtime |
| Automated security scanning of application code | `NOT APPLICABLE` — no application code |
| Dependency vulnerability scan | `NOT APPLICABLE` — no dependency manifest exists |
| Threat model | Documented; controls `NOT IMPLEMENTED` |
| Secret scanning of the repository | **Executed** — see §4 |

## 1. Scope reviewed

- The 20-document domain model, including tenant boundaries and data ownership.
- The 10 canonical state machines.
- The initial threat model, abuse cases, data classification, trust boundaries, and privacy
  requirements.
- The acceptance criteria and non-functional requirements.
- The Step 1 validators themselves — a validator that fails open is a security control that does not
  exist.
- The repository's own public-repository exposure.

## 2. Review checklist against the mandated concerns

Each item was checked against the corpus. "Modelled correctly" means the documentation states the
control; it does **not** mean the control is implemented.

| Concern | Finding |
|---|---|
| Cross-tenant ambiguity | Modelled correctly. Every aggregate carries a stated tenant boundary; a client-supplied tenant ID is explicitly never authorisation proof. |
| Ambiguous owner portfolio | Modelled correctly. Consolidation is within one tenant; cross-tenant aggregation is by union over verified memberships, never by widening the query surface. |
| Customer identity sharing | Modelled correctly. Customer profiles are tenant-scoped; the same phone number in two tenants yields two unrelated profiles, never merged. |
| Monetary floating point | Modelled correctly. Integer Rupiah throughout; floating point forbidden in every money path. Validator scans for float-typed money fields and fractional decimal columns. |
| Mutable historical pricing | Modelled correctly. Price snapshot immutable; a price-list change never alters a past order, invoice, or reprint. |
| Payment replay | Modelled correctly. Idempotency keyed on a stable `ClientReference`; gateway callbacks verified server-side with replay rejected. |
| Refund deletion | Modelled correctly. No hard delete; corrections by reversal or adjustment only. |
| Tracking token enumeration | Modelled correctly. High-entropy opaque token, stored hashed, not the order number and not derivable from it, revocable, expiring, `noindex`, rate-limited, enumeration-protected. |
| Full customer address exposure | Modelled correctly. The public portal never shows a full address; masking is contextual. |
| External courier over-access | Modelled correctly. Scoped, expiring, revocable guest job link; no tenant membership; assignment-only visibility. |
| Fake proof of delivery | Modelled correctly. Proof mandatory for every custody transfer; artefacts private and signed-URL only. |
| Notification consent bypass | Modelled correctly. Transactional and marketing separated; opt-out honoured at send time. |
| Reminder spam | Modelled correctly. Each ladder stage fires once; deduplication keyed on recipient, event, order, and window; quiet hours 20.00–08.00 outlet local time. |
| Public repository PII | **Scanned and clean** — see §4. |
| Offline duplicate order / payment | Modelled correctly. `ClientReference` generated once and reused unchanged on retry; duplicates stated as unacceptable and as an automatic NO-GO. |
| Stale role membership | Modelled correctly. Authorisation derived from membership, verified server-side per request. |
| Support impersonation | Modelled correctly. Time-bound, audited, no silent tenant access. |
| Unlawful storage fee | Constrained. Fees require tenant configuration, customer awareness, terms, grace, cap, and audit. Whether the product supports them at all is an **open question** for the owner (§6). |
| Automatic disposal | **Explicitly prohibited** and validator-enforced. The corpus is scanned for un-negated disposal language. |
| Secret leakage | **Scanned and clean** — see §4. |
| Malicious markdown / link injection | Reviewed. No inline HTML, no script content, no external-resource embedding in the corpus. |
| CI bypass | Reviewed. Actions pinned to full commit SHAs; `permissions: contents: read`; explicit timeouts; no `pull_request_target`; no untrusted shell interpolation; no secrets consumed. |
| Misleading GO claim | None present. No document asserts `GO` for Step 1. |
| Stale exact-SHA evidence | None. Evidence in this pack is bound to the candidate SHA and re-run after each change. |
| Tag movement | None. The Step 0 tag was verified unmoved: object `e95c60a14c6b976fc8cdb94e7ba0d3a7b0cce9b9`, peeled `8494bc8543b9301351da6055337832597f1f2d9f`, type annotated. |

## 3. Findings

### 3.1 Findings in the validators — the security controls of this Step

Step 1 has no runtime, so the validators **are** the enforcement layer. A validator that fails open is
a control that does not exist. These were found by building an adversarial harness that deliberately
breaks the corpus, not by reading the code.

| ID | Severity | Finding | Status |
|---|---|---|---|
| SR-01 | **MEDIUM** | Threat severity was read by scanning a record for the word "HIGH". A record carrying `Likelihood: HIGH` with `Severity: INFORMATIONAL` was counted as high-severity. The same defect in reverse could have **suppressed a genuinely unmitigated HIGH threat** by mis-binding it to the wrong record. | **FIXED** — severity is parsed from the declared `Severity` field. Counts now match an independent audit exactly: 50 threats, 34 CRITICAL/HIGH. |
| SR-02 | **MEDIUM** | Threat records were split on every mention of a threat ID, so cross-references and the severity summary table produced phantom records. Phantom records dilute the population and could mask a real one. | **FIXED** — records anchored to headings. |
| SR-03 | **MEDIUM** | Excluding acceptance-criteria documents from definition scanning erased all **68 `SEC-` definitions**, because `SECURITY_ACCEPTANCE_CRITERIA.md` is that series' authoritative register. Every security requirement was invisible to traceability. | **FIXED** — duplicate detection scoped per series to its authoritative register. |
| SR-04 | **LOW** | The requirement-definition pattern did not accept backticked identifiers, so a backticked duplicate definition passed undetected. | **FIXED** — backtick, bold, and bracket forms accepted. |
| SR-05 | **LOW** | The adversarial harness hard-coded the then-current Master Source version, so once the document moved on the mutation matched nothing and the test **silently stopped testing** while still reporting. | **FIXED** — version-agnostic, and the mutation now asserts it applied. |

**SR-01 was surfaced by a subagent** that noticed the validator's threat count disagreed with its own
independent audit and said so, rather than adjusting its work to match the tool. That is the correct
behaviour and it caught a real defect.

### 3.2 Findings in the repository state

| ID | Severity | Finding | Status |
|---|---|---|---|
| SR-06 | **MEDIUM** | Step 0 status read `IN PROGRESS` in six places after the Step 0 GO tag had been created, so the repository contradicted itself about its own release state. | **FIXED** in the v1.0.1 amendment. |
| SR-07 | **MEDIUM** | `STATUS.md` §7 rule 3 forbade recording Step 0 with the release status word while `STATUS.md` §1 recorded exactly that. A rule that contradicts the document containing it will be resolved by whoever reads it next, in whichever direction suits them. | **FIXED** — reworded to the accurate rule: `GO` is owner-conferred, never self-declared. |
| SR-08 | **LOW** | The changelog asserted "no release tag has been created yet" after the Step 0 GO tag existed — a false claim under Rule 01. | **FIXED**, and the correction stated rather than silently applied. |
| SR-09 | **INFORMATIONAL** | The repository ruleset did **not** require the three new Step 1 checks, so they ran and reported but did not block a merge. | **CLOSED.** The owner applied the change; ruleset `19164588` now requires 9 contexts, enforcement `active`, 0 bypass actors, strict policy true — **re-read independently through the API**, not accepted on report. Note: the destructive-operations guard **blocked** the agent's own attempt to apply it via `gh api`, correctly, and was not bypassed or edited. |
| SR-11 | **LOW** | Two new workflows each named their job `validate`, so three workflows published a context called `validate`. A required-status-check entry naming `validate` could not distinguish them, meaning the new gates **could not have been required separately** — adding them to the ruleset would have produced an ambiguous rule rather than real enforcement. | **FIXED** in PR #7 — contexts renamed to `product-requirements` and `domain-model`, verified reporting under those names before the ruleset was updated. |
| SR-10 | **INFORMATIONAL** | Independent human review is `ABSENT`. A defect that both the maintainer and the validators miss is not caught by a second human. | **ACCEPTED** — recorded in DEC-0016 with its compensating controls. |

### 3.3 Closure position

Position at the tagged commit `4eadbc73f8bacdc9cd2acfcc62280ac932116089`:

| Severity | Open | Closed |
|---|---|---|
| `CRITICAL` | **0** | 0 |
| `HIGH` | **0** | 0 |
| `MEDIUM` | 0 | 5 |
| `LOW` | 0 | 4 |
| `INFORMATIONAL` | **1** | 1 |

SR-09 (ruleset) was **closed** by the owner applying the change; it was then re-read independently
through the API rather than accepted on report.

The one remaining open item is **SR-10 — single-maintainer governance, independent human review
`ABSENT`**. It is not closed and is not closeable by this project's current shape: it is an **accepted
standing deviation** recorded in DEC-0017, carried visibly rather than resolved. It does not block the
Step; the owner conferred `GO WITH ACCEPTED DEVIATION` in full knowledge of it.

## 4. Public-repository exposure scan

`bash scripts/validate-public-repository-safety.sh` — **14/14 checks passed**, exit 0.

Scanned for: real-looking Indonesian mobile numbers; private key blocks; AWS, Google, Slack, GitHub, and
Stripe credentials; JSON Web Tokens; assigned secret literals; database connection strings carrying
credentials; committed `.env` files; committed database dumps or backups; and any statement that this
repository is private.

Every example datum in the corpus is fictional. The `CONFIDENTIAL`, `RESTRICTED`, and `SECRET` data
classes are described and modelled but never instantiated with a real value.

## 5. Hard gates

| Gate | Position at Step 1 |
|---|---|
| **Tenant isolation** | Modelled, not implemented. Cross-tenant exposure is documented as an automatic NO-GO. No implementation exists to leak. Negative tests are mandatory from Step 3. |
| **Financial integrity** | Modelled, not implemented. Integer Rupiah, idempotency, reversal-only correction, price-snapshot immutability. No payment code exists. Tests are mandatory from Step 5. |

**Neither gate can be said to pass or fail at Step 1**, because neither is implemented. Claiming either
gate "passes" here would be a false claim; claiming either "fails" would be equally wrong. Both are
`NOT IMPLEMENTED` and both become Definition-of-Done gates at the Steps named above.

## 6. Open questions raised, not resolved

These are recorded for the owner and were **not** closed by invention (Rule 00 rule 6). Full detail is
in `docs/product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md`.

Security- and privacy-relevant items:

1. **Tracking token and guest-link expiry durations** — defaults are proposed; the owner sets the policy.
2. **Quiet-hours exception path for critical operational messages** — Master Source §14 does not grant
   one, so none was invented.
3. **Proof-artefact retention period** — proofs are personal data; retention is a legal question.
4. **Storage / late-collection fees** — whether the product supports them at all is unresolved.
5. **Minimum proof method** — whether a platform-wide minimum is mandated or it stays tenant-configurable.
6. **Cash variance threshold** requiring an explicit reason.
7. **`MASTER_SOURCE.md` §25.1 item 12** requires approval by someone other than the author. Under
   single-maintainer governance this **cannot be satisfied**. It is recorded as an unmet gate rather than
   waived; the owner decides whether it becomes a standing deviation as visibility did.

## 7. Conclusion

The Step 1 corpus models the security and privacy posture the Master Source requires, and the
repository itself carries no secret or personal-data exposure at the reviewed SHA.

**No security control is implemented.** Every control in this review is a documented obligation on a
future Step. The correct reading of this document is that the design does not contain a known security
defect — not that the product is secure, because the product does not exist.

`GO` is the repository owner's to confer. This review does not assert it.
