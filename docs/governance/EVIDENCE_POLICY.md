# Evidence Policy — Aish Laundry App

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §25, §27
Locked by [DEC-0013 — Exact-SHA Evidence Before GO](../decisions/DEC-0013-exact-sha-evidence-before-go.md)
Baseline date: 19 July 2026

**Absence of evidence is absence of completion.** A claim without evidence is an opinion. This policy
defines what evidence is, how it is bound to a commit, how it is sanitised, and what it may never contain.

---

## 1. Why evidence exists

Aish Laundry App is built largely by AI agents working autonomously across many sessions. Nobody watches
every command. The only defence against a plausible-sounding but false completion claim is a recorded,
verifiable, exactly-bound artefact that another person can re-run.

Evidence is not bureaucracy. It is the mechanism by which trust in this repository is earned rather than
assumed.

---

## 2. Evidence packs

### 2.1 Location

Every Step has an evidence pack at:

```
evidence/step-NN/
```

`NN` is the two-digit canonical step number. Step 0's pack is `evidence/step-00/`.

### 2.2 Required contents

| Item | Requirement |
| --- | --- |
| Manifest | Records the exact commit SHA, the branch, the date and time with timezone, the operator or agent, and an index of the artefacts |
| Validator output | The complete, unedited stdout and stderr of the Step's validator, with its exit code |
| Test output | From the Step that introduces tests: complete, unedited output including counts of passed, failed, and skipped |
| Environment record | Tool versions relevant to reproducing the result |
| Gate attestation | Explicit statement of the tenant isolation and financial integrity gate results, with the evidence supporting each |
| Known gaps | What was **not** verified and why |

### 2.3 Step 0 evidence pack

Because Step 0 creates no runtime, its pack contains governance evidence only:

- the output of `bash scripts/verify-step-00.sh` with its exit code;
- a file inventory demonstrating that every required file in
  [`REQUIRED_FILES.md`](REQUIRED_FILES.md) exists;
- a demonstration that no forbidden runtime artefact exists — no `pubspec.yaml`, no `composer.json`, no
  schema, no migration, no deployment;
- a link-resolution result for internal markdown links;
- the manifest recording the exact SHA;
- a statement that application CI is `NOT APPLICABLE` and no tests exist, because there is no
  application.

Step 0's pack must **not** contain any claim of a build, a test, a deployment, or a CI run.

---

## 3. Exact-SHA discipline

This is the core rule of the policy.

1. **Evidence belongs to exactly one commit SHA.** The full 40-character SHA is recorded, not an
   abbreviation, not a branch name, not "latest".
2. **Evidence for one SHA is not evidence for another.** Not for a parent, not for a child, not for a
   commit that "only changed a comment".
3. **Any new commit invalidates all prior evidence.** Re-run the checks and re-record.
4. **The SHA that is validated must be the SHA that is merged** and, where a release follows, the SHA
   that is tagged ([`../GIT_AND_RELEASE_POLICY.md`](../GIT_AND_RELEASE_POLICY.md) §3).
5. **A merge commit is a new SHA.** Post-merge validation on `main` is separate evidence.
6. **Rebasing or amending after evidence was produced voids that evidence**, and rebasing a reviewed
   branch is forbidden.
7. **A green CI badge is not evidence.** Badges track a branch, not a commit.

### 3.1 Recording the SHA

The manifest records the SHA as reported by the repository at the moment the evidence was produced,
together with the exact command that produced each artefact and that command's exit code.

---

## 4. Sanitisation

Evidence enters a **PUBLIC** repository (AMENDMENT-0001 in [`../ASSUMPTIONS.md`](../ASSUMPTIONS.md)).
Everything in it is world-readable, permanently.

### 4.1 Must be removed before commit

- passwords, password hashes, and reset tokens;
- API keys, client secrets, access tokens, refresh tokens, session identifiers;
- private keys and certificates with private material;
- database connection strings containing credentials;
- OTP values, real or captured from a test run;
- tracking-token plaintext;
- WhatsApp provider and payment gateway credentials;
- real customer names, phone numbers, and addresses;
- photographs of customer laundry, signatures, and delivery proofs;
- internal hostnames, private IP addresses, and infrastructure identifiers;
- absolute paths that reveal a personal account name where it is not necessary.

