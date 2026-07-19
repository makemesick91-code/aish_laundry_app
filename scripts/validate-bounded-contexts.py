#!/usr/bin/env python3
"""Validate the twenty canonical bounded contexts and the context map.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import BOUNDED_CONTEXTS, mermaid_blocks, read  # noqa: E402

CONTEXTS_DOC = "docs/domain/BOUNDED_CONTEXTS.md"
CONTEXT_MAP_DOC = "docs/domain/CONTEXT_MAP.md"

REQUIRED_FACETS = [
    ("purpose", ["purpose"]),
    ("primary actors", ["actor"]),
    ("aggregates", ["aggregate"]),
    ("commands", ["command"]),
    ("events", ["event"]),
    ("upstream contexts", ["upstream"]),
    ("downstream contexts", ["downstream"]),
    ("tenant boundary", ["tenant boundary", "tenant"]),
    ("sensitive data", ["sensitive data", "sensitive"]),
    ("failure impact", ["failure impact", "failure"]),
    ("implementation step", ["step"]),
]


def normalise(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip().lower()


def main() -> int:
    root = repo_root()
    rep = Reporter("bounded-contexts")

    path = root / CONTEXTS_DOC
    if not rep.check(path.is_file(), f"{CONTEXTS_DOC} exists"):
        return rep.finish()

    text = read(path)
    flat = normalise(text)

    rep.check(
        len(BOUNDED_CONTEXTS) == 20,
        f"the canonical context set is twenty (declared {len(BOUNDED_CONTEXTS)})",
    )

    for context in BOUNDED_CONTEXTS:
        rep.check(normalise(context) in flat, f"bounded context documented: {context}")

    for label, keywords in REQUIRED_FACETS:
        rep.check(
            any(k in flat for k in keywords),
            f"contexts document facet: {label}",
        )

    # --- context map ---
    map_path = root / CONTEXT_MAP_DOC
    if rep.check(map_path.is_file(), f"{CONTEXT_MAP_DOC} exists"):
        map_text = read(map_path)
        blocks = mermaid_blocks(map_text)
        rep.check(bool(blocks), "context map contains at least one Mermaid diagram")

        # A diagram is never the specification on its own (Rule 17, Rule 19).
        without_diagrams = re.sub(r"```.*?```", "", map_text, flags=re.DOTALL)
        rep.check(
            len(without_diagrams.split()) >= 200,
            "context map has a textual explanation alongside its diagram",
        )

        map_flat = normalise(map_text)
        missing = [c for c in BOUNDED_CONTEXTS if normalise(c) not in map_flat]
        if missing:
            rep.fail("context map references every bounded context")
            for name in missing[:10]:
                rep.info(f"missing from context map: {name}")
        else:
            rep.ok("context map references every bounded context")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
