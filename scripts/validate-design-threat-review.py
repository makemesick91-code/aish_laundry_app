#!/usr/bin/env python3
"""Validate the design and UX threat review.

Enforces the closure position: no CRITICAL or HIGH finding may remain open,
every CRITICAL and HIGH must carry a concrete UX mitigation, and the review
must never describe itself as independent peer review — governance here is
single-maintainer and independent human approval is ABSENT (DEC-0017).

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import FINDING_ID, read

DOC = "docs/security/DESIGN_AND_UX_THREAT_REVIEW.md"

SEVERITIES = ["CRITICAL", "HIGH", "MEDIUM", "LOW", "INFORMATIONAL"]

REQUIRED_AREAS = [
    "hidden tenant context", "outlet context", "pii", "tracking token",
    "full address", "clipboard", "notification preview", "external courier",
    "impersonation", "payment success", "refund", "destructive",
    "confirmation", "offline", "stale", "accessibility", "colour-only",
    "focus", "screen-reader", "otp", "session", "revoked", "consent",
    "reminder", "storage fee", "disposal", "public repository", "svg",
    "remote", "malicious link", "token", "traceability",
]

# Wording that would misrepresent single-maintainer governance.
FALSE_REVIEW_CLAIMS = [
    "independent peer review", "independently reviewed",
    "independent review was", "peer-reviewed by", "externally reviewed",
    "third-party review",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("design and UX threat review")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()
    low = text.lower()

    # -- findings carry IDs -------------------------------------------------
    ids = sorted({m.group(0) for m in FINDING_ID.finditer(text)})
    rep.check(len(ids) >= 30,
              f"at least 30 findings carry a DUX-### ID (found {len(ids)})")

    # -- every severity level is used and defined --------------------------
    for sev in SEVERITIES:
        rep.check(sev in text, f"severity '{sev}' is used")

    # -- parse the finding blocks -----------------------------------------
    # Findings are specified as heading blocks (### DUX-### — Title), each
    # carrying its severity and status in the body. A summary table also
    # exists, but the blocks are the specification.
    blocks = re.split(r"(?=^#{2,5}[^\n]*DUX-\d{3})", text, flags=re.M)
    findings = []
    for block in blocks:
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first.startswith("#"):
            continue
        match = FINDING_ID.search(first)
        if not match:
            continue
        sev = next((sv for sv in SEVERITIES if sv in block), None)
        status = None
        for candidate in ("MITIGATED-BY-DESIGN", "ACCEPTED", "OPEN"):
            if candidate in block:
                status = candidate
                break
        findings.append((match.group(0), sev, status, block))

    rep.check(len(findings) >= 30,
              f"the review specifies at least 30 findings "
              f"(found {len(findings)})")

    # -- the closure position ----------------------------------------------
    open_critical = [f for f in findings
                     if f[1] == "CRITICAL" and f[2] == "OPEN"]
    open_high = [f for f in findings if f[1] == "HIGH" and f[2] == "OPEN"]
    for f in open_critical:
        rep.info(f"OPEN CRITICAL finding: {f[0]}")
    for f in open_high:
        rep.info(f"OPEN HIGH finding: {f[0]}")
    rep.check(not open_critical,
              f"no CRITICAL finding is open ({len(open_critical)} open)")
    rep.check(not open_high,
              f"no HIGH finding is open ({len(open_high)} open)")

    # -- every finding resolves to a status --------------------------------
    unresolved = [f[0] for f in findings if f[2] is None]
    for u in unresolved[:20]:
        rep.info(f"finding with no status: {u}")
    rep.check(not unresolved,
              f"every finding carries a status ({len(unresolved)} unresolved)")

    unrated = [f[0] for f in findings if f[1] is None]
    for u in unrated[:20]:
        rep.info(f"finding with no severity: {u}")
    rep.check(not unrated,
              f"every finding carries a severity ({len(unrated)} unrated)")

    # -- CRITICAL and HIGH findings carry a mitigation ----------------------
    no_mitigation = []
    for block in blocks:
        first = block.lstrip().splitlines()[0] if block.strip() else ""
        if not first.startswith("#"):
            continue
        match = FINDING_ID.search(first)
        if not match:
            continue
        if not any(s in block for s in ("CRITICAL", "HIGH")):
            continue
        if "mitigat" not in block.lower():
            no_mitigation.append(match.group(0))
    for n in no_mitigation:
        rep.info(f"CRITICAL/HIGH finding with no mitigation: {n}")
    rep.check(
        not no_mitigation,
        f"every CRITICAL and HIGH finding carries a mitigation "
        f"({len(no_mitigation)} without)",
    )

    # -- area coverage -------------------------------------------------------
    missing_areas = [a for a in REQUIRED_AREAS if a not in low]
    for a in missing_areas:
        rep.info(f"review area not covered: {a}")
    rep.check(
        not missing_areas,
        f"every mandated review area is covered ({len(missing_areas)} missing)",
    )

    # -- governance honesty --------------------------------------------------
    claims = []
    for line in text.splitlines():
        low_line = line.lower()
        for claim in FALSE_REVIEW_CLAIMS:
            if claim not in low_line:
                continue
            guard = ("never", "not ", "no ", "is absent", "absent",
                     "must not", "rather than", "instead of", "is wrong")
            if not any(g in low_line for g in guard):
                claims.append(line.strip()[:110])
    for c in claims:
        rep.info(c)
    rep.check(
        not claims,
        "the review never describes itself as independent peer review",
    )
    rep.check(
        "single-maintainer" in low or "single maintainer" in low,
        "the review states that governance is single-maintainer",
    )
    rep.check(
        "absent" in low,
        "the review states that independent human approval is ABSENT",
    )
    rep.check(
        "internally re-verified" in low or "internal re-verification" in low,
        "the review uses the mandated internal re-verification wording",
    )

    # -- severity must be argued, not asserted -----------------------------
    rep.check(
        "likelihood" in low and "impact" in low,
        "severity is argued from impact and likelihood",
    )
    rep.check(
        "residual" in low,
        "residual risk is recorded",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
