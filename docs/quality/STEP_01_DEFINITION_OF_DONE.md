# Step 1 — Definition of Done

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §24, §25, §28
**Related decisions:** [DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md),
[DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md),
[DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)

---

## 1. What Step 1 delivers

**Step 1 delivers DOCUMENTATION ONLY.** It turns the Master Source into precise, buildable requirements:
functional requirements per module, the laundry domain model, the canonical order status machine,
multi-tenancy data ownership at the entity level, acceptance criteria for the MVP scope, and the
security and quality documentation in `docs/security/` and `docs/quality/`.

**Out of scope: any code, schema, or migration.**

---

## 2. What Step 1 does NOT deliver — stated plainly

**There is no application.** Therefore:

- There are **no unit tests**, and none may be claimed.
- There are **no widget tests**, and none may be claimed.
- There are **no integration tests**, and none may be claimed.
- There are **no end-to-end tests**, and none may be claimed.
- **Application CI remains `NOT APPLICABLE`.** There is nothing to build and nothing to test.
- **Backend runtime `ABSENT`. Flutter workspace `ABSENT`. Deployment `ABSENT`.**
- **All product features `NOT IMPLEMENTED`. UAT `NOT STARTED`.**

**The only executable verification in Step 1 is the governance validators.** Their unedited output,
captured at the exact commit SHA under review, is the whole of the evidence Step 1 can honestly offer.

No security scan, penetration test, load test, device benchmark, or availability measurement has been
performed. Every numeric target in
[`NON_FUNCTIONAL_REQUIREMENTS.md`](NON_FUNCTIONAL_REQUIREMENTS.md) is a **target that has not been
measured**, and no threat, abuse case, or acceptance criterion in this Step has been exercised against
running code.

Claiming otherwise — in a document, a commit message, a pull request description, or an agent response —
is a **false claim** and is corrected immediately and visibly.

---

## 3. Gates

Step 1 is Done only when **every** gate below holds and is evidenced.

### Gate 1 — Master Source alignment
All Step 1 documentation is consistent with [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.3.0. Nothing
contradicts, extends, or softens it. No product decision was invented to close a gap; unanswered
questions are raised to the owner or recorded as assumptions, never resolved by invention. Any change to
the Master Source itself carries a version bump, a tool-regenerated checksum, and a decision record —
all three, in the same pull request.

### Gate 2 — Status honesty
Every status statement uses the approved vocabulary only: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`,
`NOT IMPLEMENTED`, `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`. No synonyms, no softening
adjectives, no hedged forms such as "should pass", "effectively done", or "essentially working". The
status snapshot reflects reality and is updated where it changed.

### Gate 3 — Exact-SHA evidence
An evidence pack exists under `evidence/step-01/`, bound to the **exact 40-character commit SHA** under
review, recording for each verification: the exact command executed, the captured output in full, the
timestamp in Asia/Jakarta, and the environment. A short SHA is insufficient. Evidence produced at one
SHA does not carry over to another — if the tree changed, it is re-run. Output is never fabricated,
paraphrased, prettified, or truncated in a way that changes its meaning.

### Gate 4 — Documented tenant isolation model
The tenant isolation model is documented completely: the hierarchy
`User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet`; the thirteen hard rules;
data ownership at the entity level; the boundary as a trust boundary in
[`../security/TRUST_BOUNDARIES.md`](../security/TRUST_BOUNDARIES.md) TB-06; the threats against it; and
testable criteria requiring negative isolation tests across every access path before any later Step's
Definition of Done. **Cross-tenant data exposure is an automatic NO-GO** and is recorded as such
throughout.

### Gate 5 — Documented security and privacy model
The security and privacy model is documented completely across
[`../security/INITIAL_THREAT_MODEL.md`](../security/INITIAL_THREAT_MODEL.md),
[`../security/ABUSE_CASES.md`](../security/ABUSE_CASES.md),
[`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md),
[`../security/TRUST_BOUNDARIES.md`](../security/TRUST_BOUNDARIES.md),
[`../security/PRIVACY_REQUIREMENTS.md`](../security/PRIVACY_REQUIREMENTS.md), and
[`../security/SECURITY_ACCEPTANCE_CRITERIA.md`](../security/SECURITY_ACCEPTANCE_CRITERIA.md). **Every
CRITICAL and HIGH threat carries at least one explicit mitigation**; a CRITICAL or HIGH threat with no
mitigation is a validator failure.

