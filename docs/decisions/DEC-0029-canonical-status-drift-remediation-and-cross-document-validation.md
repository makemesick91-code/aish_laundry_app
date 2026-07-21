# DEC-0029 — Canonical Status Drift Remediation and Cross-Document Validation

**ID:** DEC-0029
**Title:** Canonical Status Drift Remediation and Cross-Document Validation
**Status:** ACCEPTED
**Date:** 21 July 2026

---

## Context

Two canonical documents asserted things the rest of the repository contradicted. Both were found during
the Step 4 baseline verification required by DEC-0028, at commit
`1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`, and both had survived a full `verify-step-03.sh` run
reporting 38 passed and 0 failed — which is the part that matters. Neither defect was caught, because
in each case nothing was looking.

### Defect A — the Master Source roadmap table understated two GO steps

`docs/MASTER_SOURCE.md` §24 declared:

```
| Step 2 | Design System and UX Foundation                   | IN PROGRESS |
| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC   | PLANNED     |
```

Every other canonical record disagreed, and they agreed with each other:

- `docs/ROADMAP.md` declares Step 2 and Step 3 `GO WITH ACCEPTED DEVIATION`.
- `docs/STATUS.md`'s machine-readable block declares `STEP_02_STATUS=GO` and `STEP_03_STATUS=GO`.
- Two immutable annotated `GO` tags exist and were verified live:
  `aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go` (object
  `d02598b1e3a43db0ebfb6217d7e1d9ddf8484c3a`, peeling to
  `47c07d360e8802fd78f61d41435cae3f28313137`), and
  `aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` (object
  `8b37230ed8df8da343a1546fd949d8a41329fbdf`, peeling to
  `0e2554338812b05eba8411afeb099212b05f9761`).

The Master Source also contradicted **itself**: §24 called Step 3 `PLANNED` while §32's own 1.4.0
changelog entry describes Step 3 runtime introduction as delivered.

This is a false claim under Rule 01 in the understating direction. Understatement is not the safe
direction — it is the direction that makes the canonical document disagree with an immutable tag, and
under Rule 00 the canonical document is what every other layer is supposed to defer to.

**The validator gap that allowed it:** `scripts/validate-roadmap.py` reads `docs/ROADMAP.md` and
nothing else. No validator had ever parsed the Master Source's own §24 roadmap table. The most
authoritative roadmap statement in the repository was the only one not machine-checked.

### Defect B — STATUS.md contradicted itself about infrastructure

`docs/STATUS.md` §2 declared `PostgreSQL runtime foundation | PRESENT` and
`Redis runtime foundation | PRESENT`. Four sections later, §6 declared `Database | ABSENT`,
`Redis | ABSENT`, and `Local development runtime | ABSENT`.

Executed evidence at the same commit refutes the §6 rows: `bash scripts/verify-step-03.sh` reported
`PASS PostgreSQL and Redis reachable`, `PASS migrate:fresh --seed`, `PASS migrate:rollback`, and
`PASS migrate re-apply`. `infrastructure/docker-compose.dev.yml` is committed and defines both
services.

The two tables were written for different purposes — §2 for runtime foundations, §6 for deployed
environments — but neither said so, so they read as a direct contradiction on the same screen. A reader
cannot tell which is true, and a reader who picks the wrong one is misled by a canonical document.

**The validator gap that allowed it:** `scripts/validate-status.py` cross-checks backend runtime,
Flutter workspace, deployment, and Application CI claims against the filesystem, but it had no check
that two tables *within the same document* agree, and no check tying a PostgreSQL or Redis claim to
`infrastructure/docker-compose.dev.yml`.

### The shared lesson

Both defects are the same shape as DEC-0027: a canonical document asserting a state that a committed
artefact refutes, passing every gate because no gate looked. Correcting the two documents without
closing the two validator gaps would leave the repository in exactly the condition that produced them.

## Options considered

**Option 1 — correct the two documents and stop.**
Rejected. It fixes today's wording and leaves the detection gap open. The same drift recurs the next
time a step closes, which is precisely how Defect A survived the whole of Step 3.

**Option 2 — correct the documents and add validators that assert the desired wording.**
Rejected. A validator that greps for a success phrase passes on a document that says the right words
and means nothing, and it has to be rewritten every time the prose changes. It also could not have
detected Defect B, where both statements were individually well-formed and only their conjunction was
wrong.

**Option 3 — correct the documents, and add fail-closed validators that compare independent sources
against each other and against the filesystem.**
**Adopted.** The checks are negative and comparative: they look for a disagreement between the Master
Source roadmap table, `STATUS.md`'s machine block, and the real Git tags, and for an infrastructure
subject declared both present and absent without an environment qualifier that distinguishes the two
claims. Both are backed by adversarial fixtures that must fail before the checks are trusted.

## Decision

1. **Master Source §24 is corrected** so that Step 2 and Step 3 read `GO WITH ACCEPTED DEVIATION`,
   matching `ROADMAP.md`, `STATUS.md`, and the two immutable tags. **No step number, title, or scope is
   changed**, and no other row is touched.

2. **`STATUS.md` §6 is corrected** so that each environment row states the environment it describes and
   the evidence class behind it, using qualifiers on approved base statuses rather than a bare word that
   silently contradicts §2. Local development PostgreSQL and Redis are recorded as present and verified
   **for local development only**; staging, production, object storage, and the deployment pipeline
   remain `ABSENT` or `NOT CONFIGURED`, because none of them exists.

