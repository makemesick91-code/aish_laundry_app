# DEC-0028 — Step 4 Scope Resolution and Canonical Authorization

**ID:** DEC-0028
**Title:** Step 4 Scope Resolution and Canonical Authorization
**Status:** ACCEPTED
**Date:** 21 July 2026

---

## Context

Rule 49 states that Step 3 `GO` is not Step 4 authorization, and that Step 4 "begins only through a
separately authorised canonical Step 4 process". No such authorization existed until this record. This
record is that authorization, and it also resolves a scope conflict that surfaced at the moment Step 4
was about to begin.

Four facts drove this record.

1. **An execution brief proposed a Step 4 that the roadmap does not contain.** A sprint brief presented
   Step 4 as *"Domain, Branding, Environment, and SaaS Planning Foundation"* — product identity, domain
   and URL strategy, environment topology, configuration and secret contracts, multi-tenant SaaS
   planning, and subscription boundaries.

2. **The canonical roadmap says something different, in two places that agree with each other.**
   [`MASTER_SOURCE.md`](../MASTER_SOURCE.md) §24 records `Step 4 | Laundry Master Data`, and
   [`ROADMAP.md`](../ROADMAP.md) §"Step 4 — Laundry Master Data" enumerates its scope: customers,
   contacts, addresses and consent; services (kiloan, satuan, packages, add-ons); price lists per brand
   with historical price capture behaviour prepared; outlet master data; and staff and role assignment
   within a tenant. `scripts/validate-roadmap.py` additionally pins `4: "Laundry Master Data"` as a
   committed constant, so the title is machine-enforced, not merely written down.

3. **The two scopes are not variants of one another.** They share no aggregate, no table, no endpoint,
   and no acceptance criterion. Treating the brief as Step 4 would not have refined Step 4; it would
   have replaced it, and displaced Laundry Master Data to an unnamed later position.

4. **A repository-wide search for the brief's scope vocabulary returned nothing.** No canonical
   document, decision record, rule, or validator anywhere in this repository refers to a
   "Domain, Branding, Environment, and SaaS Planning" step. The brief was the sole source, and under
   the conflict-resolution order in Rule 00 a sprint brief is subordinate to the Master Source and to
   accepted decision records.

The governance question was therefore not "which scope is better" but "may an execution brief redefine
a locked roadmap entry". Master Source §24 states the roadmap is locked and that "Step numbers are
never reused or swapped without a decision record"; `CLAUDE.md` §3 repeats the lock and §9 places
roadmap numbering in the stop-and-ask set. The answer is no, and the conflict was escalated to the
repository owner rather than resolved by an agent.

## Options considered

**Option 1 — implement the brief under the label "Step 4".**
Rejected by the owner. It silently overwrites a locked roadmap entry, renumbers everything downstream
by implication, and leaves every prior citation of "Step 4" in the Master Source, the rules, the
validators, and three GO-tagged evidence packs pointing at a scope that no longer exists.

**Option 2 — formally renumber the roadmap so the brief becomes Step 4 and Laundry Master Data moves
to Step 5.**
Rejected by the owner. It is a legitimate mechanism, but it pays a large, permanent cost — every
downstream step number shifts — to accommodate a brief that no canonical document had asked for.

**Option 3 — keep the locked roadmap and withdraw the conflicting brief.**
**Adopted.** Step 4 remains Laundry Master Data. The brief's subject matter is not deleted; it is
recorded as unscheduled and left for a separate canonical decision, so nothing is lost and nothing is
smuggled in under a number that belongs to other work.

## Decision

1. **Step 4 is `Laundry Master Data`, exactly as recorded in Master Source §24 and `ROADMAP.md`.** Its
   scope is customer master data (identity, contacts, addresses, consent), service master data (kiloan,
   satuan, packages, add-ons), per-brand price lists, outlet master data, and staff and role assignment
   within a tenant.

2. **The brief titled "Domain, Branding, Environment, and SaaS Planning Foundation" is WITHDRAWN as a
   Step 4 brief.** It must not be implemented under this authorization, recorded as Step 4, aliased to
   Step 4, or assigned any other step number by an agent. Where its subject matter remains relevant it
   is carried as `UNSCHEDULED / REQUIRES SEPARATE CANONICAL DECISION` and nothing further.

3. **No roadmap number changes.** Steps 4–14 keep their existing numbers and titles. No step is reused,
   renumbered, swapped, merged, or split by this record.

4. **Step 4 is authorized to start.** The repository owner conferred the separate canonical
   authorization Rule 49 requires, on 21 July 2026, against the verified baseline
   `1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`. Work proceeds on
   `feature/step-04-laundry-master-data`, targeting `main` by pull request.