### Gate 6 — Documented financial integrity model
The financial integrity model is documented completely: **integer Rupiah** with floating point forbidden
in every financial path; idempotent payments keyed on a stable `client_reference`; server-verified
gateway callbacks; no order marked paid on a client claim; refund and void requiring permission and a
reason; no deletion of financial transactions through ordinary UI; corrections by reversal or adjustment
preserving the original; historical order prices immune to price-list changes; shift-close and courier
cash variance recorded and acknowledged, never hidden. **Financial integrity failure is an automatic
NO-GO.**

### Gate 7 — No false claims
Nothing in code, documentation, pull request text, commit messages, or agent output asserts a
capability, test, build, deployment, CI run, or UAT result that does not exist. No empty folder,
`README`, or `.gitkeep` is presented as an implemented feature. Where something was not verified, it is
labelled an **unverified claim** in plain words.

### Gate 8 — Documentation updated
All canonical documents affected by Step 1 are updated: [`../STATUS.md`](../STATUS.md),
[`../ROADMAP.md`](../ROADMAP.md), the changelog, and any decision record a new or changed decision
requires. All internal markdown links resolve to files that actually exist. Nothing declared in Step 1's
scope was quietly dropped, and no work belonging to a later Step was performed.

### Gate 9 — CI green at the exact SHA
The governance validators pass at the **exact commit SHA being reviewed**, with their unedited output in
the evidence pack. GitHub Actions are pinned to full commit SHAs, never floating tags. Workflow
`permissions:` are set explicitly and default to `contents: read`. Secret scanning passes: **no secret,
credential, token, key, or customer personal data appears anywhere in the diff or the evidence pack**.
Runtime detection confirms **no runtime artefact was introduced** — no `pubspec.yaml`, no
`composer.json`, no `package.json`, no schema, no migration.

### Gate 10 — Owner acceptance
**`GO` is conferred by the repository owner and is never self-declared.** An agent never writes `GO` for
itself, and never as the status of this Step in its own pull request. The pull request may state
`IN PROGRESS` or, after validation, `TESTED` or `WATCH` — nothing beyond what the evidence supports.

---

## 4. Public repository constraints on this Step

**This repository is PUBLIC**, an accepted deviation from a desired PRIVATE (AMENDMENT-0001, DEC-0016).
It is **never described as private**. Step 1 authoring therefore also requires:

