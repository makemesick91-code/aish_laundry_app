#!/usr/bin/env python3
"""Validate docs/STATUS.md declares the canonical Step 0 status posture.

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

STATUS = "docs/STATUS.md"

STEP0_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

# The step currently under way. Bump only when a step actually starts, in the same
# pull request that moves the status in STATUS.md and ROADMAP.md.
CURRENT_STEP = 2
CURRENT_STEP_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

# Statuses that must never appear against a step later than CURRENT_STEP.
FORWARD_LEAK_STATUSES = ["IN PROGRESS", "TESTED", "WATCH", "GO", "NO-GO"]

# label -> (regex that must match somewhere in the document)
REQUIRED_DECLARATIONS: list[tuple[str, str]] = [
    ("backend runtime is ABSENT", r"backend[^\n]{0,60}?\bABSENT\b"),
    ("Flutter workspace is ABSENT", r"flutter[^\n]{0,60}?\bABSENT\b"),
    ("deployment is ABSENT", r"deploy\w*[^\n]{0,60}?\bABSENT\b"),
    ("UAT is NOT STARTED", r"uat[^\n]{0,60}?\bNOT[ _-]?STARTED\b"),
    (
        "application CI is NOT APPLICABLE",
        r"(?:application|aplikasi)[^\n]{0,40}ci[^\n]{0,60}?\bNOT[ _-]?APPLICABLE\b"
        r"|ci[^\n]{0,40}(?:application|aplikasi)[^\n]{0,60}?\bNOT[ _-]?APPLICABLE\b",
    ),
]

FORBIDDEN_IMPLEMENTED = re.compile(r"\bIMPLEMENTED\b")
# "NOT IMPLEMENTED" / "BELUM IMPLEMENTED" are the safe forms.
NEGATED_IMPLEMENTED = re.compile(r"\b(?:NOT|NON|BELUM|TIDAK|NEVER|NO)[ _-]+IMPLEMENTED\b")

STEP_LINE = re.compile(
    r"^\s{0,3}(?:#{1,6}\s*|[-*+]\s+|\|\s*|\d+\.\s+)?\*{0,2}Step\s+(\d{1,2})\b",
    re.IGNORECASE,
)


def main() -> int:
    root = repo_root()
    rep = Reporter("status")

    path = root / STATUS
    if not rep.check(path.is_file(), f"{STATUS} exists"):
        return rep.finish()

    text = read_text(path)
    lines = text.splitlines()
    upper = text.upper()

    # --- Step 0 status ---
    step0_lines = [ln for ln in lines if STEP_LINE.match(ln) and
                   int(STEP_LINE.match(ln).group(1)) == 0]
    if not step0_lines:
        rep.fail("Step 0 status line found")
    else:
        blob = " ".join(step0_lines).upper()
        hit = next((s for s in STEP0_ALLOWED if s in blob), None)
        if hit:
            rep.ok(f"Step 0 status is one of {STEP0_ALLOWED} (found: {hit})")
        else:
            rep.fail(f"Step 0 status must be one of {STEP0_ALLOWED}")
            rep.info(f"line: {step0_lines[0].strip()}")

    # --- Step status posture ---
    #
    # The current step may carry a working status. Every step after it must be
    # PLANNED and nothing else: a later step showing a working status means work
    # has leaked forward out of its declared scope.
    entry_index = [
        (i, int(STEP_LINE.match(ln).group(1)))
        for i, ln in enumerate(lines)
        if STEP_LINE.match(ln)
    ]
    starts = sorted(i for i, _ in entry_index)

    def blocks_for(n: int) -> list[str]:
        out = []
        for start in [i for i, num in entry_index if num == n]:
            following = [i for i in starts if i > start]
            end = following[0] if following else len(lines)
            out.append("\n".join(lines[start:end]).upper())
        return out

    def declared_for(n: int) -> list[str]:
        out: list[str] = []
        for blk in blocks_for(n):
            out.extend(declared_statuses(blk))
        return out

    current = declared_for(CURRENT_STEP)
    if not current:
        rep.fail(f"Step {CURRENT_STEP} declares a recognisable status")
    elif [s for s in current if s in CURRENT_STEP_ALLOWED]:
        rep.ok(
            f"Step {CURRENT_STEP} carries an allowed working status "
            f"(declared: {', '.join(sorted(set(current)))})"
        )
    else:
        rep.fail(
            f"Step {CURRENT_STEP} must carry one of {CURRENT_STEP_ALLOWED}; "
            f"declared: {', '.join(sorted(set(current)))}"
        )

    for n in range(CURRENT_STEP + 1, 15):
        declared = declared_for(n)
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
            rep.ok(f"Step {n} declared PLANNED")
        else:
            rep.fail(
                f"Step {n} declared PLANNED; found: "
                + ", ".join(sorted(set(declared)))
            )

    # --- no feature marked IMPLEMENTED ---
    # Status vocabulary is UPPERCASE, so the match is case-sensitive on the raw
    # line. Lowercase prose such as "never evidence of an implemented feature"
    # is not a status declaration and must not be flagged.
    offending = []
    for i, line in enumerate(lines):
        if not FORBIDDEN_IMPLEMENTED.search(line):
            continue
        cleaned = NEGATED_IMPLEMENTED.sub("", line)
        if FORBIDDEN_IMPLEMENTED.search(cleaned):
            offending.append((i + 1, line.strip()))
    if offending:
        rep.fail("no feature is marked IMPLEMENTED")
        for lineno, content in offending[:10]:
            rep.info(f"line {lineno}: {content}")
    else:
        rep.ok("no feature is marked IMPLEMENTED")

    # --- explicit absence declarations ---
    for label, pattern in REQUIRED_DECLARATIONS:
        rep.check(
            re.search(pattern, text, re.IGNORECASE) is not None,
            f"declares {label}",
        )

    rep.check("NOT STARTED" in upper, "uses status vocabulary NOT STARTED")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
