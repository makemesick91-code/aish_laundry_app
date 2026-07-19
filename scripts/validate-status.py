#!/usr/bin/env python3
"""Validate docs/STATUS.md declares the canonical Step 0 status posture.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, read_text, repo_root, run_main  # noqa: E402

STATUS = "docs/STATUS.md"

STEP0_ALLOWED = ["IN PROGRESS", "TESTED", "WATCH", "GO"]

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

    # --- Steps 1..14 PLANNED ---
    entry_index = [
        (i, int(STEP_LINE.match(ln).group(1)))
        for i, ln in enumerate(lines)
        if STEP_LINE.match(ln)
    ]
    starts = sorted(i for i, _ in entry_index)
    for n in range(1, 15):
        hits = [i for i, num in entry_index if num == n]
        if not hits:
            rep.fail(f"Step {n} not declared in {STATUS}")
            continue
        marked = False
        for start in hits:
            following = [i for i in starts if i > start]
            end = following[0] if following else len(lines)
            if "PLANNED" in "\n".join(lines[start:end]).upper():
                marked = True
                break
        rep.check(marked, f"Step {n} declared PLANNED")

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
