#!/usr/bin/env python3
"""Validate the domain invariant catalogue.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import read  # noqa: E402

DOC = "docs/domain/DOMAIN_INVARIANTS.md"

# The invariants that derive from the two hard gates and the core differentiators.
REQUIRED_INVARIANTS: list[tuple[str, list[str]]] = [
    ("every business record carries a tenant identifier", [r"tenant[_ ]?id", r"tenant[- ]scoped"]),
    (
        "a client-supplied tenant ID is not authorisation",
        [r"client[- ]supplied", r"never\s+authoris", r"never\s+authoriz"],
    ),
    ("money is integer Rupiah", [r"integer\s+rupiah"]),
    ("floating point is forbidden in money paths", [r"float"]),
    ("payments are idempotent", [r"idempoten"]),
    ("historical prices are immutable", [r"immutab", r"price\s+snapshot", r"historical\s+price"]),
    ("financial records are not hard-deleted", [r"delete"]),
    ("corrections are reversal-based", [r"revers"]),
    (
        "the first-ready timestamp is immutable and aging never restarts",
        [r"first[^.\n]{0,60}ready_for_pickup", r"never\s+resta?rt", r"not\s+resta?rt"],
    ),
    ("order status is one of the canonical set", [r"canonical", r"order\s+status"]),
    ("only enumerated transitions occur", [r"transition"]),
    ("the tracking token is not the order number", [r"order\s+number"]),
    ("only the token hash is stored", [r"hash"]),
    ("custody transfer requires proof", [r"proof"]),
    ("courier cash variance is recorded", [r"variance", r"reconcil"]),
    ("a retry reuses its client reference", [r"client[_ ]?reference"]),
    ("duplicate orders and payments are unacceptable", [r"duplicate"]),
    ("invariants are enforced server-side", [r"server[- ]side"]),
    ("concurrent operations are serialised", [r"concurren", r"lock", r"transaction"]),
]

MIN_INVARIANTS = 20


def main() -> int:
    root = repo_root()
    rep = Reporter("domain-invariants")

    path = root / DOC
    if not rep.check(path.is_file(), f"{DOC} exists"):
        return rep.finish()

    text = read(path)
    lower = text.lower()

    for label, patterns in REQUIRED_INVARIANTS:
        rep.check(
            any(re.search(p, lower, re.IGNORECASE) for p in patterns),
            f"invariant catalogued: {label}",
        )

    # --- count enumerated invariants ---
    numbered = re.findall(r"^\s{0,3}\d+\.\s+\S", text, flags=re.MULTILINE)
    table_rows = [
        ln for ln in text.splitlines()
        if ln.strip().startswith("|") and ln.count("|") >= 3
        and not re.match(r"^\s*\|[\s:|-]+\|\s*$", ln)
    ]
    count = len(numbered) + len(table_rows)
    rep.check(
        count >= MIN_INVARIANTS,
        f"catalogue enumerates at least {MIN_INVARIANTS} invariants (found ~{count})",
    )

    # --- invariants must name their owning aggregate and enforcement step ---
    rep.check("aggregate" in lower, "invariants name their owning aggregate")
    rep.check(
        re.search(r"\bstep\s+\d", lower) is not None,
        "invariants name the roadmap Step that enforces them",
    )

    # --- honesty: nothing is enforced yet ---
    rep.check(
        re.search(
            r"not\s+(?:yet\s+)?enforced|no\s+runtime|not\s+implemented|absent",
            lower,
        )
        is not None,
        "catalogue states that no invariant is enforced yet",
    )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
