#!/usr/bin/env python3
"""Validate the tenant-boundary and data-ownership model.

Tenant isolation is a hard gate; this validator asserts the Step 1 model states
it correctly rather than assuming it. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import AGGREGATES, corpus, read  # noqa: E402

BOUNDARIES_DOC = "docs/domain/TENANT_BOUNDARIES.md"
OWNERSHIP_DOC = "docs/domain/DATA_OWNERSHIP.md"

HIERARCHY_LEVELS = [
    "User Account",
    "Membership",
    "Tenant",
    "Laundry Brand",
    "Outlet",
]

REQUIRED_RULES: list[tuple[str, list[str]]] = [
    (
        "a client-supplied tenant ID is never authorisation proof",
        [r"client[- ]supplied[^.\n]{0,80}never", r"never\s+authoriz\w+\s+proof",
         r"never\s+authoris\w+\s+proof", r"not\s+authoriz\w+\s+proof"],
    ),
    (
        "membership and permission are verified server-side",
        [r"server[- ]side[^.\n]{0,80}(?:verif|member|permission)",
         r"(?:verif\w+)[^.\n]{0,60}membership"],
    ),
    (
        "every business aggregate carries tenant ownership",
        [r"tenant[_ ]?id", r"tenant\s+ownership", r"tenant[- ]scoped"],
    ),
    (
        "records are never merged on matching name, email, or phone",
        [r"never\s+merge", r"not\s+merged?\s+(?:merely\s+)?because",
         r"no\s+merg\w+\s+across"],
    ),
    (
        "a customer profile is tenant-scoped, not global",
        [r"tenant[- ]scoped\s+customer", r"customer[^.\n]{0,60}tenant[- ]scoped",
         r"no\s+global\s+(?:shared\s+)?customer"],
    ),
    (
        "the same phone number in two tenants is two separate profiles",
        [r"same\s+phone[^.\n]{0,120}(?:two|separate|different|unrelated)",
         r"(?:two|separate|different|unrelated)[^.\n]{0,80}phone\s+number"],
    ),
    (
        "cross-tenant exposure is an automatic NO-GO",
        [r"cross[- ]tenant[^.\n]{0,80}no-?go", r"no-?go[^.\n]{0,80}cross[- ]tenant"],
    ),
    (
        "the owner portfolio does not weaken isolation",
        [r"portfolio[^.\n]{0,120}(?:not\s+weaken|without\s+weaken|must\s+not)",
         r"(?:not\s+weaken|never\s+weaken)[^.\n]{0,80}isolation"],
    ),
    (
        "caches, queues, and object keys are tenant-scoped",
        [r"cache\s+key[^.\n]{0,80}tenant", r"tenant[^.\n]{0,60}cache\s+key",
         r"object\s+key[^.\n]{0,80}tenant"],
    ),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("tenant-boundaries")

    path = root / BOUNDARIES_DOC
    if not rep.check(path.is_file(), f"{BOUNDARIES_DOC} exists"):
        return rep.finish()
    rep.check((root / OWNERSHIP_DOC).is_file(), f"{OWNERSHIP_DOC} exists")

    scoped = read(path) + "\n" + read(root / OWNERSHIP_DOC)
    whole = corpus(root)

    # --- hierarchy is stated in full ---
    for level in HIERARCHY_LEVELS:
        rep.check(
            re.search(re.escape(level), scoped, re.IGNORECASE) is not None,
            f"tenant hierarchy level documented: {level}",
        )

    # --- the isolation rules are stated, in the tenant documents or the corpus ---
    for label, patterns in REQUIRED_RULES:
        hit = any(re.search(p, scoped, re.IGNORECASE) for p in patterns) or any(
            re.search(p, whole, re.IGNORECASE) for p in patterns
        )
        rep.check(hit, f"tenant model states: {label}")

    # --- every aggregate appears in the boundary model ---
    #
    # An aggregate absent from the tenant-boundary document has no stated owner,
    # which is exactly how a cross-tenant leak gets designed in.
    missing = [
        name for name in AGGREGATES
        if re.search(rf"\b{re.escape(name)}\b", scoped) is None
    ]
    if missing:
        rep.fail("every aggregate appears in the tenant-boundary model")
        for name in missing[:15]:
            rep.info(f"aggregate missing a stated tenant boundary: {name}")
    else:
        rep.ok("every aggregate appears in the tenant-boundary model")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
