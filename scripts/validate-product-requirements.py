#!/usr/bin/env python3
"""Validate the Step 1 product documentation set.

Asserts the documents exist, the PRD declares its version and canonical facts,
pricing is reproduced exactly, and nothing claims an implementation that does not
exist. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import (  # noqa: E402
    PRODUCT_DOCS,
    read,
    strip_code_blocks,
)

PRODUCT_DIR = "docs/product"
PRD = "docs/product/PRODUCT_REQUIREMENTS.md"

# Pricing must appear character for character. These are the canonical figures
# from MASTER_SOURCE.md §21; paraphrasing any of them is a commercial risk on a
# public repository (Rule 14, Rule 23).
PRICING_LITERALS = [
    "Rp79.000",
    "Rp199.000",
    "Rp399.000",
    "Rp999.000",
    "Rp790.000",
    "Rp1.990.000",
    "Rp3.990.000",
    "14 hari gratis",
]

REQUIRED_PRD_TOPICS = [
    ("product vision", ["vision", "visi"]),
    ("problem statement", ["problem statement", "problem"]),
    ("goals", ["goal"]),
    ("non-goals", ["non-goal", "non goal"]),
    ("personas", ["persona"]),
    ("MVP scope", ["mvp"]),
    ("functional requirements", ["functional requirement"]),
    ("security requirements", ["security requirement", "security"]),
    ("privacy requirements", ["privacy"]),
    ("multi-tenancy requirements", ["multi-tenan", "tenancy", "tenant"]),
    ("financial requirements", ["financial"]),
    ("offline requirements", ["offline"]),
    ("tracking requirements", ["tracking"]),
    ("pickup and delivery requirements", ["pickup"]),
    ("unclaimed laundry requirements", ["unclaimed", "menumpuk"]),
    ("reporting requirements", ["report"]),
    ("subscription requirements", ["subscription"]),
    ("acceptance criteria", ["acceptance criteri"]),
    ("traceability", ["traceab"]),
    ("assumptions", ["assumption"]),
    ("open questions", ["open question"]),
    ("risks", ["risk"]),
]

# A claim of implementation. Prose such as "not implemented" is the safe form.
IMPLEMENTED_CLAIM = re.compile(r"\bIMPLEMENTED\b")
NEGATED = re.compile(
    r"\b(?:NOT|NON|BELUM|TIDAK|NEVER|NO)[ _-]+IMPLEMENTED\b"
)

# Language that would claim a test, build, deployment, or UAT actually happened.
FALSE_CLAIM_PATTERNS = [
    (r"\btests?\s+(?:pass(?:ed|es)?|are\s+green)\b", "claims tests passed"),
    (r"\bCI\s+is\s+green\b", "claims CI is green"),
    (r"\bdeployed\s+to\s+(?:production|staging)\b", "claims a deployment"),
    (r"\bUAT\s+(?:complete|passed|done)\b", "claims UAT completed"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("product-requirements")

    # --- documents exist ---
    for name in PRODUCT_DOCS:
        rep.check((root / PRODUCT_DIR / name).is_file(), f"exists: {PRODUCT_DIR}/{name}")

    prd_path = root / PRD
    if not prd_path.is_file():
        return rep.finish()

    text = read(prd_path)
    prose = strip_code_blocks(text)
    lower = text.lower()

    # --- document control ---
    rep.check(
        re.search(r"\b1\.0\.0\b", text) is not None,
        "PRD declares a document version",
    )
    rep.check(
        "Aish Laundry App" in text,
        'PRD uses the canonical product name "Aish Laundry App"',
    )

    # --- required topics ---
    for label, keywords in REQUIRED_PRD_TOPICS:
        rep.check(any(k in lower for k in keywords), f"PRD covers topic: {label}")

    # --- pricing reproduced exactly, somewhere in the product corpus ---
    corpus_text = "\n".join(
        read(root / PRODUCT_DIR / name) for name in PRODUCT_DOCS
    )
    for literal in PRICING_LITERALS:
        rep.check(
            literal in corpus_text,
            f"pricing reproduced exactly: {literal}",
        )

    # --- substance ---
    line_count = len(text.splitlines())
    rep.check(line_count >= 300, f"PRD is substantial: {line_count} lines (minimum 300)")

    # --- honesty: no bare IMPLEMENTED claim ---
    offending = []
    for i, line in enumerate(text.splitlines(), start=1):
        if not IMPLEMENTED_CLAIM.search(line):
            continue
        if IMPLEMENTED_CLAIM.search(NEGATED.sub("", line)):
            offending.append((i, line.strip()))
    if offending:
        rep.fail("PRD marks nothing as IMPLEMENTED")
        for lineno, content in offending[:5]:
            rep.info(f"line {lineno}: {content[:100]}")
    else:
        rep.ok("PRD marks nothing as IMPLEMENTED")

    # --- honesty: no claim of tests, CI, deployment, or UAT ---
    for pattern, label in FALSE_CLAIM_PATTERNS:
        hit = re.search(pattern, prose, re.IGNORECASE)
        if hit:
            rep.fail(f"PRD never {label}")
            rep.info(f"matched: {hit.group(0)!r}")
        else:
            rep.ok(f"PRD never {label}")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
