#!/usr/bin/env python3
"""Validate the public tracking security model.

The tracking portal is the product's most exposed surface. Standard library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import read  # noqa: E402

TRACKING_DOC = "docs/domain/TRACKING_DOMAIN.md"
LIFECYCLE_DOC = "docs/state-machines/TRACKING_ACCESS_LIFECYCLE.md"

REQUIRED_RULES: list[tuple[str, list[str]]] = [
    ("the token is high-entropy", [r"high[- ]entropy", r"cryptographically\s+secure"]),
    ("the token is stored hashed", [r"hash"]),
    (
        "the token is NOT the order number",
        [r"not\s+the\s+order\s+number", r"never\s+the\s+order\s+number",
         r"order\s+number[^.\n]{0,60}(?:never|not)\s+grant"],
    ),
    (
        "the token is not derivable from the order number",
        [r"not\s+deriv", r"never\s+deriv", r"not\s+guessable", r"non-?guessable"],
    ),
    ("the token is revocable", [r"revok", r"revoc"]),
    ("the token expires", [r"expir"]),
    ("the portal is noindex", [r"noindex"]),
    ("rate limiting applies", [r"rate[- ]limit"]),
    ("enumeration protection applies", [r"enumerat"]),
    ("the customer name is masked", [r"mask"]),
    (
        "the full address is never shown",
        [r"never\s+show[^.\n]{0,60}(?:full\s+)?address",
         r"(?:full\s+)?address[^.\n]{0,60}never", r"not\s+show[^.\n]{0,40}full\s+address"],
    ),
    (
        "sensitive actions require OTP",
        [r"otp"],
    ),
    (
        "the tracking projection is separate from the internal order",
        [r"projection", r"separate\s+read\s+model", r"read\s+model"],
    ),
    (
        "internal notes are not exposed",
        [r"internal\s+note"],
    ),
]

# The plaintext token must never be described as stored.
FORBIDDEN = [
    (
        r"stor\w+\s+(?:the\s+)?(?:plaintext|plain[- ]text|raw)\s+token",
        "storing the plaintext token",
    ),
    (
        r"token\s+is\s+the\s+order\s+number",
        "using the order number as the token",
    ),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("tracking-rules")

    tpath = root / TRACKING_DOC
    lpath = root / LIFECYCLE_DOC
    ok = rep.check(tpath.is_file(), f"{TRACKING_DOC} exists")
    rep.check(lpath.is_file(), f"{LIFECYCLE_DOC} exists")
    if not ok:
        return rep.finish()

    text = read(tpath) + "\n" + read(lpath)
    lower = text.lower()

    for label, patterns in REQUIRED_RULES:
        rep.check(
            any(re.search(p, lower, re.IGNORECASE) for p in patterns),
            f"tracking model states: {label}",
        )

    for pattern, label in FORBIDDEN:
        hit = re.search(pattern, lower, re.IGNORECASE)
        if hit:
            rep.fail(f"tracking model never describes {label}")
            rep.info(f"matched: {hit.group(0)!r}")
        else:
            rep.ok(f"tracking model never describes {label}")

    # --- lifecycle completeness ---
    if lpath.is_file():
        life = read(lpath).lower()
        for label, patterns in [
            ("issuance", [r"issu"]),
            ("revocation", [r"revok", r"revoc"]),
            ("expiry", [r"expir"]),
            ("reissue", [r"re-?issu", r"rotat"]),
        ]:
            rep.check(
                any(re.search(p, life) for p in patterns),
                f"tracking access lifecycle covers: {label}",
            )

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
