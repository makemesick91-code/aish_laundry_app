#!/usr/bin/env python3
"""Validate the financial-integrity rules in the Step 1 corpus.

Money must be integer Rupiah, floating point must be forbidden, payments must be
idempotent, price snapshots immutable, and corrections reversal-only. Standard
library only.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _common import Reporter, repo_root, run_main  # noqa: E402
from _step01 import corpus, read, strip_code_blocks  # noqa: E402

PAYMENT_DOC = "docs/domain/PAYMENT_DOMAIN.md"

# Each rule: label, list of alternative regexes, any of which satisfies it.
REQUIRED_RULES: list[tuple[str, list[str]]] = [
    ("money is integer Rupiah", [r"integer\s+rupiah"]),
    (
        "floating point is forbidden in financial paths",
        [r"float(?:ing[- ]point)?[^.\n]{0,80}(?:forbidden|never|not\s+permitted|prohibited)",
         r"(?:no|never\s+use|forbidden)[^.\n]{0,40}float"],
    ),
    ("payments are idempotent", [r"idempoten"]),
    (
        "idempotency is keyed on a client reference",
        [r"client[_ ]?reference", r"clientreference"],
    ),
    (
        "historical price snapshot is immutable",
        [r"price\s+snapshot", r"historical\s+price"],
    ),
    (
        "a price-list change never alters a past order",
        [r"price[- ]list[^.\n]{0,120}(?:never|not)\s+(?:change|alter|affect)",
         r"(?:never|not)\s+(?:change|alter|affect)[^.\n]{0,120}(?:historical|past)\s+order",
         r"historical\s+order[^.\n]{0,120}immut"],
    ),
    (
        "financial records are never hard-deleted",
        [r"never\s+(?:be\s+)?(?:hard[- ])?delete", r"no\s+hard\s+delete",
         r"not\s+deleted\s+through"],
    ),
    (
        "corrections are reversal or adjustment entries",
        [r"revers(?:al|ed|ing)", r"adjustment\s+entr"],
    ),
    (
        "an order is never marked paid on a client claim",
        [r"client\s+claim", r"never\s+mark\w*\s+paid"],
    ),
    (
        "gateway callbacks are verified server-side",
        [r"callback[^.\n]{0,80}verif", r"verif\w+[^.\n]{0,80}callback",
         r"server[- ]side[^.\n]{0,60}verif"],
    ),
    ("refund requires permission and a reason", [r"refund[^.\n]{0,120}reason"]),
    (
        "shift closing compares expected against actual cash",
        [r"expected[^.\n]{0,60}actual", r"variance"],
    ),
    ("courier cash is reconciled", [r"reconcil"]),
]

# Language that would contradict the integer-Rupiah rule.
FLOAT_LEAK = [
    (r"\bfloat\s*\(\s*(?:amount|total|price|money)", "a float() cast on money"),
    (r"\b(?:amount|total|price)\s*:\s*(?:float|double)\b", "a float-typed money field"),
    (r"\bdecimal\s*\(\s*\d+\s*,\s*[1-9]", "a fractional decimal money column"),
]


def main() -> int:
    root = repo_root()
    rep = Reporter("money-rules")

    text = corpus(root)
    if not rep.check(bool(text.strip()), "Step 1 corpus exists to validate"):
        return rep.finish()

    lower = text.lower()

    for label, patterns in REQUIRED_RULES:
        hit = any(re.search(p, lower, re.IGNORECASE) for p in patterns)
        rep.check(hit, f"corpus states: {label}")

    # --- the payment domain document specifically ---
    payment_path = root / PAYMENT_DOC
    if rep.check(payment_path.is_file(), f"{PAYMENT_DOC} exists"):
        payment = read(payment_path).lower()
        for label, patterns in [
            ("money is integer Rupiah", [r"integer\s+rupiah"]),
            ("payments are idempotent", [r"idempoten"]),
            ("corrections are reversal-based", [r"revers"]),
        ]:
            rep.check(
                any(re.search(p, payment, re.IGNORECASE) for p in patterns),
                f"payment domain states: {label}",
            )

    # --- no floating point leaking into a money path ---
    prose = strip_code_blocks(text)
    for pattern, label in FLOAT_LEAK:
        hit = re.search(pattern, prose, re.IGNORECASE)
        if hit:
            rep.fail(f"no {label} in the corpus")
            rep.info(f"matched: {hit.group(0)!r}")
        else:
            rep.ok(f"no {label} in the corpus")

    return rep.finish()


if __name__ == "__main__":
    run_main(main)
