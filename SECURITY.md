# Security Policy — Aish Laundry App

Owner: **Aish Tech Solution**
Canonical source: [`docs/MASTER_SOURCE.md`](docs/MASTER_SOURCE.md) §15 (Security) and §17 (Privacy).

---

## 1. Supported scope

**There is no running system to attack yet.**

As of the Step 0 baseline (19 July 2026):

| Item | Status |
| --- | --- |
| Backend runtime | ABSENT |
| Flutter workspace | ABSENT |
| Deployment | ABSENT |
| All product features | NOT IMPLEMENTED |
| UAT | NOT STARTED |

This repository contains governance documentation only. There are no published binaries, no hosted
environments, no APIs, and no user data. Consequently:

- There is **no supported product version** to receive security patches.
- Vulnerability reports about this repository can concern only the repository itself — for example a
  leaked credential, a malicious dependency introduced into tooling, a CI workflow weakness, or a
  documented policy that would be unsafe if implemented as written.
- Once a runtime exists (from **Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC** onward),
  this policy will be extended with supported versions and environment scope.

We nonetheless welcome reports about **unsafe governance**: if a rule in this repository would produce
an insecure system when implemented, that is a valid and valuable report.

---

## 2. Reporting a vulnerability

**Do not open a public GitHub issue for a security problem.** This repository is PUBLIC
(see AMENDMENT-0001 in [`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md)); a public issue discloses the
problem to everyone immediately.

Preferred channel:

1. **GitHub private vulnerability reporting** on the repository (Security → Report a vulnerability).
2. If that is unavailable, contact the repository owner directly through the contact details published
   on the repository owner's GitHub profile, with the subject line
   `SECURITY — Aish Laundry App — <short summary>`.

Please include:

- a clear description of the issue and why it is a security problem;
- the exact commit SHA, file paths, and line numbers involved;
- reproduction steps or a proof of concept, if applicable;
- the impact you believe it has, including whether tenant isolation or financial integrity is affected;
- any suggested remediation.

**Never include live secrets in a report.** If you found a leaked credential, describe its location and
type; do not paste the credential value.

### Response targets

| Stage | Target |
| --- | --- |
| Acknowledgement of report | within 3 working days |
| Initial assessment and severity | within 7 working days |
| Remediation plan communicated | within 14 working days |
| Fix or documented mitigation | severity-dependent; critical issues take priority over roadmap work |

We ask reporters to allow a reasonable coordinated-disclosure window before publishing. We will credit
reporters who wish to be credited.

### Safe harbour

Good-faith research that stays within this policy, avoids privacy violations and service degradation,
and does not access data belonging to others will not be pursued. Because there is no deployed system,
there is nothing to test against externally — any claim of a live exploit against Aish Laundry App
today is false.

---

## 3. No-secrets policy

Secrets must never enter this repository, in any branch, at any time.

**Forbidden in the repository, commit messages, pull requests, issues, evidence packs, and screenshots:**

- passwords, password hashes, and password reset tokens;
- API keys, client secrets, access tokens, refresh tokens, session identifiers;
- private keys, certificates with private material, SSH keys;
- database connection strings containing credentials;
- WhatsApp provider credentials and payment gateway keys;
- populated `.env` files;
- real customer data — names, phone numbers, addresses, photographs of laundry, invoices;
- OTP values, whether real or captured from a test run.

**Required practices:**

- Configuration is supplied by environment, not by committed files. Only `.env.example` with placeholder
  values may be committed, and only from the Step that introduces a runtime.
- Secrets used by CI live in the platform's secret store and are never echoed into logs.
- Application logs must never contain passwords, OTPs, tokens, or credentials
  ([`docs/MASTER_SOURCE.md`](docs/MASTER_SOURCE.md) §15).
- Evidence packs are sanitised before commit
  ([`docs/governance/EVIDENCE_POLICY.md`](docs/governance/EVIDENCE_POLICY.md)).

**If a secret is committed:**

1. **Rotate or revoke the credential immediately.** This is the only step that actually restores security.
2. Remove the value from the working tree and open a pull request that fixes the cause.
3. Report it through the private channel in §2.
4. Record the incident and its remediation.

Because the repository is public, treat any committed secret as compromised the moment it is pushed.
Rewriting history is **not** remediation, and history rewriting on `main` is forbidden by
[`docs/GIT_AND_RELEASE_POLICY.md`](docs/GIT_AND_RELEASE_POLICY.md).

---

## 4. Security hard gates

Two categories of defect are **automatic NO-GO**. They block merge, block release, and block a GO tag,
regardless of schedule pressure or how much other work is complete.

### 4.1 Tenant isolation

Aish Laundry App is multi-tenant. The hierarchy is
`User Account → Membership → Tenant/Organization → Laundry Brand → Outlet`.

Non-negotiable rules:

1. Every business table carries `tenant_id`.
2. Every business query is tenant-scoped.
3. A client-supplied tenant identifier is **never** authorisation proof.
4. The backend verifies membership **and** permission on every request, server-side.
5. Records are never merged merely because owner name, email, or phone number match.
6. The owner portfolio dashboard must not weaken tenant isolation in order to aggregate.
7. Platform support has no silent access to tenant data; support impersonation is time-bound and audited.

**Any cross-tenant data exposure is an automatic NO-GO.** Full policy:
[`docs/governance/TENANT_ISOLATION_POLICY.md`](docs/governance/TENANT_ISOLATION_POLICY.md).
Decision records: DEC-0002, DEC-0003, DEC-0012.

### 4.2 Financial integrity

Money is the product's most sensitive data after identity. Non-negotiable rules:

1. Money is stored as **integer Rupiah**. Floating point is forbidden for financial transactions.
2. Payments are idempotent; a retry never creates a second payment.
3. Gateway callbacks are verified server-side; an order is **never** marked paid on a client claim.
4. Refund and void require an explicit permission and a recorded reason.
5. Financial transactions are never deleted through ordinary UI; corrections happen by reversal or
   adjustment, preserving the audit trail.
6. Historical order prices are immune to later price-list changes.
7. Shift closing compares expected against actual cash; courier cash is reconciled.

**Any financial integrity failure is an automatic NO-GO.** Full policy:
[`docs/governance/FINANCIAL_INTEGRITY_POLICY.md`](docs/governance/FINANCIAL_INTEGRITY_POLICY.md).
Decision record: DEC-0012.

---

## 5. Security baseline commitments

These commitments are commercial as well as technical
([`docs/MASTER_SOURCE.md`](docs/MASTER_SOURCE.md) §21):

- The security baseline is **not** locked behind expensive plans.
- Tenant isolation is **not** an add-on.
- Backup is **not** a premium security add-on.

Baseline controls that every plan receives, once implemented in their canonical Steps:

- least privilege and server-side authorisation;
- secure password hashing and secure token storage, using Android secure storage on device;
- validated uploads; private files served only through signed URLs;
- rate limiting and brute-force protection;
- session revocation and device revocation;
- encrypted backups;
- audited, time-bound support impersonation;
- masking of phone numbers and addresses according to context — the public tracking portal never shows a
  full address;
- laundry photographs treated as private data;
- tenant data never used to train AI models without explicit consent.

---

## 6. Public tracking portal

The public tracking portal is a deliberate external attack surface (DEC-0006). Its security rules are
canonical and cannot be relaxed by a later implementation:

- the tracking token is high-entropy and is **not** the order number;
- the token is stored hashed;
- tokens are revocable and expiring;
- the portal is served with `noindex`;
- personal data is masked, and the full address is never shown;
- sensitive actions require OTP verification.

---

## 7. Related documents

- [`docs/MASTER_SOURCE.md`](docs/MASTER_SOURCE.md)
- [`docs/governance/TENANT_ISOLATION_POLICY.md`](docs/governance/TENANT_ISOLATION_POLICY.md)
- [`docs/governance/FINANCIAL_INTEGRITY_POLICY.md`](docs/governance/FINANCIAL_INTEGRITY_POLICY.md)
- [`docs/governance/EVIDENCE_POLICY.md`](docs/governance/EVIDENCE_POLICY.md)
- [`docs/GIT_AND_RELEASE_POLICY.md`](docs/GIT_AND_RELEASE_POLICY.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
