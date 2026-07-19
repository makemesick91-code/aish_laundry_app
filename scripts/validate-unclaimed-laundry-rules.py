#!/usr/bin/env python3
"""Validate the unclaimed-laundry model.

Two things matter most: aging is anchored to the FIRST READY_FOR_PICKUP and never
restarts, and the product never automatically disposes of a customer's property.
Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import corpus, is_negated, read  # noqa: E402

DOMAIN_DOC = "docs/domain/UNCLAIMED_LAUNDRY_DOMAIN.md"
SM_DOC = "docs/state-machines/UNCLAIMED_LAUNDRY_STATE_MACHINE.md"

LADDER = ["H+1", "H+3", "H+7", "H+14"]

DASHBOARD_FIELDS = [
    ("order count", ["order count"]),
    ("customer count", ["customer count"]),
    ("held invoices", ["held invoice"]),
    ("unpaid balance", ["unpaid balance"]),
    ("order age", ["order age", "age"]),
    ("outlet", ["outlet"]),
    ("last reminder", ["last reminder"]),
    ("follow-up officer", ["follow-up officer", "follow up officer"]),
    ("reason not collected", ["reason not collected"]),
]

AGING_BUCKETS = ["1", "2", "3", "6", "7", "13", "14", "30"]

REQUIRED_RULES: list[tuple[str, list[str]]] = [
    (
        "aging is anchored to the FIRST READY_FOR_PICKUP",
        [r"first[^.\n]{0,60}ready_for_pickup", r"ready_for_pickup[^.\n]{0,60}first"],
    ),
    (
        "the aging clock never restarts",
        [r"(?:not|never|does\s+not)\s+resta?rt", r"clock[^.\n]{0,40}(?:not|never)\s+reset",
         r"(?:not|never)\s+reset"],
    ),
    (
        "the first-ready timestamp is immutable",
        [r"immutab", r"recorded\s+once", r"written\s+once"],
    ),
    ("each ladder stage fires once", [r"once", r"dedup"]),
    ("quiet hours are respected", [r"quiet\s+hours", r"20\.00", r"20:00"]),
    ("opt-out is honoured", [r"opt[- ]out"]),
    (
        "the H+7 follow-up task is assignable",
        [r"assignab", r"follow[- ]up\s+task"],
    ),
    (
        "the H+14 escalation reaches a manager or owner",
        [r"escalat\w+[^.\n]{0,80}(?:manager|owner)",
         r"(?:manager|owner)[^.\n]{0,60}escalat"],
    ),
]

# The absolute prohibition. Any of these appearing as a product behaviour is a
# refusal-level violation (Rule 10, Master Source §11.4).
DISPOSAL_TERMS = [
    "auto-discard",
    "automatically discard",
    "automatically dispose",
    "automatic disposal",
    "auto-dispose",
    "automatically sell",
    "auto-sell",
    "automatically auction",
    "automatically donate",
    "automatically transfer ownership",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("unclaimed-laundry-rules")

    dpath = root / DOMAIN_DOC
    spath = root / SM_DOC
    ok = rep.check(dpath.is_file(), f"{DOMAIN_DOC} exists")
    rep.check(spath.is_file(), f"{SM_DOC} exists")
    if not ok:
        return rep.finish()

    text = read(dpath) + "\n" + read(spath)
    lower = text.lower()

    # --- the ladder ---
    for stage in LADDER:
        rep.check(stage in text, f"reminder ladder stage present: {stage}")

    # --- dashboard minimum fields ---
    for label, keywords in DASHBOARD_FIELDS:
        rep.check(
            any(k in lower for k in keywords),
            f"dashboard field documented: {label}",
        )

    # --- aging buckets ---
    bucket_hits = sum(1 for b in AGING_BUCKETS if b in text)
    rep.check(
        bucket_hits >= 6,
        f"aging buckets documented ({bucket_hits}/{len(AGING_BUCKETS)} boundaries present)",
    )

    # --- core rules ---
    for label, patterns in REQUIRED_RULES:
        rep.check(
            any(re.search(p, lower, re.IGNORECASE) for p in patterns),
            f"unclaimed model states: {label}",
        )

    # --- the absolute prohibition is stated ---
    prohibition = [
        r"never\s+automatically\s+(?:discard|dispose|sell|transfer)",
        r"(?:no|never)[^.\n]{0,60}automatic\s+disposal",
        r"absolute\s+prohibition",
        r"never\s+(?:discard|sell|auction|donate)",
    ]
    rep.check(
        any(re.search(p, lower, re.IGNORECASE) for p in prohibition),
        "the prohibition on automatic disposal is stated explicitly",
    )

    # --- and never contradicted anywhere in the corpus ---
    whole = corpus(root).lower()
    offending = []
    for term in DISPOSAL_TERMS:
        for m in re.finditer(re.escape(term), whole):
            # Sentence scope, not a window: "the product never automatically
            # discards laundry" is the correct form and must not be flagged.
            if is_negated(whole, m.start()):
                continue
            offending.append(term)
            break
    if offending:
        rep.fail("no automatic disposal behaviour is proposed anywhere in the corpus")
        for term in offending[:10]:
            rep.info(f"unnegated disposal language: {term!r}")
    else:
        rep.ok("no automatic disposal behaviour is proposed anywhere in the corpus")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
