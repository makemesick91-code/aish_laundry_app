# Rule 49 — Current Step 3 Status

## Purpose

To hold one honest statement of what Step 3 is, what exists so far, and — most importantly — what still
does not exist, so that a runtime foundation in progress is never mistaken for a finished, verified
product.

Canonical status: [`../../docs/STATUS.md`](../../docs/STATUS.md). Master Source version **1.4.0**.

## Status snapshot

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | **GO WITH ACCEPTED DEVIATION** |
| Step 1 — Product Requirement and Domain Model | **GO WITH ACCEPTED DEVIATION** |
| Step 2 — Design System and UX Foundation | **GO WITH ACCEPTED DEVIATION** |
| Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC | **GO WITH ACCEPTED DEVIATION** |
| Step 4 — Laundry Master Data | **IN PROGRESS** (started under DEC-0028; see [Rule 50](50-current-step-04-status.md)) |
| Steps 5–14 | **PLANNED** |
| PostgreSQL runtime foundation | **PRESENT** |
| Redis runtime foundation | **PRESENT** |
| Backend runtime | **PRESENT — STEP 3 FOUNDATION ONLY** |
| Flutter workspace | **PRESENT** |
| Application CI | **ACTIVE** |
| Deployment | **ABSENT** |
| UAT | **NOT STARTED** |
| All product business features | **NOT IMPLEMENTED** |

**This table was stale and is corrected under DEC-0027.** It previously declared backend runtime
`ABSENT`, Flutter workspace `ABSENT`, Application CI `NOT APPLICABLE UNTIL REAL RUNTIME WORKFLOWS
EXIST`, and Android toolchain `PREPARED — BUILDS NOT YET VERIFIED`. Each had stopped being true:
`backend/composer.json`, the root `pubspec.yaml`, and the three Step 3 runtime workflows all exist. A
status file that asserts an absence which the filesystem contradicts is not a conservative status
file — it is a false claim under Rule 01, and `scripts/validate-status.py` now cross-checks these
claims against the filesystem in both directions so the same drift cannot recur silently.

**Step 3 is now `GO WITH ACCEPTED DEVIATION` and GO-tagged.** The owner conferred `GO` against
exact-SHA evidence after Step 3 runtime, authentication, multi-tenancy, and RBAC were built, verified,
and merged to `main`. The immutable annotated tag
`aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` (object
`8b37230ed8df8da343a1546fd949d8a41329fbdf`) peels to the runtime merge SHA
`0e2554338812b05eba8411afeb099212b05f9761` — **never** to the later post-tag evidence commit
`ad31473da8376e91b67449bf7820ab9877ea8a4a`. The full closure evidence is
[`../../evidence/step-03/STEP-03-GO-CLOSURE.md`](../../evidence/step-03/STEP-03-GO-CLOSURE.md). The tag
is immutable and must never be moved, deleted, recreated, or retargeted.

**`GO` was conferred by the repository owner, never self-declared by an agent** (Rule 01). It carries
the accepted deviations recorded below and does **not** upgrade any narrower claim: runtime presence is
never proof of runtime correctness (Rule 36, hard rule 6), Android artefacts remain debug-only, and no
performance, deployment, or UAT result is asserted. **`GO WITH ACCEPTED DEVIATION` is not an
unqualified `GO`.** The deviations are DEC-0017 (single-maintainer governance; no independent human
review) and DEC-0026 (the scaffold-authorization suite runs 38/38 only on a Step 3 feature branch; on
`main` and in a fresh clone it is a visible exit-78 SKIP by owner-approved branch/path pin, never
represented as PASS), together with the debug-only runtime limitations.

**Step 3 `GO` did not start Step 4 and does not authorise deployment.** Step 4 began only when the
repository owner conferred the separate canonical authorization this rule required, recorded as
[DEC-0028](../../docs/decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md) on
21 July 2026. Step 4 is now `IN PROGRESS`; its business features remain `NOT IMPLEMENTED` until built
and evidenced, and deployment remains `ABSENT`. Step 4 starting does not authorise Step 5, and it does
not authorise deployment.

