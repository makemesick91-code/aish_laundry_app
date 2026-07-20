# Rule 47 — Runtime Testing and Adversarial Validator Gates

## Purpose

Step 3 is the first step where "the tests pass" can mean something real. This rule fixes the evidence
standard that makes that claim trustworthy: bound to an exact commit, reproducible from a clean checkout,
and never confused with a validator merely reporting that scope was classified.

## Hard rules

1. **Exact-SHA CI and fresh-clone verification are mandatory before any Step 3 test result is cited.**
   A test result is bound to the full 40-character commit SHA it ran against, and it is re-run and
   re-captured from a genuinely clean checkout — not merely a locally cached working tree — before it is
   trusted as evidence (Rule 01, DEC-0013). A result that has only ever run in a dirty local environment
   is unverified regardless of how confidently it reads.

## Supporting expectations

- Every Step 3 validator — the runtime-scope guard, and any authentication, tenancy, or RBAC test suite
  introduced alongside it — is tested adversarially against deliberately broken input before it is relied
  upon as a gate, mirroring the discipline already required for Step 2 validators (Rule 33). A validator
  that has only ever been run against correct input is an untested validator, and reporting it as a
  passing gate overstates the assurance it actually provides.
- Runtime presence is never substituted for a test result (Rule 36, hard rule 6); a `classify` check
  passing means placement was legal, not that authentication, tenancy, or RBAC behaves correctly.
- Test evidence for tenant isolation is produced against PostgreSQL specifically, never a substitute
  engine (Rule 43).
- CI workflows remain pinned to a full commit SHA for every action used, and workflow permissions remain
  least-privilege, exactly as required before any runtime existed (Rule 11, hard rules 17–18).
- A failing test is reported as a failure. "Should pass," "effectively passing," and similar hedges are
  treated as the false claims they are (Rule 01, hard rule 2).

## Step 3 note

No Step 3 application test suite exists yet to run adversarially or otherwise, because no application
code exists to test. This rule fixes the evidence bar the first authentication, tenancy, and RBAC test
suite must clear before its results may be cited toward Step 3 `GO`. Application CI itself remains `NOT
APPLICABLE` until a real runtime workflow actually exists and runs (Rule 49).

## Violation handling

- **A test result cited without its exact commit SHA, or produced only from a dirty local environment**
  — the claim is void; re-run from a clean checkout and recapture, or withdraw the claim (Rule 01).
- **A validator relied upon as a gate with no adversarial testing against broken input** — the assurance
  is overstated; test it adversarially before reporting it as a passing gate (Rule 33).
- **A `classify` result reported as proof that a feature works** — false claim under Rule 01; correct it
  immediately (Rule 36).
- **A tenant-isolation test result produced against a non-PostgreSQL engine** — the evidence is void
  (Rule 43, Rule 48).
- **A CI action unpinned to a full commit SHA, or a workflow with broader-than-needed permissions** —
  reject the workflow change until pinned and scoped down (Rule 11).
- **A failing test reported with hedged, softened language** — correct the report to state plainly that
  it failed (Rule 01).
