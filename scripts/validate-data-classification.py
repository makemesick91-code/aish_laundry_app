#!/usr/bin/env python3
"""Validate the data classification model and the public-repository constraint.

Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import DATA_CLASSES, read  # noqa: E402

DOC = "docs/security/DATA_CLASSIFICATION.md"

# element keyword -> required classification
REQUIRED_ASSIGNMENTS: list[tuple[str, str, list[str]]] = [
    ("marketing pricing", "PUBLIC", ["pricing", "harga"]),
    ("customer phone", "CONFIDENTIAL", ["phone"]),
    ("customer address", "RESTRICTED", ["address"]),
    ("tracking token", "SECRET", ["tracking token"]),
    ("OTP", "SECRET", ["otp"]),
    ("payment provider credential", "SECRET", ["credential"]),
    ("private key", "SECRET", ["private key"]),
    ("laundry photograph", "RESTRICTED", ["photo"]),
]

PUBLIC_REPO_RULES: list[tuple[str, list[str]]] = [
    (
        "only PUBLIC and sanitised INTERNAL material is committed",
        [r"only\s+public[^.\n]{0,80}internal", r"sanitis\w+\s+internal",
         r"sanitiz\w+\s+internal", r"only[^.\n]{0,40}internal[^.\n]{0,60}sanitis"],
    ),
    (
        "higher classes are never instantiated with real values",
        [r"never\s+instantiat", r"not\s+instantiat", r"never[^.\n]{0,60}real\s+value"],
    ),
    (
        "every example datum is fictional",
        [r"fiction", r"fictitious"],
    ),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("data-classification")

    path = root / DOC
    if not rep.check(path.is_file(), f"{DOC} exists"):
        return rep.finish()

    raw = read(path)
    # Markdown emphasis and code ticks sit inside the phrases being matched
    # ("Only `PUBLIC` and sanitised `INTERNAL`"), so they are stripped before any
    # content assertion. Matching against the raw text produced false failures.
    text = re.sub(r"[`*_]", "", raw)
    lower = text.lower()

    # --- all five classes defined ---
    for cls in DATA_CLASSES:
        rep.check(
            re.search(rf"\b{cls}\b", text) is not None,
            f"data class defined: {cls}",
        )

    # --- canonical assignments present ---
    #
    # An element may be classified either inline on its own row, or by sitting in
    # a section whose heading names the class. Both shapes are accepted; a
    # document-wide search is not, because a legend listing every class would
    # otherwise satisfy every assignment by accident.
    headings = [
        (m.start(), m.group(0))
        for m in re.finditer(r"^#{1,6}\s+.*$", text, flags=re.MULTILINE)
    ]

    def enclosing_heading(pos: int) -> str:
        current = ""
        for start, heading in headings:
            if start <= pos:
                current = heading
            else:
                break
        return current

    for label, expected, keywords in REQUIRED_ASSIGNMENTS:
        found = False
        evidence = ""
        for kw in keywords:
            for m in re.finditer(re.escape(kw), lower):
                # 1. inline on the same row
                line_start = text.rfind("\n", 0, m.start()) + 1
                line_end = text.find("\n", m.start())
                line = text[line_start : line_end if line_end != -1 else len(text)]
                if re.search(rf"\b{expected}\b", line):
                    found, evidence = True, "inline"
                    break
                # 2. under a section heading naming the class
                if re.search(rf"\b{expected}\b", enclosing_heading(m.start())):
                    found, evidence = True, "section heading"
                    break
            if found:
                break
        if found:
            rep.ok(f"{label} is classified {expected} (via {evidence})")
        else:
            rep.fail(f"{label} is classified {expected}")

    # --- public repository constraint stated ---
    for label, patterns in PUBLIC_REPO_RULES:
        rep.check(
            any(re.search(p, lower, re.IGNORECASE) for p in patterns),
            f"public-repository constraint stated: {label}",
        )

    rep.check(
        re.search(r"\bpublic\b", lower) is not None
        and re.search(r"repositor", lower) is not None,
        "document acknowledges the repository is PUBLIC",
    )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