This paragraph previously read "Step 4 remains `PLANNED / NOT STARTED`". That was true when written
and stopped being true at DEC-0028; it is corrected here rather than left to contradict the roadmap
(Rule 01, DEC-0029).

## The permitted accessibility wording for Step 3

Where a Step 3 runtime shell has had its accessibility foundation exercised, the only permitted wording
is:

**RUNTIME ACCESSIBILITY FOUNDATION TESTED FOR SHELLS — FULL ASSISTIVE-TECHNOLOGY AND WCAG AUDIT NOT YET
COMPLETED.**

This is distinct from, and does not upgrade, the Step 2 wording (Rule 27, Rule 35): **DESIGNED TO MEET
WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.** Neither wording may be shortened, softened, or
presented as a completed conformance audit (Rule 01, Rule 41).

## What Step 3 authorizes

Per **DEC-0024**, Step 3 is the first canonical step authorized to introduce runtime: a Flutter
workspace, a Laravel backend, PostgreSQL, Redis, and runtime CI, confined to the approved roots fixed in
Rule 36. It is authorized to build authentication, multi-tenancy, and RBAC foundation. It is **not**
authorized to build POS, orders, payments, production operations, tracking, delivery, the unclaimed
laundry reminder ladder, finance and reporting, or subscription and platform administration — those
remain Step 4 and later (Rule 42).

## What Step 3 does not authorize

Deployment remains `ABSENT` and is not authorized by DEC-0024 (Rule 36, hard rule 2; Rule 49 status
table above). Step 4+ business features remain forbidden regardless of runtime presence (Rule 36, hard
rule 4). Runtime presence is never proof of runtime correctness (Rule 36, hard rule 6) — the `classify`
required check reports scope classification only and executes no application test.

## Adversarial validator evidence

`scripts/validate-runtime-scope.py` was adversarially tested by `scripts/test-step-03-validators.sh`
against **31 deliberate violations and 5 legitimate Step 3 cases**, with the working tree verified
byte-identical before and after. **Result: 36/36 expectations met** (31 forbidden cases correctly
rejected, 5 legitimate cases correctly accepted), as recorded in DEC-0024's verification section.

**An earlier reported figure of "31/31 mutations caught" is SUPERSEDED — INVALID ASSURANCE RESULT and
must never be cited.** That earlier harness embedded literal secret fixtures that tripped the guard
inside every sandbox regardless of whether the intended mutation actually ran; mutations appeared caught
when the mutation setup had in fact failed with a shell syntax error and never executed. The harness was
corrected to assemble fixtures at runtime and fail loudly on setup error. Citing the superseded 31/31
figure as current assurance is a false claim under Rule 01, regardless of how authoritative it once
sounded.

## Governance and evidence facts

1. **Exact-SHA evidence is mandatory** for every verification claim made in Step 3, exactly as in every
   prior step (Rule 01, DEC-0013, Rule 47).
2. **Prior `GO` tags are immutable.** The Step 0, Step 1, and Step 2 `GO` tags are never moved, deleted,
   or re-pointed by anything done in Step 3. The historical absence guard remains the validator for
   those tags; the new scope guard is never applied to them retroactively (Rule 36, Rule 11).
3. **The destructive-operations guard is never disabled or weakened** to accommodate Step 3 work.
   Introducing runtime does not create an exception to `.claude/hooks/guard-destructive-operations.sh`;
   if it blocks something believed legitimate, escalate to the owner rather than editing the guard (Rule
   12 of `CLAUDE.md`, `.claude/rules/12-autonomous-execution.md`).
4. **Public repository safety is unchanged by runtime introduction.** This repository remains `PUBLIC`,
   an accepted deviation from a canonical desired `PRIVATE` (AMENDMENT-0001, DEC-0016). Every runtime
   artefact — seed, fixture, configuration, log — follows the same authoring constraints as every
   documentation artefact before it (Rule 23, Rule 45).
