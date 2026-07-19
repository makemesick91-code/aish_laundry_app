# DEC-0016 — Public Repository Visibility Accepted Deviation

**ID:** DEC-0016
**Title:** Public Repository Visibility Accepted Deviation
**Status:** ACCEPTED
**Date:** 19 July 2026

---

## Context

The canonical facts for Aish Laundry App originally specified that the repository
`makemesick91-code/aish_laundry_app` would be **PRIVATE**.

During Step 0 that requirement collided with a second, equally canonical requirement: that the PR-only,
exact-SHA, no-force-push policy in [`../GIT_AND_RELEASE_POLICY.md`](../GIT_AND_RELEASE_POLICY.md) be
**enforced by the platform** rather than merely written down. GitHub's free plan cannot apply rulesets or
branch protection to a private repository. The limitation was verified directly: the attempt returned
**HTTP 403** with the message *"Upgrade to GitHub Pro or make this repository public"*.

The repository owner was presented with the tradeoff and elected **PUBLIC** visibility so that branch
protection could be applied. That election was recorded as **AMENDMENT-0001** in
[`../ASSUMPTIONS.md`](../ASSUMPTIONS.md), and the repository has operated as PUBLIC ever since, with
ruleset ID `19164588` active, zero bypass actors, and direct-push protection proven by a `GH013`
rejection.

AMENDMENT-0001 recorded the visibility **fact** honestly. It did not, however, do three things that the
repository now needs before Step 1 authors a large corpus of product and domain documentation:

1. It did not state that PRIVATE remains the **canonical desired end state**, so the deviation risked
   hardening into an unexamined default.
2. It did not enumerate, as binding rules, the **authoring constraints** that a public repository imposes
   on every future document, evidence pack, fixture, and example.
3. It did not record the **single-maintainer governance** consequence: there is no independent human
   reviewer, so the compensating controls must be named explicitly rather than assumed.

Step 1 produces requirements, personas, journeys, domain models, threat models, and acceptance criteria —
exactly the material where a plausible-looking example phone number, address, or token would leak into a
world-readable repository. The authoring constraints must be canonical **before** that corpus is written,
not retrofitted after.

---

## Decision

**The PUBLIC visibility of this repository is recorded as an explicitly accepted deviation from a
canonical requirement that remains PRIVATE — not as a silently normalised condition.**

The decision has five parts:

1. **Actual visibility is PUBLIC.** It is never described as private, anywhere, by anyone.
2. **Canonical desired visibility remains PRIVATE.** PUBLIC is a deviation accepted under a specific,
   recorded constraint (free-plan ruleset capability), not a product preference and not a security
   judgement that public is adequate.
3. **All repository content is treated as world-readable and permanently so.** Deletion is not
   remediation; anything committed must be assumed mirrored, cached, and indexed.
4. **The public-repository authoring constraints in the next section are binding** on every file in the
   repository, including documentation, evidence packs, test fixtures, and examples.
5. **Governance operates in single-maintainer mode.** Independent human approval is ABSENT. The
   compensating controls are the active ruleset, exact-SHA CI, deterministic validators, and recorded
   internal re-verification — and this is stated plainly rather than presented as peer review.

### Binding authoring constraints

The following must **never** be committed to this repository, in any file type, including documentation,
examples, fixtures, and evidence:

- customer data of any kind;
- real customer phone numbers, names, or addresses;
- photographs of customer laundry or premises;
- credentials, tokens, OTP values, or private keys;
- `.env` files or production configuration;
- database dumps or backups;
- sensitive server addresses or internal network topology;
- internal incident data containing personal data;
- raw authentication output;
- third-party provider secrets or billing credentials.

Positively stated:

- **Evidence packs are sanitised before commit** and state that sanitisation occurred.
- **Every example datum is fictional** and recognisably so.
- **Only `PUBLIC` and sanitised `INTERNAL` material is committed.** `CONFIDENTIAL`, `RESTRICTED`, and
  `SECRET` classes may be *described* and *modelled*, but never *instantiated* with real values.
- **A committed secret is compromised on push.** Rotation is the first action; removal is the second.

### Upgrade path

Moving the repository to PRIVATE in future requires: a paid plan or host that supports rulesets on
private repositories, a new decision record superseding this one, and **re-verification of the ruleset
after the visibility change** — because changing visibility can silently drop enforcement, which would
convert a governance improvement into a governance regression.

---

## Consequences

- The Master Source moves to **version 1.0.1**, adding the public-repository authoring constraints as
  canonical content (§15.8) and recording the deviation status (§21.6).
