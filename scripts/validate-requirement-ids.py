#!/usr/bin/env python3
"""Validate requirement identifiers across the Step 1 corpus.

Every requirement must have a stable, unique, well-formed identifier, must be
defined exactly once, and must use only a canonical prefix. Standard library
only.
"""

from __future__ import annotations

import re
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import (  # noqa: E402
    DEFINITION_LINE,
    REQUIREMENT_ID,
    REQUIREMENT_PREFIXES,
    REQUIREMENT_REGISTERS,
    existing_step01_docs,
    read,
    requirement_ids,
)

MIN_TOTAL_REQUIREMENTS = 120



def main() -> int:
    root = repo_root()
    rep = Reporter("requirement-ids")

    docs = existing_step01_docs(root)
    if not rep.check(bool(docs), "Step 1 documents exist to validate"):
        return rep.finish()

    definitions: dict[str, list[str]] = defaultdict(list)
    all_mentioned: set[str] = set()
    malformed: list[tuple[str, int, str]] = []

    for path in docs:
        rel = path.relative_to(root).as_posix()
        text = read(path)
        all_mentioned |= requirement_ids(text)

        # Duplicate detection is scoped to each series' authoritative register.
        # A restatement elsewhere (the aggregate catalogue repeating invariants
        # per aggregate, the traceability matrix listing every ID) is a
        # cross-reference, not a second definition.
        for lineno, line in enumerate(text.splitlines(), start=1):
            m = DEFINITION_LINE.match(line)
            if not m:
                continue
            prefix = m.group(1)
            if REQUIREMENT_REGISTERS.get(prefix) != path.name:
                continue
            rid = f"{prefix}-{int(m.group(2)):03d}"
            definitions[rid].append(f"{rel}:{lineno}")

        # An identifier that is nearly right is worse than one that is absent,
        # because it silently escapes every traceability query.
        for m in re.finditer(
            r"\b(" + "|".join(REQUIREMENT_PREFIXES) + r")[-_ ]?(\d{1,5})\b", text
        ):
            token = m.group(0)
            if not REQUIREMENT_ID.fullmatch(token):
                lineno = text[: m.start()].count("\n") + 1
                malformed.append((rel, lineno, token))

    rep.info(f"scanned {len(docs)} Step 1 documents")

    # --- something was actually defined ---
    rep.check(
        len(definitions) >= MIN_TOTAL_REQUIREMENTS,
        f"corpus defines at least {MIN_TOTAL_REQUIREMENTS} requirements "
        f"(found {len(definitions)})",
    )

    # --- no duplicate definitions ---
    duplicates = {rid: locs for rid, locs in definitions.items() if len(locs) > 1}
    if duplicates:
        rep.fail(f"every requirement ID is defined exactly once ({len(duplicates)} duplicated)")
        for rid, locs in sorted(duplicates.items())[:10]:
            rep.info(f"{rid} defined at: {', '.join(locs[:4])}")
    else:
        rep.ok("every requirement ID is defined exactly once")

    # --- prefixes are canonical ---
    bad_prefix = sorted(
        {rid for rid in definitions if rid.split("-")[0] not in REQUIREMENT_PREFIXES}
    )
    if bad_prefix:
        rep.fail("every requirement ID uses a canonical prefix")
        for rid in bad_prefix[:10]:
            rep.info(f"non-canonical: {rid}")
    else:
        rep.ok("every requirement ID uses a canonical prefix")

    # --- malformed near-misses ---
    if malformed:
        rep.fail("no malformed requirement identifiers")
        for rel, lineno, token in malformed[:10]:
            rep.info(f"{rel}:{lineno}: {token!r} (expected PREFIX-000 form)")
    else:
        rep.ok("no malformed requirement identifiers")

    # --- per-prefix coverage report ---
    by_prefix: dict[str, int] = defaultdict(int)
    for rid in definitions:
        by_prefix[rid.split("-")[0]] += 1
    for prefix in REQUIREMENT_PREFIXES:
        count = by_prefix.get(prefix, 0)
        rep.check(count > 0, f"prefix {prefix}- has at least one requirement (found {count})")

    # --- references resolve to definitions ---
    dangling = sorted(all_mentioned - set(definitions))
    if dangling:
        rep.fail("every referenced requirement ID is defined somewhere")
        for rid in dangling[:15]:
            rep.info(f"referenced but never defined: {rid}")
    else:
        rep.ok("every referenced requirement ID is defined somewhere")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