5. **Single-maintainer governance is unchanged.** Independent human approval remains `ABSENT` (DEC-0017).
   The compensating controls — the active ruleset, exact-SHA CI, deterministic and adversarially tested
   validators, and recorded internal re-verification — are load-bearing, not supplementary, and are
   never described as independent peer review.

## Step boundary

**Step 3 has `GO`, and Step 4 has since started under its own separate canonical authorization
([DEC-0028](../../docs/decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md)).**
Step 3 `GO` was never Step 4 authorization and did not become it; the two are independent, and the
Step 4 status snapshot is [Rule 50](50-current-step-04-status.md). Laundry master data, service
catalog, and pricing implementation belong to Step 4 and are built there — not pulled forward on the
strength of the Step 3 tag, and not deferred out of it. Step 5+ work is not pulled forward on the
strength of DEC-0028. Step numbers are locked and are never reused, renumbered, swapped, merged, or
split without an accepted decision record (Master Source §24) — a constraint DEC-0028 applied when it
rejected a brief that would have redefined Step 4.

## Maintenance

1. This snapshot is updated only when reality changes, alongside `docs/STATUS.md`.
2. Statuses move forward on **exact-SHA evidence** only (Rule 01).
3. Use the approved status vocabulary only: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`,
   `NOT IMPLEMENTED`, `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`, `GO`. No synonyms, no
   softening adjectives. Compound qualifiers used above (`GO WITH ACCEPTED DEVIATION`, `PRESENT — STEP 3
   FOUNDATION ONLY`) qualify an approved base status; they never replace it or invent a new base status.
   A qualifier that narrows a claim is acceptable; one that implies progress the evidence does not
   support is not, which is why `IN PROGRESS — PHASE A COMPLETE` was withdrawn under DEC-0027.
4. If another document contradicts this snapshot, the other document is wrong — unless the Master
   Source itself has moved, in which case this file is updated to match it.

## Violation handling

- **Any claim that Step 3 delivered a working feature, a tested authentication flow, a verified tenancy
  boundary, a deployment, or a UAT result** — correct it immediately and visibly, and state that the
  earlier claim was wrong (Rule 01).
- **The superseded "31/31 mutations caught" figure cited as current assurance** — correct it immediately;
  cite the current **36/36** result and the fact that the earlier figure is invalid, exactly as this rule
  states.
- **An accessibility claim stronger than the permitted Step 3 wording** — correct it to the exact
  permitted wording above (Rule 27, Rule 41).
- **Any runtime artefact created outside the approved roots, or any Step 4+ feature detected** — remove
  it and report the scope breach (Rule 36).
- **`GO` written for Step 3 by an agent without a recorded owner authorization** — revert the wording;
  `GO` is the owner's to confer. Step 3's `GO WITH ACCEPTED DEVIATION` was owner-conferred against
  exact-SHA evidence and recorded in `evidence/step-03/STEP-03-GO-CLOSURE.md`.
- **Step 3 reverted to `IN PROGRESS` or `PLANNED` after the GO tag exists** — that is a false
  understatement; the GO tag is immutable and the status is `GO WITH ACCEPTED DEVIATION`.
  `scripts/validate-status.py` fails closed on it.
- **A status advanced without exact-SHA evidence** — revert the advancement.
- **A prior `GO` tag moved, deleted, or re-pointed** — record the incident, restore the original target
  if possible, and publish a corrective tag (Rule 11).
- **The destructive-operations guard edited, disabled, or bypassed to unblock Step 3 work** — treat as a
  serious autonomy breach; restore the guard and disclose (Rule 12).
- **This repository described as private, or PUBLIC presented as the desired end state** — correct it
  immediately, citing AMENDMENT-0001 and DEC-0016 (Rule 23).
