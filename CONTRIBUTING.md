# Contributing to Aish Laundry App

This repository is governed by [`docs/MASTER_SOURCE.md`](docs/MASTER_SOURCE.md). Every contribution —
human or AI — must be consistent with it. Where a document and the Master Source disagree, the Master
Source wins and the other document must be corrected in the same pull request.

---

## 1. Ground rules

1. **Never claim work that does not exist.** No implementation, test, deployment, CI run, or UAT may be
   described as done unless it is real and evidenced. An empty folder is not a feature.
2. **Stay inside the current Step.** Work belonging to a later Step must not be smuggled into an earlier
   one. See [`docs/ROADMAP.md`](docs/ROADMAP.md).
3. **No secrets.** Never commit tokens, passwords, API keys, private keys, `.env` files, customer data,
   or real phone numbers. See [`SECURITY.md`](SECURITY.md).
4. **Use the canonical status vocabulary** defined in
   [`docs/governance/STATUS_MODEL.md`](docs/governance/STATUS_MODEL.md).
5. **Bahasa Indonesia is the primary product language.** Technical governance terms may remain in English.
6. **The product name is exactly `Aish Laundry App`.** No alternative canonical product name may appear.

---

## 2. Branch model

- `main` is protected. Direct pushes are forbidden. All change arrives via pull request.
- Branch from the current `main` head.
- Branch naming:

| Purpose | Pattern | Example |
| --- | --- | --- |
| Canonical step work | `feature/step-NN-<slug>` | `feature/step-00-master-source-and-governance` |
| Bug fix | `fix/<slug>` | `fix/status-table-typo` |
| Documentation only | `docs/<slug>` | `docs/clarify-quiet-hours` |
| Chore / tooling | `chore/<slug>` | `chore/update-validator` |
| Security fix | `security/<slug>` | `security/harden-token-policy` |

- `NN` is the two-digit canonical step number. Step numbers are never reused or swapped without a
  decision record.
- Slugs are lowercase kebab-case, ASCII only.
- One branch addresses one Step or one focused change. Do not mix Steps in a single branch.

Full branching, tagging, and rollback rules: [`docs/GIT_AND_RELEASE_POLICY.md`](docs/GIT_AND_RELEASE_POLICY.md).

---

## 3. Conventional commits

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

<body>

<footer>
```

Allowed types:

| Type | Use for |
| --- | --- |
| `feat` | new user-visible capability |
| `fix` | bug fix |
| `docs` | documentation only |
| `chore` | tooling, scaffolding, housekeeping |
| `refactor` | behaviour-preserving code change |
| `test` | tests only |
| `perf` | performance work |
| `build` | build system or dependency change |
| `ci` | CI configuration |
| `security` | security hardening |
| `revert` | revert of a previous commit |

Rules:

- Subject is imperative mood, lowercase, no trailing period, at most 72 characters.
- Scope is the affected area: `governance`, `docs`, `decisions`, `backend`, `ops-android`,
  `customer-android`, `admin-web`, `ci`, `scripts`.
- Breaking changes use `!` after the scope and a `BREAKING CHANGE:` footer.
- Reference decision records in the footer where relevant: `Refs: DEC-0012`.

Example:

```
docs(governance): add tenant isolation hard-gate policy

Records the thirteen tenant isolation rules from the Master Source as an
enforceable hard gate and links them to the Step 0 validator.

Refs: DEC-0002, DEC-0012
```

---

## 4. Pull request requirements

A pull request is only reviewable when **all** of the following hold.

### 4.1 Content

- [ ] Title follows conventional-commit format and names the Step, e.g. `docs(governance): step 0 master source and governance`.
- [ ] Description states the Step number, the scope, and what is explicitly **out of scope**.
- [ ] Description contains an honest status statement using the canonical vocabulary.
- [ ] No claim of implementation, test, deployment, or CI that does not exist.
- [ ] Any product decision that is new or changed has a corresponding `docs/decisions/DEC-####-*.md` file.
- [ ] Any status change is reflected in [`docs/STATUS.md`](docs/STATUS.md).
- [ ] [`docs/CHANGELOG.md`](docs/CHANGELOG.md) is updated for anything user-visible or canonical.

### 4.2 Technical

