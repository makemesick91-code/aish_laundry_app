#!/usr/bin/env python3
"""Validate the domain glossary.

The glossary is binding: every canonical domain term must have an entry, so that
later Steps cannot quietly introduce a synonym. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import AGGREGATES, ORDER_STATUSES, read  # noqa: E402

GLOSSARY = "docs/domain/DOMAIN_GLOSSARY.md"

# Terms whose meaning is load-bearing across the whole product.
REQUIRED_TERMS = [
    "Tenant",
    "Membership",
    "Outlet",
    "Brand",
    "Aggregate",
    "Bounded Context",
    "Money",
    "Idempoten",
    "Client Reference",
    "Tracking Token",
    "Aging",
    "Rework",
    "Quiet Hours",
    "Kiloan",
    "Satuan",
    "Nota",
    "Courier",
    "Proof",
    "Reversal",
    "Shift",
]

MIN_ENTRIES = 40


def main() -> int:
    root = repo_root()
    rep = Reporter("domain-glossary")

    path = root / GLOSSARY
    if not rep.check(path.is_file(), f"{GLOSSARY} exists"):
        return rep.finish()

    text = read(path)
    lower = text.lower()

    # --- required terms defined ---
    for term in REQUIRED_TERMS:
        rep.check(term.lower() in lower, f"glossary defines term: {term}")

    # --- canonical order statuses appear ---
    missing = [s for s in ORDER_STATUSES if s not in text]
    if missing:
        rep.fail("glossary lists the canonical order statuses")
        for s in missing[:8]:
            rep.info(f"missing status: {s}")
    else:
        rep.ok("glossary lists the canonical order statuses")

    # --- aggregates are named consistently with the catalogue ---
    named = [a for a in AGGREGATES if re.search(rf"\b{re.escape(a)}\b", text)]
    rep.check(
        len(named) >= 10,
        f"glossary references the aggregate vocabulary ({len(named)}/{len(AGGREGATES)})",
    )

    # --- substance: count entries ---
    #
    # An entry is a heading or a table row that introduces a term.
    headings = re.findall(r"^#{2,6}\s+\S.*$", text, flags=re.MULTILINE)
    table_rows = [
        ln for ln in text.splitlines()
        if ln.strip().startswith("|") and ln.count("|") >= 3
        and not re.match(r"^\s*\|[\s:|-]+\|\s*$", ln)
    ]
    entries = len(headings) + len(table_rows)
    rep.check(
        entries >= MIN_ENTRIES,
        f"glossary is substantial: ~{entries} entries (minimum {MIN_ENTRIES})",
    )

    # --- the glossary states that it is binding ---
    rep.check(
        re.search(r"binding|canonical|must\s+be\s+used|exactly", lower) is not None,
        "glossary states that its terms are binding",
    )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