### 4.2 How to sanitise

1. **Redact, do not delete.** Replace a removed value with a clear marker such as `[REDACTED: token]`, so
   that a reader can see something was there and what kind of thing it was.
2. **Never edit a result.** Redacting a secret is permitted. Changing a failure into a pass, altering a
   count, or removing a failing line is falsification.
3. **Mark truncation.** Long output may be truncated with an explicit marker stating how much was
   removed. Never truncate to hide a failure.
4. **Prefer synthetic data.** Evidence produced against synthetic fixtures needs less redaction and is
   more reproducible.
5. **Review before commit.** The agent or contributor reads the evidence pack in full before committing
   it.

### 4.3 If a secret reaches the repository through evidence

Follow [`../../SECURITY.md`](../../SECURITY.md): **rotate first**, then remove through a normal pull
request, then report privately. History rewriting is forbidden
([`../GIT_AND_RELEASE_POLICY.md`](../GIT_AND_RELEASE_POLICY.md) §6).

---

## 5. Integrity of evidence

1. **Output is real.** It came from an actual execution, in this repository, at the recorded SHA.
2. **Output is unedited**, except for the redaction and truncation permitted in §4.2.
3. **A failure is recorded as a failure.** Evidence of a failed run is valuable and is committed as
   readily as evidence of a passing run.
4. **A skipped or quarantined test is disclosed** in the pack and in the pull request.
5. **Nothing is fabricated.** Generating plausible output that was never produced is the most serious
   violation of this policy and of
   [`../AI_EXECUTION_POLICY.md`](../AI_EXECUTION_POLICY.md).
6. **A validator is never weakened to produce better evidence.** If an assertion is wrong, fix it
   deliberately, in a separate commit, with a written justification.

---

## 6. Evidence and status

A status is advanced only on evidence
([`STATUS_MODEL.md`](STATUS_MODEL.md)):

| Status claimed | Evidence required |
| --- | --- |
| `IN PROGRESS` | None beyond the branch itself |
| `TESTED` | Validator and test output at an exact SHA, passing |
| `WATCH` | As `TESTED`, plus a written statement of the unresolved risk, its owner, and the condition that ends the watch |
| `GO` | As `TESTED`, plus both hard-gate attestations, a satisfied Definition of Done, and an approved pull request at that SHA |
| `NO-GO` | The real, unedited output of the failure |

Step 0 constraint: the release status word is never recorded as Step 0's status in
[`../STATUS.md`](../STATUS.md) during the foundation pull request.

---

## 7. Hard-gate attestation

From the Step that introduces the relevant capability, every evidence pack contains an explicit
attestation for both hard gates:

**Tenant isolation** ([`TENANT_ISOLATION_POLICY.md`](TENANT_ISOLATION_POLICY.md))
Records: which endpoints were exercised cross-tenant, the result, and the test output.

**Financial integrity** ([`FINANCIAL_INTEGRITY_POLICY.md`](FINANCIAL_INTEGRITY_POLICY.md))
Records: duplicate-submission and callback-replay results, confirmation that no floating point appears in
a money path, and the test output.

At the Step 0 baseline both attestations state honestly that **no runtime exists**, so neither gate can
be exercised, and that both become mandatory from Step 3 and Step 5 respectively.

---

## 8. Retention

- Evidence packs are committed to the repository and are never deleted.
- Superseded evidence stays; a later pack does not replace an earlier one, it joins it.
- Evidence is part of the immutable record, protected by the same history rules as everything else on
  `main`.

---

## 9. Reviewing evidence

A reviewer checks:

1. Does the manifest record a full SHA, and is it the SHA under review?
2. Is the validator output present, complete, and exit code 0?
3. Does the output correspond to the claims in the pull request description?
4. Are failures and skips disclosed rather than hidden?
5. Is the pack sanitised, with redactions marked?
6. Are the hard-gate attestations present and honest?
7. Are the known gaps stated?

A pack that fails any of these is not evidence, and the Step is **NO-GO** until it is corrected.
