#!/usr/bin/env python3
"""Validate the use-case catalogue, MVP scope, journeys, and success metrics.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import PERSONAS, read, requirement_ids, strip_code_blocks  # noqa: E402

USE_CASES = "docs/product/USE_CASE_CATALOG.md"
MVP = "docs/product/MVP_SCOPE.md"
USER_JOURNEYS = "docs/product/USER_JOURNEYS.md"
OPS_JOURNEYS = "docs/product/OPERATIONAL_JOURNEYS.md"
METRICS = "docs/product/SUCCESS_METRICS.md"
JTBD = "docs/product/JOBS_TO_BE_DONE.md"
OPEN_Q = "docs/product/ASSUMPTIONS_AND_OPEN_QUESTIONS.md"

MIN_USE_CASES = 30

# Claiming a measured result before Step 14 would be a false claim.
MEASURED_CLAIM = [
    (r"\bwe\s+(?:achieved|measured|reached)\b", "claims a measured result"),
    (r"\bcurrent(?:ly)?\s+at\s+\d+(?:\.\d+)?%", "claims a current metric value"),
    (r"\bbaseline\s+is\s+\d", "claims an established baseline"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("use-cases")

    for rel in (USE_CASES, MVP, USER_JOURNEYS, OPS_JOURNEYS, METRICS, JTBD, OPEN_Q):
        rep.check((root / rel).is_file(), f"exists: {rel}")

    uc_path = root / USE_CASES
    if not uc_path.is_file():
        return rep.finish()

    uc = read(uc_path)
    uc_lower = uc.lower()

    # --- catalogue size ---
    rows = [
        ln for ln in uc.splitlines()
        if ln.strip().startswith("|") and ln.count("|") >= 3
        and not re.match(r"^\s*\|[\s:|-]+\|\s*$", ln)
    ]
    headings = re.findall(r"^#{2,6}\s+\S.*$", uc, flags=re.MULTILINE)
    count = max(len(rows), len(headings))
    rep.check(
        count >= MIN_USE_CASES,
        f"use-case catalogue is substantial (~{count} entries, minimum {MIN_USE_CASES})",
    )

    # --- use cases name actors and trace to requirements ---
    actors_named = [p for p in PERSONAS if p.lower() in uc_lower]
    rep.check(
        len(actors_named) >= 8,
        f"use cases name their actors ({len(actors_named)}/{len(PERSONAS)} personas referenced)",
    )
    rep.check(
        len(requirement_ids(uc)) >= 20,
        "use cases cite requirement IDs",
    )

    # --- MVP scope states what is in AND out ---
    mvp = read(root / MVP).lower()
    if mvp.strip():
        rep.check("mvp" in mvp, "MVP scope names the MVP")
        rep.check(
            any(k in mvp for k in ("out of scope", "not in the mvp", "after the mvp", "non-goal")),
            "MVP scope states what is explicitly out of scope",
        )
        for step_kw in ("step 3", "step 5", "step 7", "step 9", "step 10"):
            rep.check(step_kw in mvp, f"MVP scope maps capability to {step_kw.title()}")

    # --- journeys ---
    for rel, label in ((USER_JOURNEYS, "user"), (OPS_JOURNEYS, "operational")):
        text = read(root / rel)
        if not text.strip():
            continue
        rep.check(
            "```mermaid" in text,
            f"{label} journeys include a Mermaid diagram",
        )
        without = re.sub(r"```.*?```", "", text, flags=re.DOTALL)
        rep.check(
            len(without.split()) >= 300,
            f"{label} journeys have written narrative alongside diagrams",
        )

    # --- success metrics are forward-looking, never claimed as achieved ---
    metrics_text = read(root / METRICS)
    metrics_lower = metrics_text.lower()
    if metrics_text.strip():
        rep.check(
            re.search(r"will\s+be\s+measured|to\s+be\s+measured|not\s+yet\s+measured",
                      metrics_lower) is not None,
            "success metrics state what will be measured, not what was achieved",
        )
        rep.check(
            "step 14" in metrics_lower,
            "success metrics defer baselines and targets to Step 14",
        )
        prose = strip_code_blocks(metrics_text)
        for pattern, label in MEASURED_CLAIM:
            hit = re.search(pattern, prose, re.IGNORECASE)
            if hit:
                rep.fail(f"success metrics never {label}")
                rep.info(f"matched: {hit.group(0)!r}")
            else:
                rep.ok(f"success metrics never {label}")

    # --- open questions are recorded rather than invented away ---
    openq = read(root / OPEN_Q).lower()
    if openq.strip():
        rep.check(
            any(k in openq for k in ("open question", "assumption")),
            "open questions register records assumptions and open questions",
        )
        rep.check(
            "owner" in openq,
            "open questions are directed to the repository owner",
        )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
