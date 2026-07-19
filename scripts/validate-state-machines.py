#!/usr/bin/env python3
"""Validate the Step 1 state machine documents.

Asserts the canonical status sets are complete and spelled exactly, that each
machine has both a diagram and a written transition table, and that forbidden
transitions are stated rather than merely implied. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import (  # noqa: E402
    DELIVERY_STATUSES,
    ORDER_STATUSES,
    QC_STATUSES,
    STATE_MACHINE_DOCS,
    mermaid_blocks,
    read,
)

SM_DIR = "docs/state-machines"

STATUS_SETS = [
    ("ORDER_STATE_MACHINE.md", ORDER_STATUSES, "order"),
    ("PICKUP_DELIVERY_STATE_MACHINE.md", DELIVERY_STATUSES, "pickup/delivery"),
    ("QUALITY_CONTROL_STATE_MACHINE.md", QC_STATUSES, "quality control"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("state-machines")

    # --- all ten documents exist ---
    for name in STATE_MACHINE_DOCS:
        rep.check((root / SM_DIR / name).is_file(), f"exists: {SM_DIR}/{name}")

    # --- canonical status sets are complete and exact ---
    for filename, statuses, label in STATUS_SETS:
        path = root / SM_DIR / filename
        if not path.is_file():
            continue
        text = read(path)
        missing = [s for s in statuses if re.search(rf"\b{re.escape(s)}\b", text) is None]
        if missing:
            rep.fail(f"{label} machine declares all {len(statuses)} canonical statuses")
            for s in missing:
                rep.info(f"missing status: {s}")
        else:
            rep.ok(f"{label} machine declares all {len(statuses)} canonical statuses")

    # --- every machine has a diagram AND a written table ---
    for name in STATE_MACHINE_DOCS:
        path = root / SM_DIR / name
        if not path.is_file():
            continue
        text = read(path)

        rep.check(bool(mermaid_blocks(text)), f"{name} contains a Mermaid diagram")

        # A markdown table is the specification; the diagram is an aid.
        table_rows = [
            ln for ln in text.splitlines()
            if ln.strip().startswith("|") and ln.count("|") >= 3
        ]
        rep.check(
            len(table_rows) >= 5,
            f"{name} contains a written transition table ({len(table_rows)} rows)",
        )

        # Forbidden transitions must be explicit, not merely implied by omission.
        rep.check(
            re.search(r"forbidden|not permitted|disallowed|illegal", text, re.IGNORECASE)
            is not None,
            f"{name} states forbidden transitions explicitly",
        )

        # Transitions need an actor and a precondition to be enforceable.
        lower = text.lower()
        rep.check("actor" in lower or "role" in lower, f"{name} names transition actors")
        rep.check(
            "precondition" in lower or "guard" in lower,
            f"{name} states transition preconditions",
        )

    # --- the aging anchor must not be restartable ---
    order_path = root / SM_DIR / "ORDER_STATE_MACHINE.md"
    unclaimed_path = root / SM_DIR / "UNCLAIMED_LAUNDRY_STATE_MACHINE.md"
    combined = read(order_path) + "\n" + read(unclaimed_path)
    if combined.strip():
        lower = combined.lower()
        rep.check(
            "first" in lower and "ready_for_pickup" in lower,
            "aging is anchored to the FIRST READY_FOR_PICKUP",
        )
        rep.check(
            re.search(r"(?:not|never|does not)\s+(?:be\s+)?resta?rt", lower) is not None,
            "aging clock is documented as never restarting",
        )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
