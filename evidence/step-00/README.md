# Step 0 Evidence Pack — Master Source and Governance

This directory holds the factual evidence for Step 0 of Aish Laundry App.

Evidence here is intended to be reproducible. It records real commands and real
results, distinguishes candidate / merge / tag / final SHAs, and contains no
secret, token, or raw authentication output.

## Contents

| File | Purpose |
|---|---|
| [preflight.md](preflight.md) | Environment, tool availability, authentication status, skill and Graphify discovery, and the blocker found during preflight |
| [repository-verification.md](repository-verification.md) | Repository ownership, visibility history and the deliberate PUBLIC deviation, `main` bootstrap |
| [file-manifest.md](file-manifest.md) | Full SHA-256 manifest of every tracked file |
| [validation-results.md](validation-results.md) | Governance validator results, adversarial tamper tests, destructive-guard verification |
| [decision-validation.md](decision-validation.md) | DEC-0001 … DEC-0015 completeness and heading validation |
| [rules-traceability.md](rules-traceability.md) | Rule coverage and hard-gate placement |
| [security-review.md](security-review.md) | Independent adversarial security review, findings, remediation, re-verification |
| [tooling-report.md](tooling-report.md) | Limit Saver status, Graphify, subagents, MCP, credential handling |
| [graphify-summary.md](graphify-summary.md) | Graphify version, mode, graph metrics, findings |
| [ci-exact-sha.md](ci-exact-sha.md) | Exact-SHA CI evidence for the candidate and merge commits |
| [ruleset-verification.md](ruleset-verification.md) | Ruleset configuration and enforcement verification |
| [clean-checkout-foundation.md](clean-checkout-foundation.md) | Clean-checkout verification of the feature branch |
| [merge-verification.md](merge-verification.md) | Foundation PR merge evidence |
| [tag-verification.md](tag-verification.md) | Annotated GO tag object, peeled SHA, remote verification |
| [post-tag-evidence.md](post-tag-evidence.md) | Post-tag evidence-only PR and tag immutability check |
| [final-closure.md](final-closure.md) | Final Step 0 closure status against the Definition of Done |

## Reproducing the validation

```bash
bash scripts/verify-step-00.sh
```

Runs every governance gate and exits non-zero if any gate fails.

## Evidence rules

- No secret, token, private key, or raw SSH configuration is recorded.
- Authentication is recorded as status only, never as a token value.
- Full SHAs are recorded, never abbreviated forms alone.
- Candidate, merge, tag, and final `main` SHAs are kept distinct and are never
  conflated.
- A claim is recorded only when the corresponding command actually produced the
  stated result. Failures are recorded as failures.
- Subagent claims are not treated as evidence. Each was independently re-verified
  by the main agent before being recorded here; two were found materially wrong
  and are documented in [security-review.md](security-review.md).
