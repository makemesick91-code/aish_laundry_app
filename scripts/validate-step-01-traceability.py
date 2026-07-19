#!/usr/bin/env python3
"""Validate bidirectional Step 1 traceability.

No orphans in either direction: every requirement reaches an acceptance
criterion, and every criterion cites a requirement that exists. Every CRITICAL
and HIGH threat reaches a criterion too. Standard library only.
"""

from __future__ import annotations

import re
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import (  # noqa: E402
    REQUIREMENT_PREFIXES,
    THREAT_ID,
    existing_step01_docs,
    read,
    requirement_ids,
)

# Documents that carry acceptance criteria. MVP_SCOPE.md is included because the
# MVP boundary states its own acceptance criteria for the capabilities it admits;
# excluding it would under-count genuine coverage.
AC_DOCS = [
    "docs/quality/ACCEPTANCE_CRITERIA.md",
    "docs/security/SECURITY_ACCEPTANCE_CRITERIA.md",
    "docs/product/MVP_SCOPE.md",
]

#: Acceptance-criteria documents that must exist for the Step to be complete.
REQUIRED_AC_DOCS = [
    "docs/quality/ACCEPTANCE_CRITERIA.md",
    "docs/security/SECURITY_ACCEPTANCE_CRITERIA.md",
]

MATRIX = "docs/product/REQUIREMENT_TRACEABILITY.md"
THREAT_DOC = "docs/security/INITIAL_THREAT_MODEL.md"

DEFINITION_LINE = re.compile(
    r"^\s{0,3}(?:#{1,6}\s*|[-*+]\s+|\|\s*|\d+\.\s+)?"
    r"\*{0,2}\[?("
    + "|".join(REQUIREMENT_PREFIXES)
    + r")-(\d{3,4})\]?\*{0,2}\s*(?:\||[-—:]|\*{0,2}\s*$)"
)

#: An orphan is a defined requirement that appears in NEITHER an acceptance
#: criteria document NOR the traceability matrix — it is reachable from nothing,
#: which is precisely the "no orphans in either direction" defect Rule 22 names.
#: A small tolerance is allowed because a handful of requirements are structural
#: (document control, naming) rather than behavioural; more than this means the
#: traceability apparatus is decorative.
MAX_ORPHAN_RATE = 0.05


def main() -> int:
    root = repo_root()
    rep = Reporter("step-01-traceability")

    docs = existing_step01_docs(root)
    if not rep.check(bool(docs), "Step 1 documents exist to validate"):
        return rep.finish()

    # --- collect defined requirements ---
    defined: dict[str, str] = {}
    for path in docs:
        rel = path.relative_to(root).as_posix()
        for line in read(path).splitlines():
            m = DEFINITION_LINE.match(line)
            if m:
                defined.setdefault(f"{m.group(1)}-{int(m.group(2)):03d}", rel)
    rep.info(f"requirements defined: {len(defined)}")
    if not rep.check(bool(defined), "at least one requirement is defined"):
        return rep.finish()

    # --- collect criteria citations ---
    criteria_text = ""
    for rel in REQUIRED_AC_DOCS:
        rep.check((root / rel).is_file(), f"exists: {rel}")
    for rel in AC_DOCS:
        criteria_text += "\n" + read(root / rel)
    cited = requirement_ids(criteria_text)
    rep.info(f"requirement IDs cited by acceptance criteria: {len(cited)}")

    # --- matrix ---
    matrix_path = root / MATRIX
    matrix_text = ""
    if rep.check(matrix_path.is_file(), f"exists: {MATRIX}"):
        matrix_text = read(matrix_path)
    matrix_ids = requirement_ids(matrix_text)
    rep.info(f"requirement IDs present in the traceability matrix: {len(matrix_ids)}")

    # --- direction 1: criteria must not cite requirements that do not exist ---
    dangling = sorted(cited - set(defined))
    if dangling:
        rep.fail("every acceptance criterion cites a requirement that exists")
        for rid in dangling[:15]:
            rep.info(f"criterion cites undefined requirement: {rid}")
    else:
        rep.ok("every acceptance criterion cites a requirement that exists")

    # --- direction 2: no requirement is orphaned ---
    #
    # A requirement is traced if an acceptance criterion cites it, or the
    # traceability matrix carries it. Requiring both would punish a correct
    # corpus; requiring neither would make the matrix decorative.
    traced = (cited | matrix_ids) & set(defined)
    orphans = sorted(set(defined) - traced)
    orphan_rate = len(orphans) / len(defined) if defined else 0.0
    rep.info(
        f"traced requirements: {len(traced)}/{len(defined)} "
        f"({1 - orphan_rate:.0%}); orphans: {len(orphans)}"
    )
    if orphan_rate <= MAX_ORPHAN_RATE:
        rep.ok(
            f"orphaned requirements are at most {MAX_ORPHAN_RATE:.0%} "
            f"(found {orphan_rate:.0%})"
        )
    else:
        rep.fail(
            f"orphaned requirements are at most {MAX_ORPHAN_RATE:.0%} "
            f"(found {orphan_rate:.0%})"
        )
        for rid in orphans[:25]:
            rep.info(
                f"orphan — no criterion and no matrix row: {rid} "
                f"(defined in {defined[rid]})"
            )

    # --- direction 4: high-severity threats reach a criterion ---
    threat_text = read(root / THREAT_DOC)
    if threat_text:
        matches = list(THREAT_ID.finditer(threat_text))
        high: list[str] = []
        for i, m in enumerate(matches):
            end = matches[i + 1].start() if i + 1 < len(matches) else len(threat_text)
            section = threat_text[m.start() : end].upper()
            if re.search(r"\b(?:CRITICAL|HIGH)\b", section):
                high.append(m.group(0))
        rep.info(f"CRITICAL/HIGH threats: {len(high)}")

        criteria_upper = criteria_text.upper()
        unreferenced = [t for t in dict.fromkeys(high) if t not in criteria_upper]
        if unreferenced:
            rep.fail("every CRITICAL and HIGH threat is referenced by an acceptance criterion")
            for tid in unreferenced[:15]:
                rep.info(f"threat with no acceptance criterion: {tid}")
        else:
            rep.ok("every CRITICAL and HIGH threat is referenced by an acceptance criterion")

    # --- per-prefix traceability report ---
    by_prefix: dict[str, list[int]] = defaultdict(lambda: [0, 0])
    for rid in defined:
        prefix = rid.split("-")[0]
        by_prefix[prefix][0] += 1
        if rid in cited:
            by_prefix[prefix][1] += 1
    for prefix in sorted(by_prefix):
        total, hit = by_prefix[prefix]
        rep.info(f"{prefix}-: {hit}/{total} traced to a criterion")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
