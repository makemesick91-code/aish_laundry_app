# DEC-0017 — Single-Maintainer Approval Standing Deviation

**ID:** DEC-0017
**Title:** Single-Maintainer Approval Standing Deviation
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §25.1 item 12 requires that, for a Step-closing change,
*"the pull request is reviewed and approved by someone other than the author."*

Aish Laundry App is developed under **single-maintainer governance**. There is exactly one maintainer,
who is also the repository owner. There is no second human with commit or review authority. The
requirement therefore **cannot be satisfied** — not because it was skipped, deprioritised, or found
inconvenient, but because the second person it presupposes does not exist.

This surfaced concretely at Step 1. Every other Definition-of-Done item was met and evidenced at an
exact SHA; item 12 alone could not be. Three responses were possible:

1. **Silently waive it.** Rejected. A Definition-of-Done item that is quietly ignored is worse than one
   that was never written, because the checklist then implies a level of assurance the project does not
   have.
2. **Mark each Step as failing its Definition of Done.** Rejected as misleading in the other direction:
   it would flag a permanent structural condition as though it were a per-Step defect, and repeated
   identical failures train a reader to ignore the item entirely.
3. **Record it once as a standing, accepted deviation with named compensating controls**, so that every
   future Step inherits an honest position rather than re-litigating it. **Chosen.**

This mirrors the treatment of repository visibility in AMENDMENT-0001 and
[DEC-0016](DEC-0016-public-repository-visibility-accepted-deviation.md): a canonical requirement that
reality cannot currently meet is recorded as a deviation with its consequences stated, not deleted and
not pretended away.

---

## Decision

**Master Source §25.1 item 12 — independent approval — is recorded as a standing accepted deviation for
as long as the project operates under single-maintainer governance.**

1. **Independent human approval is `ABSENT`.** This is stated plainly in every Step's evidence pack and
   pull request. It is never omitted, never softened, and never implied to be satisfied.
2. **The deviation is standing, not per-Step.** It applies to every Step from Step 1 onward until
   superseded. A Step does not re-argue it, and a Step does not record it as a fresh failure.
3. **Item 12 remains canonical and is not deleted.** It states the correct requirement for a project
   with more than one maintainer, and it becomes binding the moment a second maintainer exists.
4. **`GO` remains the repository owner's to confer.** This deviation does not transfer, dilute, or
   automate that authority. An agent still never self-declares `GO`.
5. **Internal re-verification is never described as review or approval.** Where a report would
   otherwise say "reviewed", it says *"internally re-verified under single-maintainer governance"*.
   Describing self-verification as peer review is a false claim under §1.3, not a wording preference.

### Compensating controls

Because the human control is absent, these carry its weight and are therefore load-bearing rather than
supplementary:

| Control | What it substitutes for |
|---|---|
| **Active repository ruleset**, zero bypass actors, PR-only to `main`, enforcement proven by a `GH013` rejection | A reviewer preventing a direct push |
| **Exact-SHA CI** on every required check (DEC-0013) | A reviewer confirming the tree actually builds and validates |
| **Deterministic validators**, standard library only, no LLM in the gate path | A reviewer checking the rules were followed |
| **Adversarial validator testing** — the corpus is deliberately broken and the responsible validator must fail | A reviewer noticing that a check is vacuous |
| **Recorded internal re-verification** with findings by severity | A reviewer's written review |
| **Evidence packs bound to an exact SHA**, sanitised, with unverified items labelled | A reviewer's independent confirmation |

### The honest limitation

These controls are **not equivalent** to independent review, and this record does not claim they are.

A validator checks what it was written to check. It cannot notice that a requirement is wrong, that a
domain model is subtly mis-shaped, that a threat was never imagined, or that a whole category of
problem is missing. **A defect that both the maintainer and the validators miss is not caught.** That
residual risk is real, is accepted, and is the reason this record exists rather than a waiver.

The adversarial harness partially addresses the narrower failure mode — a validator that silently
passes everything — by requiring each validator to demonstrate it fails on the defect it exists to
catch. During Step 1 this found seven defects in newly written validators that reading the code had not
revealed. It does not address the wider failure mode of an unimagined problem.

