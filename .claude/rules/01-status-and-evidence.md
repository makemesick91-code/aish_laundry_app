# Rule 01 — Status Vocabulary and Evidence

## Purpose

To make every status claim in this repository verifiable. The most common failure mode of AI-assisted
projects is confident language describing work that was never done. This rule removes the vocabulary
that makes that failure possible.

Backed by **DEC-0013 — Exact-SHA Evidence Before GO**.

## Status vocabulary

Only these statuses may be used. Do not invent synonyms, do not decorate them with adjectives.

| Status | Meaning |
|---|---|
| `PLANNED` | Decided and scheduled. Nothing built. |
| `IN PROGRESS` | Actively being worked. Not complete, not verified. |
| `TESTED` | Verification was executed and its output captured at an exact SHA. |
| `WATCH` | Complete but carrying a known risk that must be monitored. |
| `NOT IMPLEMENTED` | The feature does not exist in any form. |
| `ABSENT` | The runtime, workspace, or environment does not exist. |
| `NOT APPLICABLE` | Genuinely does not apply at this stage. |
| `NOT STARTED` | Scheduled activity that has not begun (e.g. UAT). |
| `NO-GO` | A hard gate failed. Work stops. |

`GO` is a status the **repository owner** confers. An agent never writes `GO` for itself.

## Hard rules

1. **Never write `GO` as the status of Step 0** in the foundation pull request. The maximum
   permissible Step 0 status is `IN PROGRESS`, `TESTED`, or `WATCH`.
2. **Never claim any implementation, test, deployment, CI run, or UAT result that does not exist.**
   This includes hedged forms: "should pass", "effectively done", "essentially working".
3. An empty directory, a `README`, or a `.gitkeep` is **never** evidence of an implemented feature
   and must never be described as one.
4. If you did not run it, do not say it ran. If you ran it and it failed, report the failure.
5. Statuses only move forward on evidence. Never upgrade a status to make a report read better.
6. Absence of a check is not a pass. "No errors found" requires having actually looked.

## Exact-SHA evidence rules

Every verification claim must be bound to the commit it was produced from.

1. Evidence must record: the **exact 40-character commit SHA**, the **exact command executed**, the
   **captured output**, the **timestamp** (Asia/Jakarta), and the **environment** it ran in.
2. A short SHA is insufficient for an evidence record. Use the full SHA.
3. Evidence produced at SHA `A` **does not** carry over to SHA `B`. If the tree changed, re-run.
4. Evidence artifacts live under `evidence/`. They are append-only in spirit: an evidence file is
   never quietly edited to change a result.
5. Never fabricate, paraphrase, prettify, or truncate captured output in a way that changes its
   meaning. If output is long, store it in full and summarize alongside it, clearly labelled as a
   summary.
6. Evidence must never contain secrets, tokens, credentials, OTPs, or customer personal data.
   Redact before storing, and note that redaction occurred.
7. A claim without evidence is an **unverified claim** and must be labelled as such in plain words.

## Step 0 evidence expectation

Step 0 has **governance validators only** — there is no application to test. Acceptable Step 0
evidence is the output of the repository's own validation scripts (for example
`bash scripts/verify-step-00.sh`) captured at an exact SHA. Nothing in Step 0 may be described as a
tested feature, because no feature exists.

## Violation handling

- **A false or unsupported claim is discovered** — correct it immediately and visibly. Do not
  silently edit the wording; state that a previous claim was wrong and what the verified truth is.
- **Evidence found without a SHA** — treat the evidence as void. Re-run and recapture, or remove the
  claim it supported.
- **Fabricated output** — automatic NO-GO. Stop work, disclose to the owner, and treat every other
  claim from the same session as suspect until re-verified.
- **`GO` written for Step 0** — revert the wording before the pull request proceeds.
- Repeated status inflation is grounds for the owner to reject the entire branch. Accuracy is not
  negotiable against schedule.
