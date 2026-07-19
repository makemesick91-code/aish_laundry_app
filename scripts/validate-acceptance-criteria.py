#!/usr/bin/env python3
"""Validate acceptance criteria and the non-functional requirements.

Criteria must be testable, must cite requirement IDs, and must never be reported
as passed — writing a criterion is not running one. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import read, requirement_ids, strip_code_blocks  # noqa: E402

AC_DOC = "docs/quality/ACCEPTANCE_CRITERIA.md"
SEC_AC_DOC = "docs/security/SECURITY_ACCEPTANCE_CRITERIA.md"
NFR_DOC = "docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md"
DOD_DOC = "docs/quality/STEP_01_DEFINITION_OF_DONE.md"

MANDATORY_SCENARIOS = [
    ("owner with multiple tenants", ["multiple tenant"]),
    ("customer number reused across tenants", ["reused", "same phone"]),
    ("cross-tenant access denied", ["cross-tenant", "cross tenant"]),
    ("immutable price snapshot", ["price snapshot", "historical price"]),
    ("partial payment", ["partial payment"]),
    ("payment replay", ["replay"]),
    ("duplicate offline order", ["duplicate"]),
    ("order lifecycle", ["lifecycle", "order status"]),
    ("quality control rework", ["rework"]),
    ("tracking token expiry", ["expir"]),
    ("tracking token revocation", ["revok", "revoc"]),
    ("external courier guest access", ["guest"]),
    ("proof of delivery", ["proof of delivery", "proof"]),
    ("failed delivery", ["failed delivery"]),
    ("H+1/H+3/H+7 reminders", ["h+1", "h+3", "h+7"]),
    ("opt-out honoured", ["opt-out", "opt out"]),
    ("overdue laundry escalation", ["escalat"]),
    ("provider notification failure", ["provider"]),
    ("subscription entitlement", ["entitlement"]),
    ("portfolio dashboard authorization", ["portfolio"]),
]

# Targets that must be reproduced exactly and never claimed as achieved.
NFR_TARGETS = [
    "500 ms",
    "2.5 second",
    "3.5 second",
    "99.5",
    "99.9",
    "15 minute",
    "4 hour",
]

# Language that would falsely report a criterion as satisfied.
FALSE_PASS = [
    (r"\ball\s+criteria\s+(?:pass|are\s+met|satisfied)\b", "claims all criteria are met"),
    (r"\bcriteria\s+(?:passed|verified\s+in\s+CI)\b", "claims criteria passed"),
    (r"\btests?\s+(?:pass(?:ed|es)|are\s+green)\b", "claims tests passed"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("acceptance-criteria")

    ac_path = root / AC_DOC
    ok = rep.check(ac_path.is_file(), f"{AC_DOC} exists")
    rep.check((root / SEC_AC_DOC).is_file(), f"{SEC_AC_DOC} exists")
    rep.check((root / NFR_DOC).is_file(), f"{NFR_DOC} exists")
    rep.check((root / DOD_DOC).is_file(), f"{DOD_DOC} exists")
    if not ok:
        return rep.finish()

    text = read(ac_path) + "\n" + read(root / SEC_AC_DOC)
    lower = text.lower()

    # --- Given/When/Then used for critical scenarios ---
    gwt = len(re.findall(r"\bgiven\b", lower))
    rep.check(gwt >= 10, f"criteria use Given/When/Then ({gwt} 'Given' clauses)")
    rep.check("when" in lower and "then" in lower, "criteria use When and Then clauses")

    # --- criteria cite requirement IDs ---
    cited = requirement_ids(text)
    rep.check(
        len(cited) >= 40,
        f"criteria cite requirement IDs ({len(cited)} distinct IDs cited)",
    )

    # --- mandatory scenario coverage ---
    for label, keywords in MANDATORY_SCENARIOS:
        rep.check(any(k in lower for k in keywords), f"scenario covered: {label}")

    # --- negative paths present ---
    rep.check(
        re.search(r"negative|denied|rejected|must\s+fail|forbidden", lower) is not None,
        "criteria cover negative paths, not only happy paths",
    )

    # --- NFR targets reproduced, and not claimed as achieved ---
    nfr_text = read(root / NFR_DOC)
    nfr_lower = nfr_text.lower()
    for target in NFR_TARGETS:
        rep.check(target in nfr_lower, f"NFR target reproduced: {target}")

    for label, keywords in [
        ("metric", ["metric"]),
        ("measurement method", ["measurement", "measured"]),
        ("environment", ["environment"]),
        ("threshold", ["threshold"]),
        ("responsible step", ["step"]),
        ("failure consequence", ["consequence", "failure"]),
    ]:
        rep.check(any(k in nfr_lower for k in keywords), f"NFRs state: {label}")

    rep.check(
        re.search(
            r"not\s+(?:yet\s+)?(?:measured|achieved)|target[^.\n]{0,40}not\s+met"
            r"|no\s+measurement",
            nfr_lower,
        )
        is not None,
        "NFRs state that targets are not yet measured",
    )

    # --- honesty: nothing is reported as passed ---
    prose = strip_code_blocks(text + "\n" + nfr_text)
    for pattern, label in FALSE_PASS:
        hit = re.search(pattern, prose, re.IGNORECASE)
        if hit:
            window = prose[max(0, hit.start() - 150) : hit.start() + 150].lower()
            if re.search(r"\b(?:never|not|no|must not|cannot|would be)\b", window):
                rep.ok(f"never {label} (only negated mentions)")
                continue
            rep.fail(f"never {label}")
            rep.info(f"matched: {hit.group(0)!r}")
        else:
            rep.ok(f"never {label}")

    # --- Step 1 DoD states documentation-only ---
    dod = read(root / DOD_DOC).lower()
    if dod.strip():
        for label, patterns in [
            ("no application tests exist", [r"no\s+(?:unit|application|automated)", r"not\s+applicable"]),
            ("application CI is NOT APPLICABLE", [r"not\s+applicable"]),
            ("GO is owner-conferred", [r"owner", r"never\s+self-declar"]),
            ("exact-SHA evidence is required", [r"exact[- ]sha", r"exact\s+commit"]),
        ]:
            rep.check(
                any(re.search(p, dod, re.IGNORECASE) for p in patterns),
                f"Step 1 Definition of Done states: {label}",
            )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
