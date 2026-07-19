# Assumptions and Amendments — Aish Laundry App

Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md)
Baseline date: 19 July 2026

This register records assumptions made during governance and implementation, and amendments where reality
required a canonical fact to change. Entries are honest records, not justifications. An entry is never
deleted; it is resolved, rejected, or superseded, and it keeps its identifier forever.

---

## Register

| ID | Subject | Status |
| --- | --- | --- |
| ASSUMPTION-0001 | Local directory name versus remote repository name | RESOLVED / ACCEPTED |
| AMENDMENT-0001 | Repository visibility | ACCEPTED — visibility is PUBLIC; locked by DEC-0016 |

---

## ASSUMPTION-0001 — Local directory name versus remote repository name

**Status: RESOLVED / ACCEPTED**
**Date: 19 July 2026**

### Assumption

The local directory `aish_laundry` is the local monorepo root, while the remote repository name remains
`aish_laundry_app`.

### Context

The working tree on the development machine lives at `/home/fikri/Projects/aish_laundry`, but the GitHub
repository is `makemesick91-code/aish_laundry_app` with default branch `main`. A mismatch between a local
directory name and a remote repository name is ordinarily harmless, but it can cause confusion in
documentation, in scripts that assume the directory name equals the repository name, and in evidence packs
that record paths.

### Resolution

The mismatch is accepted deliberately rather than being "fixed" by renaming either side:

- `aish_laundry` is canonically the **local monorepo root directory name**.
- `aish_laundry_app` is canonically the **remote repository name**.
- **Aish Laundry App** remains the official product name in all prose (DEC-0001).

### Consequences

- Documentation refers to the repository by its remote name where the remote is meant, and by path where
  the local tree is meant.
- Scripts must not derive the repository name from the containing directory name.
- Evidence packs may contain absolute local paths; those are sanitised of anything sensitive but the
  directory name itself is not sensitive.
- No further action is required. This assumption is closed.

### Related

DEC-0001; [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §1.4.

---

## AMENDMENT-0001 — Repository visibility

**Status: ACCEPTED**
**Date: 19 July 2026**
**Amends: the canonical facts statement that the repository would be PRIVATE**

### What the canonical facts originally specified

The canonical facts specified that the repository would be **PRIVATE**.

### What actually happened

GitHub's free plan **cannot apply rulesets or branch protection to private repositories**. The limitation
was verified directly: an attempt to apply protection returned **HTTP 403** with the message
*"Upgrade to GitHub Pro or make this repository public"*.

This created a direct conflict between two governance requirements:

- keep the repository private, and
- enforce branch protection on `main` so that the PR-only, exact-SHA, no-force-push policy in
  [`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md) is actually enforced by the platform rather
  than merely written down.

### Decision

The repository owner was presented with the tradeoff and **explicitly elected PUBLIC visibility** so that
branch protection could be enforced.

**Repository visibility is therefore PUBLIC, by deliberate decision of the owner.**

### Consequence recorded honestly

**Commercial pricing and product decisions in this repository are publicly visible.** This includes the
locked pricing in [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §21, the roadmap, the positioning, the
non-goals, and all fifteen decision records.

Further consequences:

- Any credential committed to this repository must be treated as compromised the instant it is pushed.
  Rotation is the only remediation ([`../SECURITY.md`](../SECURITY.md)).
- No customer data, no real phone numbers, and no tenant-identifying information may ever be committed,
  including in test fixtures and evidence packs.
- Security reports must use private vulnerability reporting, never public issues.
- Competitors can read the commercial strategy. This was weighed and accepted; the product's advantage is
  execution, not secrecy of a price list that is published to customers anyway.

### Explicit prohibition

**Do not claim the repository is private anywhere** — not in documentation, not in a pull request
description, not in an evidence pack, not in a report to the owner. Any statement that this repository is
private is false and must be corrected immediately.

### Alternatives that were considered and rejected

| Alternative | Why rejected |
| --- | --- |
| Keep the repository private without branch protection | Governance policy would be unenforced; the honesty and exact-SHA rules would rest on convention alone. |
| Upgrade to a paid GitHub plan | Adds recurring cost at the foundation stage before any revenue; the owner chose not to. |
| Move to a different hosting platform | Disproportionate change for a solvable tradeoff; would delay Step 0. |
| Split into a public governance repository and a private code repository | Breaks the monorepo decision and splits the traceability chain across two histories. |

### Review condition

If the repository is later moved to a paid plan or a different host, visibility may be revisited through a
new amendment and, if it changes any canonical statement, a decision record and a Master Source version
bump.

### Locked by a decision record

This amendment recorded the visibility **fact**. It is now locked, and extended, by
[**DEC-0016 — Public Repository Visibility Accepted Deviation**](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md),
which additionally records three things this amendment did not:

1. **PRIVATE remains the canonical desired visibility.** PUBLIC is an accepted deviation with a defined
   upgrade path, not a settled preference.
2. **The binding public-repository authoring constraints** now canonical in
   [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §15.8 — what may never be committed, that every example datum
   is fictional, and that evidence packs are sanitised.
3. **Governance operates in single-maintainer mode**, with independent human approval ABSENT and the
   compensating controls named explicitly.

This amendment is not superseded and its text is unedited above; DEC-0016 supplements it.

### Related

[`MASTER_SOURCE.md`](MASTER_SOURCE.md) §15.8, §21.6, §33.4; [`../SECURITY.md`](../SECURITY.md);
[`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md);
[DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md).

---

## How to add an entry

1. Use the next free identifier: `ASSUMPTION-NNNN` for something believed but unverified,
   `AMENDMENT-NNNN` for a change to a previously canonical fact.
2. Record the context honestly, including what was originally specified and what actually happened.
3. State the status using the vocabulary in
   [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md) where applicable, or one of
   `OPEN`, `RESOLVED / ACCEPTED`, `REJECTED`, `SUPERSEDED`.
4. Record the consequences, including uncomfortable ones.
5. If the entry changes a canonical product decision, also create a decision record in
   [`decisions/`](decisions/) and bump the Master Source version.
6. Never delete an entry.
