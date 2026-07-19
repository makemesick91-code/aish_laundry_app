# Rule 23 — Public Repository Safety

## Purpose

This repository is **PUBLIC**. Every file in it is world-readable, permanently, by anyone. This rule
turns that fact into concrete authoring constraints so that a plausible-looking example never becomes a
real disclosure.

Backed by **AMENDMENT-0001** and
[**DEC-0016 — Public Repository Visibility Accepted Deviation**](../../docs/decisions/DEC-0016-public-repository-visibility-accepted-deviation.md).
Canonical content: Master Source §15.8.

## The deviation, stated correctly

1. **Actual visibility is PUBLIC.** Never describe this repository as private — not in documentation, a
   pull request, an evidence pack, a commit message, or a report.
2. **Canonical desired visibility remains PRIVATE.** PUBLIC is an *accepted deviation*, taken because
   GitHub's free plan cannot apply rulesets or branch protection to a private repository (verified
   HTTP 403). It is not a security judgement that public is adequate, and it is not a preference.
3. **Describing PUBLIC as the desired end state is also wrong**, and is corrected the same way as
   describing the repository as private.
4. **Deletion is not remediation.** Anything committed must be assumed mirrored, cached, and indexed from
   the moment it is pushed. A secret is compromised on push; **rotation is the first action**, removal
   the second (Rule 03).

## Never commit

5. Customer data of any kind.
6. Real customer phone numbers, names, or addresses.
7. Photographs of customer laundry or customer premises.
8. Credentials, tokens, OTP values, or private keys.
9. `.env` files or production configuration.
10. Database dumps or backups.
11. Sensitive server addresses or internal network topology.
12. Internal incident data containing personal data.
13. Raw authentication output.
14. Third-party provider secrets or billing credentials.

This applies to **every file type**, without exception: documentation, examples, test fixtures, seed
data, evidence packs, commit messages, issue text, and pull request descriptions.

## Always

15. **Every example datum is fictional** and recognisably so. Invent examples; never copy one from a real
    customer, a real device, a real log, or a real screenshot.
16. **Evidence packs are sanitised before commit**, and they state that sanitisation occurred (Rule 01).
17. **Only `PUBLIC` and sanitised `INTERNAL` material is committed.** `CONFIDENTIAL`, `RESTRICTED`, and
    `SECRET` classes may be described and modelled, never instantiated with real values (Rule 21).
18. **Pricing text must be accurate at all times.** Commercial figures are publicly readable, so drift is
    a commercial risk rather than a typo (Rule 14).
19. **Assume a hostile reader.** Competitors, scrapers, and automated secret-hunters read this repository
    continuously. Write as though they do, because they do.

## Single-maintainer governance

20. **Independent human approval is `ABSENT`.** Governance operates in single-maintainer mode.
21. The compensating controls are: the active ruleset, exact-SHA CI, deterministic validators, and
    recorded internal re-verification.
22. **Never describe internal re-verification as independent peer review**, and never describe a
    self-review as an approval. Where a report would normally say "reviewed", it says
    "internally re-verified under single-maintainer governance".

## Changing visibility

23. Repository visibility is **owner territory** and is never changed by an agent (Rule 11, Rule 12).
24. A future move to PRIVATE requires a superseding decision record **and re-verification of the ruleset
    after the change**, because changing visibility can silently drop enforcement — turning a governance
    improvement into a governance regression.

## Violation handling

- **A secret committed** — treat as compromised immediately. **Rotate first**, then remove, then
  disclose to the owner. Removal without rotation is not a fix and must never be reported as one.
- **Real personal data committed** — automatic **NO-GO** (Rule 03). Stop, disclose completely, remove,
  and treat the data as exposed regardless of how quickly it was removed.
- **This repository described as private** — correct it immediately and visibly, citing AMENDMENT-0001
  and DEC-0016.
- **PUBLIC presented as the canonical desired state** — correct it; the deviation must remain visible and
  re-examinable, not normalised.
- **An example datum copied from reality** — remove and replace with a fictional one, and check whether
  it was already pushed; if it was, treat it as a disclosure.
- **Internal re-verification reported as independent review or approval** — correct the wording. This is
  a false claim under Rule 01, not a stylistic preference.
- **An authoring constraint relaxed by editing a validator** — treat as a serious governance breach.
  Relaxing a constraint requires a superseding decision record and a Master Source version bump, never a
  validator edit (Rule 00).
