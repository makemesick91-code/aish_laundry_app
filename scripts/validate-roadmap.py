#!/usr/bin/env python3
"""Validate docs/ROADMAP.md: Steps 0..14, canonical titles, Steps 1-14 PLANNED.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import (  # noqa: E402
    Reporter,
    declared_statuses,
    read_text,
    repo_root,
    run_main,
)

ROADMAP = "docs/ROADMAP.md"

# The step currently under way. Bump this only when a step actually starts, in the
# same pull request that moves the status in ROADMAP.md and STATUS.md.
CURRENT_STEP = 1
CURRENT_STEP_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

# Statuses that must never appear against a step later than CURRENT_STEP. Work
# leaking forward out of its declared scope is a roadmap-lock violation.
FORWARD_LEAK_STATUSES = ["IN PROGRESS", "TESTED", "WATCH", "GO", "NO-GO"]

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

    # Step status posture.
    #
    # Step 1 is the step currently under way, so it may legitimately carry a
    # working status. Steps 2..14 must still be PLANNED: a later step showing any
    # other status means work has leaked forward out of its declared scope, which
    # the roadmap lock in MASTER_SOURCE.md §24 forbids.
    entry_lines = sorted(i for i, _ in order)

    def block_for(n: int) -> str | None:
        hits = entries.get(n, [])
        if len(hits) != 1:
            return None
        start = hits[0][0]
        following = [i for i in entry_lines if i > start]
        end = following[0] if following else len(lines)
        return "\n".join(lines[start:end]).upper()

    declared = declared_statuses(block_for(CURRENT_STEP))
    if declared:
        allowed = [s for s in declared if s in CURRENT_STEP_ALLOWED]
        if allowed:
            rep.ok(
                f"Step {CURRENT_STEP} carries an allowed working status "
                f"(declared: {', '.join(sorted(set(declared)))})"
            )
        else:
            rep.fail(
                f"Step {CURRENT_STEP} must carry one of {CURRENT_STEP_ALLOWED}; "
                f"declared: {', '.join(sorted(set(declared)))}"
            )
    else:
        rep.fail(f"Step {CURRENT_STEP} declares a recognisable status")

    for n in range(CURRENT_STEP + 1, 15):
        declared = declared_statuses(block_for(n))
        if not declared:
            rep.fail(f"Step {n} declares a recognisable status")
            continue
        leaked = sorted({s for s in declared if s in FORWARD_LEAK_STATUSES})
        if leaked:
            rep.fail(
                f"Step {n} must be PLANNED only, but declares: "
                + ", ".join(leaked)
            )
        elif "PLANNED" in declared:
            rep.ok(f"Step {n} is marked PLANNED")
        else:
            rep.fail(
                f"Step {n} is marked PLANNED; declared: "
                + ", ".join(sorted(set(declared)))
            )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