- `.claude/rules/00-canonical-source.md`, `03-security-and-privacy.md`, and `11-git-and-ci.md` gain the
  corresponding binding rules.
- Step 1 and every later Step author their documentation under these constraints from the first commit.
- The governance traceability matrix gains a row for this decision.
- AMENDMENT-0001 remains valid and unedited; this record supplements it rather than replacing it.

### Positive consequences

- The deviation is **visible and re-examinable** instead of becoming an unquestioned default. A future
  reader learns that PRIVATE was wanted, why it was not achieved, and what would restore it.
- The authoring constraints exist **before** the largest documentation corpus in the project is written,
  which is the only point at which they are cheap to apply.
- Branch protection is genuinely enforced by the platform, which is a stronger guarantee than a private
  repository with unenforced conventions would have provided.
- Single-maintainer governance is stated honestly, so no reader mistakes internal re-verification for
  independent peer review.
- Pricing and product decisions being public is turned from an accident into a deliberate, bounded
  position.

### Negative consequences / trade-offs

- **Commercial strategy, pricing, roadmap, and all decision records are readable by competitors.** This
  is a genuine competitive cost, accepted on the reasoning that the advantage is execution rather than
  secrecy of a price list that is published to customers anyway.
- **The blast radius of any committed secret is maximal and immediate.** A private repository would have
  offered a window between commit and exposure; this one offers none.
- **Authoring is slower and more constrained.** Every example must be invented rather than copied from
  reality, and every evidence pack must be sanitised before commit.
- **There is no independent reviewer.** Single-maintainer governance means a mistake that the maintainer
  and the validators both miss is not caught by a second human. The validators are therefore doing work
  that a reviewer would otherwise do, and their coverage is a real dependency.
- **The upgrade path carries its own risk.** Flipping to private later could silently disable the
  ruleset, so the change is not a one-line settings edit.

---

## Verification

This decision is verified by:

1. `python3 scripts/validate-decisions.py` — asserts DEC-0016 exists exactly once, carries status
   ACCEPTED, and contains all twelve mandated headings.
2. `python3 scripts/validate-required-files.py` — asserts the decision-record set is complete at sixteen
   records.
3. `python3 scripts/validate-master-source.py` — asserts the Master Source declares version **1.0.1** and
   that its recorded SHA-256 digest matches the file content, regenerated by tooling rather than
   hand-edited.
4. `bash scripts/validate-secrets.sh` — asserts no secret pattern is present in the tree.
5. `python3 scripts/validate-markdown-links.py` — asserts every internal link in this record resolves.
6. `bash scripts/verify-step-00.sh` — the aggregate governance gate, whose output is captured at the exact
   commit SHA under review per [DEC-0013](DEC-0013-exact-sha-evidence-before-go.md).

Verification of the *visibility fact itself* is external to the repository: the GitHub API reports the
repository as public and ruleset `19164588` as active with zero bypass actors. That observation is
recorded in the Step 0 evidence pack and is not re-asserted here as a repository-internal claim.

---

## Supersession policy

This record is superseded, never edited into a different decision. It would be superseded by a new record
that:

- moves the repository to PRIVATE on a plan or host that supports rulesets on private repositories, and
  records re-verification of the ruleset **after** the visibility change; or
- changes the authoring constraints; or
- replaces single-maintainer governance with independent review.

Any such record cites `DEC-0016` explicitly, and this record gains a supersession note pointing at its
replacement while keeping its content intact. The identifier `DEC-0016` is permanent and is never reused.

Relaxing an authoring constraint — for example, permitting a real phone number in a fixture — is a
product and security decision that requires a superseding record and a Master Source version bump. It is
never done by editing a validator.

---

## Related Master Source sections

- §1.2 Amendment procedure — version bump, checksum refresh, decision record.
- §1.3 Honesty rules — never claim the repository is private.
- §15 Security — credentials, tokens, secrets, and the no-secrets rule.
- §15.8 Public repository authoring constraints — added at version 1.0.1 by this decision.
- §17 Privacy — personal data held and the masking and consent rules.
- §21.6 Public visibility notice — the commercial consequence of PUBLIC visibility.
- §26 Git and CI — branch protection, exact-SHA policy, and the enforcement that PUBLIC visibility buys.
- §33.4 Never — never describe this repository as private.

Related registers and records: [`../ASSUMPTIONS.md`](../ASSUMPTIONS.md) AMENDMENT-0001;
[DEC-0009](DEC-0009-initial-commercial-pricing.md) (pricing is publicly visible);
[DEC-0013](DEC-0013-exact-sha-evidence-before-go.md) (exact-SHA evidence);
[`../../SECURITY.md`](../../SECURITY.md) (rotation-first remediation).