---

## Consequences

- Master Source §25.1 item 12 gains an explicit note recording this deviation and pointing here.
- Every Step's evidence pack and pull request states that independent human approval is `ABSENT`.
- `.claude/rules/23-public-repository-safety.md` already forbids describing internal re-verification as
  peer review; that rule now cites this record.
- Step 1 is assessed as meeting its Definition of Done **with this deviation recorded**, in the same way
  Step 0 was assessed with the visibility deviation recorded.

### Positive consequences

- The gap is **visible and permanent in the record** rather than resurfacing as an identical unexplained
  failure at every Step, which is the condition under which people stop reading a checklist.
- The compensating controls are **named**, so their adequacy can be argued about on the merits, and so a
  future reader understands they are load-bearing rather than nice-to-have.
- The residual risk is stated rather than implied, which makes it possible to decide later that it has
  grown too large.
- It becomes obvious what would close the deviation: a second maintainer.

### Negative consequences / trade-offs

- **A real class of defect goes uncaught.** Independent review catches wrong-in-a-plausible-way work,
  which is exactly what automation cannot catch. Nothing in this record fixes that.
- **The validators now carry more weight than they were designed for.** They were meant to supplement a
  reviewer, not replace one. Their coverage is a genuine dependency, and a gap in a validator is now a
  gap in the only control.
- **Normalisation risk.** A standing deviation is easier to stop noticing than a recurring failure. The
  mitigation is that it must be restated in every evidence pack — but restating a thing is not the same
  as re-examining it.
- **Self-assessment bias is unmitigated.** The person writing the evidence is the person judging it.
- Recording the deviation makes it **publicly visible** that this project has no independent review,
  which is information a hostile reader can use. That is accepted as the cost of honesty (DEC-0016).

---

## Verification

1. `python3 scripts/validate-decisions.py` — asserts DEC-0017 exists exactly once, carries status
   ACCEPTED, and contains all twelve mandated headings.
2. `python3 scripts/validate-required-files.py` — asserts the decision-record set is complete at
   seventeen records.
3. `python3 scripts/validate-master-source.py` — asserts the Master Source declares its version in both
   header and footer and that its recorded SHA-256 digest matches, regenerated by tooling.
4. `bash scripts/verify-step-01.sh` — the aggregate gate, captured at an exact SHA (DEC-0013).

**What cannot be verified by this repository:** that the compensating controls are *sufficient*. That is
a judgement, not a measurement, and it is the repository owner's to make and to revisit.

---

## Supersession policy

This record is superseded, never edited into a different decision. It would be superseded by a record
that:

- adds a **second maintainer or reviewer**, at which point §25.1 item 12 becomes satisfiable and this
  deviation ends; or
- changes the compensating controls; or
- concludes that the residual risk is no longer acceptable and pauses Step closure until independent
  review exists.

Any such record cites `DEC-0017` explicitly, and this record gains a supersession note pointing at its
replacement while keeping its content intact. The identifier `DEC-0017` is permanent and never reused.

**This deviation is never widened by implication.** It excuses exactly one Definition-of-Done item —
independent approval. It does not excuse any other gate, does not lower any validator threshold, does
not permit `GO` to be self-declared, and does not authorise merging without green required checks.

---

## Related Master Source sections

- §1.3 Honesty rules — never claim assurance the project does not have.
- §15.8 Public repository authoring constraints and single-maintainer governance.
- §25.1 General Definition of Done, item 12 — the requirement this record deviates from.
- §26 Git and CI — branch protection, exact-SHA policy, and the required checks that carry the
  compensating weight.
- §27 AI development rules — an agent never self-declares `GO` and never reports an unrun check.
- §34.7 Step 1 verification — governance validators, not application tests.

Related records: [DEC-0013](DEC-0013-exact-sha-evidence-before-go.md) (exact-SHA evidence);
[DEC-0016](DEC-0016-public-repository-visibility-accepted-deviation.md) (the precedent for recording an
accepted deviation); [`../ASSUMPTIONS.md`](../ASSUMPTIONS.md) AMENDMENT-0001.