- **Every example datum is fictional** and recognisably so, per
  [`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md) §6. An example is invented,
  never copied from reality.
- **Only `PUBLIC` and sanitised `INTERNAL` material is committed.** `CONFIDENTIAL`, `RESTRICTED`, and
  `SECRET` may be described and modelled but **never instantiated with real values**.
- **Evidence packs are sanitised before commit** and state that sanitisation occurred.
- Pricing appearing anywhere matches the Master Source **character for character**.
- A committed secret is **rotated first and removed second**; deletion is not remediation.

---

## 5. Governance mode — stated honestly

**Governance operates in single-maintainer mode. Independent human approval is ABSENT.**

Master Source §25.1 item 12 requires a Step-closing change to be reviewed and approved by someone other
than the author. That requirement **cannot currently be satisfied**, and the gap is recorded here rather
than quietly satisfied by a second pass from the same person. The compensating controls are the active
branch-protection ruleset, exact-SHA CI, and the deterministic validators.

**Internal re-verification by the maintainer is never described as independent peer review.** A mistake
that the maintainer and the validators both miss is not caught by a second human, and the validators'
coverage is therefore a real dependency rather than a formality.

---

## 6. Violation handling

- **Step 1 declared done without exact-SHA evidence** — the declaration is void. Re-run verification or
  withdraw the claim.
- **Any claim of application tests, builds, deployment, CI, or UAT in Step 1** — remove immediately.
  These are `NOT APPLICABLE`, `ABSENT`, and `NOT STARTED`, and saying otherwise is a false claim.
- **Fabricated output or a hand-edited checksum** — automatic **NO-GO**. Stop work, disclose to the
  owner, and treat every other claim from the same session as suspect until re-verified.
- **A CRITICAL or HIGH threat left without a mitigation** — validator failure; the Step is not Done.
- **A runtime artefact introduced during Step 1** — remove it and report the scope breach.
- **`GO` self-declared by an agent** — revert the wording before the pull request proceeds.
- **A secret or real personal datum committed** — rotate first, disclose, then remove.
- Repeated status inflation is grounds for the owner to reject the branch entirely.

---

## 7. Step 1 completion checklist

| # | Item | Gate |
| --- | --- | --- |
| 1 | Functional requirements per module documented | 1 |
| 2 | Laundry domain model documented at the entity level | 1 |
| 3 | Canonical order status machine documented, including the exact entry conditions of `READY_FOR_PICKUP` | 1 |
| 4 | Multi-tenancy data ownership expressed at the entity level | 4 |
| 5 | MVP acceptance criteria documented | 1 |
| 6 | Threat model, abuse cases, data classification, trust boundaries, privacy requirements, and security acceptance criteria documented | 5 |
| 7 | Non-functional requirements documented with metric, method, environment, threshold, Step, and consequence | 1 |
| 8 | Financial integrity model documented | 6 |
| 9 | Status vocabulary correct everywhere; no forbidden claim anywhere | 2, 7 |
| 10 | All internal markdown links resolve | 8 |
| 11 | `STATUS.md`, `ROADMAP.md`, and the changelog updated | 8 |
| 12 | Governance validators pass at the exact SHA; output captured unedited | 3, 9 |
| 13 | Secret scanning passes; every example fictional; evidence sanitised | 4 (§4 above), 9 |
| 14 | No runtime artefact of any kind introduced | 9 |
| 15 | Pull request open against `main`, stating what was verified, at which exact SHA, and what remains unverified — and **not** claiming `GO` | 10 |

---

## 8. Open questions for the repository owner

These are raised, not resolved. **No product decision has been invented to close them.**

1. **Numeric targets versus Master Source §19.3.** §19.3 states that concrete numeric budgets are set in
   Step 13, measured against real devices, and that the Master Source deliberately does not invent
   numbers that have not been measured. The headline figures recorded in
   [`NON_FUNCTIONAL_REQUIREMENTS.md`](NON_FUNCTIONAL_REQUIREMENTS.md) §3 — API p95 under 500 ms, portal
   primary content under 2.5 seconds, Android cold start under 3.5 seconds, crash-free sessions at least
   99.5%, availability 99.9%, RPO 15 minutes, RTO 4 hours — are recorded there as **proposed Step 1
   targets awaiting owner confirmation and Step 13 measurement**, explicitly not as canonical budgets and
   explicitly not as measured results. The owner may wish to confirm this framing, or to accept the
   figures into the Master Source through a version bump, checksum refresh, and decision record.
2. **§25.1 item 12 — independent review.** The general Definition of Done requires approval by someone
   other than the author for a Step-closing change. Single-maintainer governance means this cannot
   currently be met. The gap is recorded in §5 above rather than waived silently; the owner decides
   whether to accept it as a standing deviation, as DEC-0016 did for visibility.
3. **Storage or late-collection fees.** ABUSE-020 in
   [`../security/ABUSE_CASES.md`](../security/ABUSE_CASES.md) constrains such fees to a tenant-configured,
   disclosed, audited financial transaction. Whether the product supports them at all is a **product
   decision that has not been made**, and none has been assumed here.
4. **Proof method policy per tenant.** Rule 09 permits OTP, photo, signature, or recipient name to vary
   by tenant policy while requiring that *some* proof always exists. Whether a minimum method is mandated
   platform-wide is an open product question for Step 8.

---

## 9. Related documents

- [`ACCEPTANCE_CRITERIA.md`](ACCEPTANCE_CRITERIA.md)
- [`NON_FUNCTIONAL_REQUIREMENTS.md`](NON_FUNCTIONAL_REQUIREMENTS.md)
- [`../security/INITIAL_THREAT_MODEL.md`](../security/INITIAL_THREAT_MODEL.md)
- [`../security/ABUSE_CASES.md`](../security/ABUSE_CASES.md)
- [`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md)
- [`../security/TRUST_BOUNDARIES.md`](../security/TRUST_BOUNDARIES.md)
- [`../security/PRIVACY_REQUIREMENTS.md`](../security/PRIVACY_REQUIREMENTS.md)
- [`../security/SECURITY_ACCEPTANCE_CRITERIA.md`](../security/SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../DEFINITION_OF_DONE.md`](../DEFINITION_OF_DONE.md) · [`../STATUS.md`](../STATUS.md) · [`../ROADMAP.md`](../ROADMAP.md)