3. **No local or CI verification is ever reported as production infrastructure.** A row that describes
   a locally verified service says so in the row itself. Deployment remains `ABSENT`.

4. **Two fail-closed validator checks are added**, and neither is trusted until an adversarial fixture
   has shown it fail:
   - `scripts/validate-roadmap.py` gains a cross-source check that parses the Master Source §24 table
     and requires each step's status to agree with `STATUS.md`'s machine-readable block, with
     `GO WITH ACCEPTED DEVIATION` normalised to the base status `GO`; when a real tag is present in the
     checkout, a step declared `GO` must have its `GO` tag, and a step **not** declared `GO` must not.
   - `scripts/validate-status.py` gains an infrastructure-consistency check that fails when one
     infrastructure subject is declared both present and absent without an environment qualifier
     distinguishing the two rows, and that cross-checks PostgreSQL and Redis claims against
     `infrastructure/docker-compose.dev.yml`.

5. **The Master Source version is raised 1.4.0 → 1.4.1 (PATCH)** and its checksum regenerated by the
   repository's own tooling from the final file content. PATCH is the correct class under Rule 00: no
   product decision, pricing figure, roadmap number, hierarchy level, reminder stage, or architectural
   lock changes. Two statements of fact are corrected to match evidence that already existed.

6. **This record corrects statements about Step 2 and Step 3; it does not re-open, re-confer, or
   re-verify either step.** Both `GO` tags remain immutable and untouched. Correcting a stale sentence
   about a closed step is not a re-closure of that step.

## Consequences

The canonical documents now agree with the immutable tags and with the filesystem, and the two blind
spots that let them disagree are machine-checked.

### Positive consequences

- The Master Source's own roadmap table is validated for the first time.
- A future step closure that updates `ROADMAP.md` and `STATUS.md` but forgets §24 now fails CI instead
  of shipping.
- Self-contradiction inside `STATUS.md` is detectable rather than requiring a human to read two tables
  four sections apart and notice.
- The corrected `STATUS.md` §6 is more informative than the version it replaces: it distinguishes what
  is verified locally from what does not exist, which the original could not express at all.

### Negative consequences / trade-offs

- The new cross-source check couples `validate-roadmap.py` to `STATUS.md`'s machine block. A malformed
  block now fails two validators rather than one. This is deliberate — fail-closed duplication is the
  point — but it is real added coupling.
- The infrastructure-consistency check works from a committed list of infrastructure subjects. A future
  subject not on that list is unchecked until it is added, so the check reduces the blind spot rather
  than eliminating it, and this record says so rather than overclaiming.
- Environment-qualified statuses are longer and less scannable than a bare `ABSENT`. Precision is worth
  the verbosity here, but it is a cost.
- These corrections consume a Master Source version and a decision record for what is, in content
  terms, four table rows. That overhead is the governance model working as designed, not a defect.

## Verification

All findings and all corrections are bound to commit `1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d` as the
baseline, on `feature/step-04-laundry-master-data`, branched from that exact SHA with `origin/main`
confirmed identical.

Observed **before** correction, at that commit:

- `docs/MASTER_SOURCE.md` §24 declared Step 2 `IN PROGRESS` and Step 3 `PLANNED`.
- `docs/STATUS.md` §2 declared PostgreSQL and Redis `PRESENT`; §6 declared Database and Redis `ABSENT`.
- `git rev-parse` confirmed both `GO` tag objects and both peeled commits, as quoted in Context.
- `bash scripts/verify-step-03.sh` reported **38 passed, 0 failed, 2 skipped** — both defects present
  and undetected. The two skips are named in that output and are not counted as passes.

The post-correction verification result, the adversarial-fixture result for both new checks, and the
regenerated checksum are recorded in the Step 4 evidence pack under `evidence/step-04/`, each bound to
the exact commit it was produced from (Rule 01, DEC-0013). This record does not quote a post-correction
figure it did not produce; the evidence pack carries it.

## Requirement references

No product requirement is created, changed, or withdrawn. This record corrects status statements and
adds governance validation. Requirement identifiers, pricing, the tenant hierarchy, and the reminder
ladder are untouched.

## Threat references

Governance integrity, in the same class as DEC-0027: a canonical document asserting a state that a
committed artefact refutes, and passing every gate because no gate compared the two. Understatement is
treated as the same severity as overstatement — a canonical document that disagrees with an immutable
tag is wrong in either direction.

## Rule references

- Rule 00 — Master Source version bump and checksum discipline; a lower layer never overrides it.
- Rule 01 — status vocabulary; a claim contradicted by evidence is a false claim.
- Rule 11 — tags are immutable; nothing here moves, deletes, or re-points a tag.
- Rule 15 — canonical status snapshot maintenance.
- Rule 33 / Rule 47 — adversarial validator testing before a gate is relied upon.
- Rule 49 — Step 3 status, and the standing prohibition on understating it after the GO tag exists.

## Supersession policy

Superseded only by a later accepted decision record naming it explicitly. The corrected statuses
themselves are not frozen: they move forward through the ordinary canonical process, on exact-SHA
evidence, in a pull request that updates the Master Source, `ROADMAP.md`, and `STATUS.md` together.
Weakening or removing either new validator check requires a superseding record stating what replaces
the assurance; it is never done by editing the validator (Rule 00, Rule 23).

## Related Master Source sections

- §1 — canonical rules, version and checksum discipline.
- §24 — Roadmap, the table corrected by this record.
- §25 — Definition of Done.
- §31 — Decision records.
- §32 — Changelog, where the 1.4.1 entry is recorded.
