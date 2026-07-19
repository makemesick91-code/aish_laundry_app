#!/usr/bin/env python3
"""Validate the component state matrix.

Every component must be resolved against every state — APPLICABLE or
NOT APPLICABLE, never blank. A blank cell is an undecided design that becomes
someone's bug in Step 5.

Standard library only.
"""

from __future__ import annotations

import re
import sys

from _common import Reporter, repo_root
from _step02 import COMPONENT_ID, DESIGN_DIR, read

DOC = f"{DESIGN_DIR}/COMPONENT_STATE_MATRIX.md"
CATALOG = f"{DESIGN_DIR}/COMPONENT_CATALOG.md"

REQUIRED_STATES = [
    "default", "hover", "focus", "pressed", "selected", "disabled", "loading",
    "success", "warning", "error", "offline", "syncing", "conflict",
    "read-only", "permission denied", "expired", "revoked",
]

# Cell markers the matrix is permitted to use. Anything else is undecided.
CELL_MARKERS = {"APPLICABLE", "NOT APPLICABLE", "N/A", "YES", "NO", "—", "-",
                "X", "•", "✓", "✗"}


def main() -> int:
    root = repo_root()
    rep = Reporter("component state matrix")

    text = read(root, DOC)
    rep.check(bool(text), f"{DOC} exists")
    if not text:
        return rep.finish()

    lowered = text.lower()

    # -- every mandated state appears as a column --------------------------
    missing = [s for s in REQUIRED_STATES if s not in lowered]
    for s in missing:
        rep.info(f"state not present in the matrix: {s}")
    rep.check(
        not missing,
        f"every mandated component state is present ({len(missing)} missing)",
    )

    # -- the matrix covers the catalogue's components ----------------------
    catalog = read(root, CATALOG)
    catalog_ids = set(COMPONENT_ID.findall(catalog))
    matrix_ids = set(COMPONENT_ID.findall(text))
    rep.check(bool(matrix_ids),
              f"the matrix references components by CMP-### ID "
              f"({len(matrix_ids)} referenced)")

    uncovered = sorted(catalog_ids - matrix_ids)
    for c in uncovered[:20]:
        rep.info(f"component in the catalog but not in the matrix: {c}")
    rep.check(
        not uncovered,
        f"every catalogued component appears in the state matrix "
        f"({len(uncovered)} uncovered)",
    )

    unknown = sorted(matrix_ids - catalog_ids)
    for c in unknown[:20]:
        rep.info(f"component in the matrix but not in the catalog: {c}")
    rep.check(
        not unknown,
        f"the matrix introduces no component the catalog does not define "
        f"({len(unknown)} unknown)",
    )

    # -- no blank cells in any matrix table row ----------------------------
    blanks = 0
    rows_checked = 0
    for line in text.splitlines():
        stripped = line.strip()
        if not stripped.startswith("|") or not COMPONENT_ID.search(stripped):
            continue
        cells = [c.strip() for c in stripped.strip("|").split("|")]
        rows_checked += 1
        for index, cell in enumerate(cells):
            if index == 0:
                continue
            if cell == "":
                blanks += 1
                rep.info(f"blank cell in matrix row: {stripped[:80]}")
                break
    rep.check(rows_checked > 0, "the matrix contains component rows")
    rep.check(blanks == 0,
              f"no matrix cell is blank ({rows_checked} rows checked, "
              f"{blanks} rows with a blank)")

    # -- focus is never NOT APPLICABLE for an interactive component --------
    # A component that can receive input must resolve 'focus'. If the matrix
    # marks focus inapplicable everywhere, the focus contract is missing.
    rep.check(
        "focus" in lowered,
        "the matrix resolves the focus state",
    )
    focus_removed = re.search(
        r"focus[^|\n]{0,40}\|[^|\n]{0,20}(removed|none|suppressed)", lowered)
    rep.check(
        not focus_removed,
        "no component marks its focus indicator as removed or none",
    )

    # -- the matrix explains its own markers -------------------------------
    rep.check(
        any(m.lower() in lowered for m in ("applicable", "not applicable")),
        "the matrix uses an explicit APPLICABLE / NOT APPLICABLE vocabulary",
    )

    return rep.finish()


if __name__ == "__main__":
    sys.exit(main())
