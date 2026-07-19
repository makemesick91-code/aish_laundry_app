# Definition of Done — Aish Laundry App

Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §25

A Step, a pull request, or a feature is **Done** only when every applicable item below is true **and
evidenced**. "I believe it works" is not Done. "The validator passed on this exact SHA and here is the
output" is Done.

---

## 1. General Definition of Done

### 1.1 Scope

- [ ] The Step's declared scope is delivered in full.
- [ ] Nothing declared was quietly dropped, renamed, or deferred without a written record.
- [ ] No work belonging to a later Step was performed.
- [ ] No work belonging to an earlier Step was left unfinished.
- [ ] Anything discovered but out of scope is recorded in [`ASSUMPTIONS.md`](ASSUMPTIONS.md) or as an
      issue, and assigned to its owning Step.

### 1.2 Canonical documents

- [ ] [`MASTER_SOURCE.md`](MASTER_SOURCE.md) is updated if the Step changed anything canonical, and its
      version is bumped appropriately.
- [ ] [`STATUS.md`](STATUS.md) reflects reality after the Step, using only the canonical vocabulary.
- [ ] [`ROADMAP.md`](ROADMAP.md) reflects the Step's new status.
- [ ] [`CHANGELOG.md`](CHANGELOG.md) has an entry describing what actually changed.
- [ ] Any new or changed product decision has a decision record in [`decisions/`](decisions/).
- [ ] [`GOVERNANCE_TRACEABILITY.md`](GOVERNANCE_TRACEABILITY.md) is updated if a new foundation area,
      rule file, decision, or validator was introduced.

### 1.3 Honesty

- [ ] Every claim in the pull request description is true.
- [ ] No implementation, test, deployment, CI run, or UAT is claimed that did not happen.
- [ ] No empty folder is presented as an implemented feature.
- [ ] Statuses use only [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md) vocabulary.
- [ ] Uncertainty is stated as uncertainty, not smoothed over.

### 1.4 Hard gates

- [ ] **Tenant isolation**: no cross-tenant data exposure. Verified, not assumed.
      ([`governance/TENANT_ISOLATION_POLICY.md`](governance/TENANT_ISOLATION_POLICY.md))
- [ ] **Financial integrity**: no violation of Master Source §16. Verified, not assumed.
      ([`governance/FINANCIAL_INTEGRITY_POLICY.md`](governance/FINANCIAL_INTEGRITY_POLICY.md))

A failure in either gate is an automatic **NO-GO**. It blocks merge, blocks release, and blocks a GO tag,
regardless of schedule.

### 1.5 Quality

- [ ] Tests required by the Step exist, run, and pass, with real unedited output
      ([`MASTER_SOURCE.md`](MASTER_SOURCE.md) §28).
- [ ] From Step 3 onward: the tenant isolation suite passes.
- [ ] From Step 5 onward: the financial integrity suite passes.
- [ ] Any skipped or quarantined test is disclosed in the pull request.
- [ ] The Step's validator script passes with exit code 0.
- [ ] No validator assertion was weakened, skipped, or deleted to obtain a green result.

### 1.6 Security and privacy

- [ ] No secrets, tokens, credentials, `.env` files, or private keys appear in the diff, the commit
      messages, the pull request, or the evidence pack.
- [ ] No real customer data appears anywhere, including test fixtures.
- [ ] Logging redaction is in place for any new code path that touches passwords, OTPs, tokens, or
      credentials.
- [ ] Personal data masking rules are respected for any new surface.

### 1.7 Repository hygiene

- [ ] All internal markdown links resolve to files that actually exist in the tree.
- [ ] The diff contains no unrelated reformatting noise.
- [ ] Branch naming and conventional commits follow [`../CONTRIBUTING.md`](../CONTRIBUTING.md).
- [ ] No forbidden destructive git operation was performed
      ([`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md)).

### 1.8 Evidence

- [ ] An evidence pack exists under `evidence/step-NN/`.
- [ ] The evidence pack is bound to the **exact commit SHA** under review (DEC-0013).
- [ ] The evidence pack is sanitised
      ([`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)).
- [ ] Command output in the evidence pack is real and unedited.

### 1.9 Review and merge

- [ ] The pull request targets `main` and `main` was not pushed to directly.
- [ ] At least one approving review from someone other than the author for a Step-closing change.
- [ ] All required checks are green **on the exact head SHA that will be merged**.
- [ ] The Step's status is advanced only after merge, never in anticipation of it.

---

## 2. Step 0 Definition of Done

Step 0 — Master Source and Governance. Current status: **IN PROGRESS**.

### 2.1 Master Source

