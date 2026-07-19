#!/usr/bin/env python3
"""Structurally validate fenced code blocks and Mermaid diagrams.

This performs STRUCTURAL validation only. It does not render diagrams and it
never claims that a diagram renders correctly — only that its fence is closed,
its declaration is recognised, and it is not empty. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main, tracked_files  # noqa: E402
from _step01 import fenced_blocks_balanced, mermaid_blocks, read  # noqa: E402

# Diagram kinds Mermaid recognises that this project uses.
KNOWN_DIAGRAM_TYPES = [
    "graph",
    "flowchart",
    "stateDiagram",
    "stateDiagram-v2",
    "sequenceDiagram",
    "classDiagram",
    "erDiagram",
    "journey",
    "gantt",
    "pie",
    "mindmap",
    "timeline",
    "C4Context",
]


def main() -> int:
    root = repo_root()
    rep = Reporter("mermaid-blocks")

    markdown = [
        p for p in tracked_files(root)
        if p.suffix.lower() == ".md" and ".git" not in p.parts
    ]
    rep.info(f"checking {len(markdown)} markdown files")

    unbalanced: list[str] = []
    empty_blocks: list[str] = []
    unknown_types: list[str] = []
    total_diagrams = 0
    files_with_diagrams = 0

    for path in sorted(markdown):
        rel = path.relative_to(root).as_posix()
        text = read(path)
        if not text:
            continue

        balanced, count = fenced_blocks_balanced(text)
        if not balanced:
            unbalanced.append(f"{rel} ({count} fences — odd, so one is unclosed)")
            # A file with an unclosed fence cannot be parsed further with
            # confidence, so its diagrams are not inspected.
            continue

        blocks = mermaid_blocks(text)
        if blocks:
            files_with_diagrams += 1
        for i, body in enumerate(blocks, start=1):
            total_diagrams += 1
            stripped = body.strip()
            if not stripped:
                empty_blocks.append(f"{rel} diagram {i}")
                continue
            first = stripped.splitlines()[0].strip()
            # Strip an optional leading direction/config token.
            if not any(
                re.match(rf"^{re.escape(t)}\b", first) for t in KNOWN_DIAGRAM_TYPES
            ):
                unknown_types.append(f"{rel} diagram {i}: {first[:60]!r}")

    # --- fences ---
    if unbalanced:
        rep.fail("every markdown file has balanced code fences")
        for entry in unbalanced[:15]:
            rep.info(f"unclosed fence: {entry}")
    else:
        rep.ok("every markdown file has balanced code fences")

    # --- diagram bodies ---
    if empty_blocks:
        rep.fail("no Mermaid block is empty")
        for entry in empty_blocks[:10]:
            rep.info(f"empty: {entry}")
    else:
        rep.ok("no Mermaid block is empty")

    if unknown_types:
        rep.fail("every Mermaid block declares a recognised diagram type")
        for entry in unknown_types[:10]:
            rep.info(f"unrecognised: {entry}")
    else:
        rep.ok("every Mermaid block declares a recognised diagram type")

    rep.info(f"{total_diagrams} Mermaid diagrams across {files_with_diagrams} files")
    rep.check(total_diagrams > 0, "the corpus contains at least one Mermaid diagram")

    rep.info(
        "NOTE: structural validation only. No diagram was rendered, and no claim "
        "of successful visual rendering is made."
    )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
