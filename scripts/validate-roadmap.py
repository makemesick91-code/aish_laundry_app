#!/usr/bin/env python3
"""Validate docs/ROADMAP.md: Steps 0..14, canonical titles, Steps 1-14 PLANNED.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

ROADMAP = "docs/ROADMAP.md"

CANONICAL_TITLES = {
    0: "Master Source and Governance",
    1: "Product Requirement and Domain Model",
    2: "Design System and UX Foundation",
    3: "Runtime, Authentication, Multi-Tenancy, and RBAC",
    4: "Laundry Master Data",
    5: "POS, Order, and Payment Foundation",
    6: "Production Operations",
    7: "Customer Tracking and WhatsApp",
    8: "Pickup and Delivery Operations",
    9: "Unclaimed Laundry and Cashflow Recovery",
    10: "Finance, Reports, and Owner Portfolio",
    11: "Customer Android Experience",
    12: "Subscription and Platform Administration",
    13: "Security, Performance, Backup, and Recovery",
    14: "Pilot and Commercial Launch",
}

# A roadmap entry line: heading, list item, or table row starting with "Step N".
ENTRY = re.compile(
    r"^\s{0,3}(?:#{1,6}\s*|[-*+]\s+|\|\s*|\d+\.\s+)?\*{0,2}Step\s+(\d{1,2})\b",
    re.IGNORECASE,
)


def normalize(text: str) -> str:
    """Lowercase, collapse whitespace, normalize dashes and punctuation."""
    text = text.replace("—", " ").replace("–", " ").replace("-", " ")
    text = re.sub(r"[|*_`:#]", " ", text)
    text = re.sub(r"[,\.]", "", text)
    return re.sub(r"\s+", " ", text).strip().lower()


def main() -> int:
    root = repo_root()
    rep = Reporter("roadmap")

    path = root / ROADMAP
    if not rep.check(path.is_file(), f"{ROADMAP} exists"):
        return rep.finish()

    lines = read_text(path).splitlines()

    entries: dict[int, list[tuple[int, str]]] = {}
    order: list[tuple[int, int]] = []
    for idx, line in enumerate(lines):
        m = ENTRY.match(line)
        if not m:
            continue
        num = int(m.group(1))
        entries.setdefault(num, []).append((idx, line))
        order.append((idx, num))

    if not entries:
        rep.fail(f"{ROADMAP} contains recognizable 'Step N' roadmap entries")
        return rep.finish()

    # Coverage: 0..14, exactly once each.
    for n in range(0, 15):
        hits = entries.get(n, [])
        if len(hits) == 1:
            rep.ok(f"Step {n} declared exactly once (line {hits[0][0] + 1})")
        elif not hits:
            rep.fail(f"Step {n} MISSING from {ROADMAP}")
        else:
            rep.fail(
                f"Step {n} duplicated at lines "
                + ", ".join(str(i + 1) for i, _ in hits)
            )

    for n in sorted(entries):
        if not (0 <= n <= 14):
            rep.fail(f"unexpected roadmap step number: Step {n}")

    # Titles
    for n, title in CANONICAL_TITLES.items():
        hits = entries.get(n, [])
        if len(hits) != 1:
            continue
        line = hits[0][1]
        if normalize(title) in normalize(line):
            rep.ok(f"Step {n} title matches canonical: {title}")
        else:
            rep.fail(f"Step {n} title does not match canonical '{title}'")
            rep.info(f"line: {line.strip()}")

    # Steps 1-14 marked PLANNED (same line or its block until the next entry).
    entry_lines = sorted(i for i, _ in order)
    for n in range(1, 15):
        hits = entries.get(n, [])
        if len(hits) != 1:
            continue
        start = hits[0][0]
        following = [i for i in entry_lines if i > start]
        end = following[0] if following else len(lines)
        block = "\n".join(lines[start:end]).upper()
        rep.check(
            "PLANNED" in block,
            f"Step {n} is marked PLANNED",
        )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
