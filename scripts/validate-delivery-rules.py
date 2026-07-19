#!/usr/bin/env python3
"""Validate the pickup and delivery model.

Two recurring failure modes are checked hard: claiming route optimization the
product does not perform, and letting an external courier see more than the job
they were assigned. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import (  # noqa: E402
    DELIVERY_STATUSES,
    corpus,
    is_negated,
    read,
    strip_code_blocks,
)

DOMAIN_DOC = "docs/domain/PICKUP_DELIVERY_DOMAIN.md"
SM_DOC = "docs/state-machines/PICKUP_DELIVERY_STATE_MACHINE.md"
SETTLEMENT_DOC = "docs/state-machines/COURIER_SETTLEMENT_STATE_MACHINE.md"

REQUIRED_RULES: list[tuple[str, list[str]]] = [
    ("proof of pickup and delivery is mandatory", [r"proof"]),
    (
        "proof mechanisms include OTP, photo, signature, recipient name",
        [r"signature"],
    ),
    ("proof artifacts are private", [r"private", r"signed\s+url"]),
    ("time windows are used rather than exact times", [r"time\s+window"]),
    ("service zones are defined", [r"zone"]),
    ("courier assignment is explicit", [r"assign"]),
    ("cash collection is a financial transaction", [r"cash"]),
    ("courier cash is reconciled", [r"reconcil"]),
    ("a failed delivery is a first-class outcome", [r"failed", r"fail"]),
    ("reschedule is supported", [r"reschedul"]),
    (
        "the external courier uses an expiring guest link",
        [r"guest\s+link", r"guest\s+job"],
    ),
    ("the guest link is revocable", [r"revok", r"revoc"]),
    ("the guest link is scoped to one job", [r"one\s+job", r"assigned\s+job", r"single\s+job"]),
    ("the guest token is high-entropy and hashed", [r"hash", r"high[- ]entropy"]),
    ("offline capture is supported for couriers", [r"offline"]),
]

# Route optimization claims the product must never make.
FALSE_OPTIMIZATION = [
    (r"\boptimal\s+route\b", "'optimal route'"),
    (r"\broute\s+optimi[sz]ation\s+engine\b", "a route optimization engine"),
    (r"\bguaranteed\s+(?:arrival|delivery)\s+time\b", "a guaranteed arrival time"),
    (r"\bshortest\s+possible\s+route\b", "'shortest possible route'"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("delivery-rules")

    dpath = root / DOMAIN_DOC
    spath = root / SM_DOC
    ok = rep.check(dpath.is_file(), f"{DOMAIN_DOC} exists")
    rep.check(spath.is_file(), f"{SM_DOC} exists")
    rep.check((root / SETTLEMENT_DOC).is_file(), f"{SETTLEMENT_DOC} exists")
    if not ok:
        return rep.finish()

    text = read(dpath) + "\n" + read(spath) + "\n" + read(root / SETTLEMENT_DOC)
    lower = text.lower()

    # --- canonical statuses ---
    missing = [s for s in DELIVERY_STATUSES if re.search(rf"\b{s}\b", text) is None]
    if missing:
        rep.fail(f"all {len(DELIVERY_STATUSES)} canonical delivery statuses documented")
        for s in missing:
            rep.info(f"missing status: {s}")
    else:
        rep.ok(f"all {len(DELIVERY_STATUSES)} canonical delivery statuses documented")

    # --- required rules ---
    for label, patterns in REQUIRED_RULES:
        rep.check(
            any(re.search(p, lower, re.IGNORECASE) for p in patterns),
            f"delivery model states: {label}",
        )

    # --- route suggestion, not optimization ---
    rep.check(
        re.search(r"suggest|usulan", lower) is not None,
        "route ordering is described as a suggestion",
    )

    whole = strip_code_blocks(corpus(root))
    for pattern, label in FALSE_OPTIMIZATION:
        offending = []
        for m in re.finditer(pattern, whole, re.IGNORECASE):
            # Sentence scope: "the product never claims an optimal route" is
            # correct prose and must not be flagged, while a claim sitting near
            # an unrelated negated sentence must not be excused.
            if is_negated(whole, m.start()):
                continue
            offending.append(m.group(0))
        if offending:
            rep.fail(f"corpus never claims {label}")
            rep.info(f"unnegated: {offending[:3]}")
        else:
            rep.ok(f"corpus never claims {label}")

    # --- settlement lifecycle ---
    settle = read(root / SETTLEMENT_DOC).lower()
    if settle.strip():
        for label, patterns in [
            ("expected versus actual cash", [r"expected", r"actual"]),
            ("variance is recorded", [r"variance", r"discrepan"]),
            ("handover is tracked", [r"hand[- ]?over", r"handover"]),
        ]:
            rep.check(
                any(re.search(p, settle) for p in patterns),
                f"courier settlement covers: {label}",
            )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