- [ ] The relevant validator script passes locally, and its output is attached as evidence.
- [ ] All internal markdown links resolve to files that actually exist in the tree.
- [ ] No secrets, tokens, credentials, `.env` files, or real customer data are present in the diff.
- [ ] No runtime manifest (`pubspec.yaml`, `composer.json`, …) is added before the Step that authorises it.
- [ ] The diff contains no unrelated reformatting noise.

### 4.3 Evidence

- [ ] An evidence pack exists under `evidence/step-NN/` and is bound to the **exact commit SHA** under review.
- [ ] Evidence is sanitised: no secrets, no personal data, no internal hostnames.
- [ ] Rules followed: [`docs/governance/EVIDENCE_POLICY.md`](docs/governance/EVIDENCE_POLICY.md).

### 4.4 Merge

- Squash or merge commit per repository setting; rebase-and-force-push onto `main` is forbidden.
- At least one approving review is required.
- All required checks must be green **on the exact head SHA that is merged**.
- The author does not self-approve a Step-closing pull request.

---

## 5. Validator requirement

Every pull request must run the validator for its Step before review is requested:

```bash
bash scripts/verify-step-00.sh
```

Rules:

- A non-zero exit code is a **NO-GO**. Do not request review, do not merge, do not weaken the validator
  to make it pass.
- If the validator itself is wrong, fix the validator in a separate, clearly-described commit and explain
  why in the pull request description.
- Never delete or disable an assertion to get green. Assertions may only be changed with a written
  justification and, where the change alters a canonical rule, a decision record.
- Validator output pasted into a pull request must be the real, unedited output.

---

## 6. No-secrets rule

- Secrets never enter the repository, the pull request description, the commit message, the evidence
  pack, issue comments, or screenshots.
- The repository is **PUBLIC** (see AMENDMENT-0001 in [`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md)).
  Assume anything committed is world-readable forever.
- Configuration examples use `.env.example` with placeholder values only, and only from the Step that
  introduces a runtime.
- Logs and evidence must never contain passwords, OTPs, tokens, session identifiers, or credentials.
- A committed secret is a security incident: rotate the credential first, then remove it, then report it
  per [`SECURITY.md`](SECURITY.md). Deleting the commit is **not** sufficient remediation.

---

## 7. Step scope discipline

The roadmap in [`docs/ROADMAP.md`](docs/ROADMAP.md) is locked. Contributions must respect it.

**Do:**

- Deliver exactly the scope declared for the current Step.
- Record anything discovered but out of scope as an issue or an assumption in
  [`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md), and let the owning Step handle it.
- Keep placeholder folders as placeholders until their authorising Step arrives.

**Do not:**

- Add a runtime, schema, migration, API, or UI in a Step that does not authorise it.
- Renumber, merge, or swap Steps without a decision record.
- Mark a Step complete while any hard gate is unmet.

**Step 0 forbidden operations** (non-exhaustive, from the Master Source):
`flutter create`, `dart create`, `laravel new`, `composer create-project`, `npm create`, `pubspec.yaml`,
`composer.json`, `artisan`, database schema, migrations, authentication, tenant implementation, REST API
runtime, Android UI, Flutter Web UI, Docker application runtime, any deployment, and any payment,
WhatsApp, tracking, pickup-delivery, or H+1/H+3/H+7 implementation.

---

## 8. Hard gates that block any merge

Two failures are automatic **NO-GO** regardless of anything else in the pull request:

1. **Cross-tenant data exposure** — see
   [`docs/governance/TENANT_ISOLATION_POLICY.md`](docs/governance/TENANT_ISOLATION_POLICY.md).
2. **Financial integrity failure** — see
   [`docs/governance/FINANCIAL_INTEGRITY_POLICY.md`](docs/governance/FINANCIAL_INTEGRITY_POLICY.md).

---

## 9. AI-assisted contributions

AI agents contributing to this repository operate under
[`docs/AI_EXECUTION_POLICY.md`](docs/AI_EXECUTION_POLICY.md) and the rule files in `.claude/rules/`.
The same standard applies to humans and agents: no fabricated results, no unverified claims, evidence
bound to an exact commit SHA, and a clear stop-and-report when a hard gate is at risk.