- [ ] `docs/MASTER_SOURCE.md` exists.
- [ ] It carries an explicit `Document version: 1.0.0` line.
- [ ] It carries an explicit `Baseline date: 19 July 2026` line.
- [ ] It contains all thirty-three numbered canonical sections in the canonical order.
- [ ] It reproduces the locked pricing exactly: 14 hari gratis; Starter Rp79.000/bulan; Growth
      Rp199.000/bulan; Scale Rp399.000/bulan; Enterprise mulai Rp999.000/bulan; annual Starter
      Rp790.000/tahun, Growth Rp1.990.000/tahun, Scale Rp3.990.000/tahun.
- [ ] It reproduces the locked roadmap Step 0 to Step 14 with the canonical titles.
- [ ] It reproduces the multi-tenancy hierarchy and all thirteen hard rules.
- [ ] It reproduces the unclaimed-laundry aging rule and the H+1 / H+3 / H+7 / H+14 ladder.
- [ ] It uses the official product name **Aish Laundry App** consistently, and no other canonical product
      name appears.

### 2.2 Decision records

- [ ] All fifteen records DEC-0001 … DEC-0015 exist in `docs/decisions/`.
- [ ] Each has status **ACCEPTED** and date **19 July 2026**.
- [ ] Each contains all required headings: ID, Title, Status, Date, Context, Decision, Consequences,
      Positive consequences, Negative consequences / trade-offs, Verification, Supersession policy,
      Related Master Source sections.
- [ ] No record is a placeholder; every Context, Decision, and Consequences section is substantive.

### 2.3 Governance documents

- [ ] `docs/governance/REQUIRED_FILES.md` exists and matches the real tree.
- [ ] `docs/governance/STATUS_MODEL.md` defines PLANNED, IN PROGRESS, TESTED, WATCH, GO, NO-GO,
      NOT IMPLEMENTED, ABSENT, NOT APPLICABLE, NOT STARTED.
- [ ] `docs/governance/EVIDENCE_POLICY.md` exists.
- [ ] `docs/governance/TENANT_ISOLATION_POLICY.md` exists.
- [ ] `docs/governance/FINANCIAL_INTEGRITY_POLICY.md` exists.
- [ ] `docs/GIT_AND_RELEASE_POLICY.md`, `docs/AI_EXECUTION_POLICY.md`, and `docs/TOOLING_POLICY.md` exist.
- [ ] `docs/GOVERNANCE_TRACEABILITY.md` maps every foundation area to a rule file, a decision record, and
      a validator.
- [ ] `docs/ASSUMPTIONS.md` records ASSUMPTION-0001 as RESOLVED / ACCEPTED and AMENDMENT-0001 for
      repository visibility.
- [ ] `docs/ROADMAP.md`, `docs/STATUS.md`, `docs/CHANGELOG.md`, and this file exist.

### 2.4 Root and rule files

- [ ] `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, and `CLAUDE.md` exist at the repository root.
- [ ] Rule files `00-canonical-source.md` through `15-current-product-status.md` exist under
      `.claude/rules/`.

### 2.5 Status honesty

- [ ] `docs/STATUS.md` records Step 0 as **IN PROGRESS** and Steps 1–14 as **PLANNED**.
- [ ] It records all product features as **NOT IMPLEMENTED**.
- [ ] It records Backend runtime **ABSENT**, Flutter workspace **ABSENT**, Deployment **ABSENT**.
- [ ] It records Application CI **NOT APPLICABLE** and UAT **NOT STARTED**.
- [ ] Step 0 is **not** recorded anywhere with the release status word.

### 2.6 No-runtime guarantee

- [ ] No `pubspec.yaml` anywhere in the tree.
- [ ] No `composer.json` anywhere in the tree.
- [ ] No `artisan`, no Laravel application, no Flutter workspace.
- [ ] No database schema, migration, or seed.
- [ ] No authentication, tenancy, or REST API implementation.
- [ ] No Android UI or Flutter Web UI.
- [ ] No Docker application runtime and no deployment configuration.
- [ ] No payment, WhatsApp, tracking, pickup-delivery, or H+1/H+3/H+7 implementation.
- [ ] Every runtime placeholder folder contains a `README.md` stating `Status: NOT IMPLEMENTED` and
      `Runtime: ABSENT`, and nothing else that implies a runtime.

### 2.7 Validation

- [ ] `bash scripts/verify-step-00.sh` exits 0.
- [ ] Its unedited output is stored in `evidence/step-00/`, bound to the exact commit SHA.
- [ ] Every internal markdown link in the repository resolves.
- [ ] No secrets appear anywhere in the repository.

### 2.8 Visibility honesty

- [ ] AMENDMENT-0001 in [`ASSUMPTIONS.md`](ASSUMPTIONS.md) records the PUBLIC repository visibility, the
      GitHub free-plan limitation that caused it, and the owner's explicit election.
- [ ] Nowhere in the repository is the repository described as private.

---

## 3. What Done does not mean

- Done does not mean the validator was edited until it passed.
- Done does not mean a status was advanced because a deadline arrived.
- Done does not mean a hard gate was waived "just this once" — hard gates are never waived.
- Done does not mean a folder exists where a feature should be.