5. **Authorization to start is not a status.** Step 4 moves to `IN PROGRESS`; it does not acquire
   `TESTED`, `WATCH`, or `GO` from this record. `GO` remains owner-conferred against exact-SHA evidence
   (Rule 01, DEC-0013), and nothing in this record confers it in advance.

6. **This record authorizes Step 4 scope only.** It does not authorize Step 5+ business features, and
   it does not authorize deployment. Deployment remains `ABSENT` and is not made reachable by any part
   of this record.

## Consequences

The locked roadmap survived a conflicting brief without being edited to accommodate it, and Step 4 has
the explicit authorization Rule 49 required before any of its work could legitimately begin.

### Positive consequences

- Every existing citation of "Step 4" across the Master Source, the rules, the validators, and the
  Step 0–3 evidence packs continues to mean what it meant when it was written.
- The precedent is recorded explicitly: an execution brief does not outrank the Master Source, and a
  scope conflict is escalated rather than absorbed.
- The withdrawn subject matter is preserved as an open item rather than discarded, so the owner can
  schedule it deliberately instead of rediscovering it.

### Negative consequences / trade-offs

- The withdrawn scope contains work that is genuinely needed before deployment — domain strategy,
  environment topology, and configuration and secret contracts among it. Declining to smuggle it into
  Step 4 means it stays unscheduled until the owner schedules it, and this record does not pretend
  otherwise.
- Step 4 is materially larger than the withdrawn brief, because master data is real runtime with real
  migrations rather than planning documentation. Authorizing it accepts that cost.
- `UNSCHEDULED / REQUIRES SEPARATE CANONICAL DECISION` is an honest label, not a plan. Nothing in this
  record commits the owner to ever scheduling that work.

## Verification

Verified at baseline `1eff6f1c57e2b6032bdf54e0feef22b0fc58e95d`, on `main`, working tree clean,
`origin/main` confirmed identical by `git fetch --all --tags --prune`:

- `docs/MASTER_SOURCE.md` §24 line 1244 reads `| Step 4 | Laundry Master Data | PLANNED |`.
- `docs/ROADMAP.md` lines 135–143 enumerate the canonical Step 4 scope reproduced in this record.
- `scripts/validate-roadmap.py` pins `CANONICAL_TITLES[4] = "Laundry Master Data"`.
- A repository-wide `grep` for the withdrawn brief's scope vocabulary returned zero matches, confirming
  the brief had no canonical basis anywhere in the tree.
- `bash scripts/verify-step-03.sh` at that commit reported **38 passed, 0 failed, 2 skipped**. The two
  skips are named and are not reported as passes: the DEC-0026 scaffolding suite (exit-78 precondition
  skip on `main`, by design) and the Flutter gates (Flutter not on `PATH` in that environment).

The owner's authorization is the message that accompanied it; this record is its canonical form. No
part of this record's verification is a claim about Step 4 implementation, which had not begun when it
was written.

## Requirement references

No product requirement is created, changed, or withdrawn by this record. It resolves a process conflict
and confers a start authorization. Step 4's own requirement set is recorded separately under
`docs/product/` and `docs/quality/`.

## Threat references

The governance risk this record addresses is scope substitution: a locked roadmap entry being replaced
by an unreviewed brief, leaving every prior citation of that entry silently wrong. Related to the
status-drift class recorded in DEC-0027 and DEC-0029 — in all three the defect is a canonical document
saying something the rest of the repository contradicts.

## Rule references

- Rule 00 — canonical source and conflict-resolution order.
- Rule 01 — status vocabulary; `GO` is owner-conferred and never self-declared.
- Rule 12 — autonomous execution; roadmap numbering is stop-and-ask.
- Rule 16 — a requirement that exists only in a brief does not exist.
- Rule 36 — runtime scope; Step 4+ features remain guarded until separately authorized.
- Rule 42 — Step 4+ business features remain `NOT IMPLEMENTED` until built and evidenced.
- Rule 49 — Step 4 begins only through a separately authorised canonical Step 4 process.

## Supersession policy

This record is superseded only by a later accepted decision record that names it explicitly. A future
record may schedule the withdrawn subject matter under its own step number or as out-of-band work; doing
so does not retroactively make it Step 4. Amending Step 4's canonical scope requires a decision record,
a Master Source version bump, and a regenerated checksum (Rule 00), never a validator edit or an
execution brief.

## Related Master Source sections

- §1 — canonical rules and conflict order.
- §24 — Roadmap (locked), and the Step 4 entry specifically.
- §25 — Definition of Done.
- §31 — Decision records.
- §32 — Changelog.
